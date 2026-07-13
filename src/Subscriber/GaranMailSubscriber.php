<?php

declare(strict_types=1);

namespace Hug\EuLabel\Subscriber;

use Hug\EuLabel\Service\GaranConditionsFileLoader;
use Hug\EuLabel\Service\GaranData;
use Hug\EuLabel\Service\GaranDataResolver;
use Hug\EuLabel\Service\GaranSummaryPdfGenerator;
use Hug\EuLabel\Service\OrderConfirmationMailChecker;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Events\FlowSendMailActionEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Hängt Garantieunterlagen an die Bestellbestätigung: die als PDF gepflegten
 * Garantiebedingungen der bestellten Produkte (dedupliziert) und/oder eine
 * generierte Garantieübersicht — gesteuert über garanMailMode. Fehler
 * verhindern nie den Mailversand.
 */
class GaranMailSubscriber implements EventSubscriberInterface
{
    public const SUMMARY_FILE_NAME = 'Garantie-Uebersicht.pdf';

    private const CONFIG_ENABLED = 'HugEuLabel.config.garanEnabled';
    private const CONFIG_MAIL_MODE = 'HugEuLabel.config.garanMailMode';

    private const MODE_DISABLED = 'disabled';
    private const MODE_CONDITIONS = 'conditions_pdfs';
    private const MODE_SUMMARY = 'summary_pdf';
    private const MODE_BOTH = 'both';

    /**
     * @param EntityRepository<OrderCollection> $orderRepository
     */
    public function __construct(
        private readonly GaranDataResolver $resolver,
        private readonly SystemConfigService $systemConfigService,
        private readonly OrderConfirmationMailChecker $mailChecker,
        private readonly EntityRepository $orderRepository,
        private readonly GaranConditionsFileLoader $fileLoader,
        private readonly GaranSummaryPdfGenerator $summaryGenerator,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            FlowSendMailActionEvent::class => 'onFlowSendMail',
        ];
    }

    public function onFlowSendMail(FlowSendMailActionEvent $event): void
    {
        try {
            $this->attachGuaranteeDocuments($event);
        } catch (\Throwable $exception) {
            $this->logger?->error(
                'HugEuLabel: attaching the GARAN guarantee documents failed; the mail is sent without them.',
                ['exception' => $exception],
            );
        }
    }

    private function attachGuaranteeDocuments(FlowSendMailActionEvent $event): void
    {
        $salesChannelId = $event->getStorableFlow()->getData(MailAware::SALES_CHANNEL_ID);
        $salesChannelId = \is_string($salesChannelId) && $salesChannelId !== '' ? $salesChannelId : null;

        if (!$this->systemConfigService->getBool(self::CONFIG_ENABLED, $salesChannelId)) {
            return;
        }

        $mode = $this->systemConfigService->getString(self::CONFIG_MAIL_MODE, $salesChannelId) ?: self::MODE_CONDITIONS;
        if ($mode === self::MODE_DISABLED) {
            return;
        }

        if (!$this->mailChecker->isOrderConfirmation($event)) {
            return;
        }

        $order = $this->loadOrder($event);
        if ($order === null) {
            return;
        }

        $items = $this->collectGuaranteeItems($order, $event);
        if ($items === []) {
            return;
        }

        $attachments = [];

        if ($mode === self::MODE_CONDITIONS || $mode === self::MODE_BOTH) {
            $attachments = [...$attachments, ...$this->buildConditionsAttachments($items, $event)];
        }

        if ($mode === self::MODE_SUMMARY || $mode === self::MODE_BOTH) {
            $summary = $this->summaryGenerator->generate($items);
            if ($summary !== null) {
                $attachments[] = [
                    'content' => $summary,
                    'fileName' => self::SUMMARY_FILE_NAME,
                    'mimeType' => 'application/pdf',
                ];
            }
        }

        if ($attachments === []) {
            return;
        }

        $this->appendAttachments($event->getDataBag(), $attachments);
    }

    private function loadOrder(FlowSendMailActionEvent $event): ?OrderEntity
    {
        $orderId = $event->getStorableFlow()->getData(OrderAware::ORDER_ID);
        if (!\is_string($orderId) || $orderId === '') {
            return null;
        }

        $criteria = new Criteria([$orderId]);
        $criteria->setTitle('hug-eu-label::garan-order');
        $criteria->addAssociation('lineItems.product.manufacturer');

        return $this->orderRepository->search($criteria, $event->getContext())->getEntities()->first();
    }

    /**
     * @return list<array{label: string, productNumber: string, data: GaranData}>
     */
    private function collectGuaranteeItems(OrderEntity $order, FlowSendMailActionEvent $event): array
    {
        $items = [];

        foreach ($order->getLineItems() ?? [] as $lineItem) {
            if ($lineItem->getType() !== LineItem::PRODUCT_LINE_ITEM_TYPE) {
                continue;
            }

            $product = $lineItem->getProduct();
            if ($product === null) {
                continue;
            }

            $data = $this->resolver->resolve($product, $event->getContext());
            if ($data === null) {
                continue;
            }

            $items[] = [
                'label' => $lineItem->getLabel(),
                'productNumber' => $product->getProductNumber(),
                'data' => $data,
            ];
        }

        return $items;
    }

    /**
     * @param list<array{label: string, productNumber: string, data: GaranData}> $items
     *
     * @return list<array{content: string, fileName: string, mimeType: string}>
     */
    private function buildConditionsAttachments(array $items, FlowSendMailActionEvent $event): array
    {
        $attachments = [];
        $seenMediaIds = [];

        foreach ($items as $item) {
            $mediaId = $item['data']->conditionsMediaId;
            if ($mediaId === null || isset($seenMediaIds[$mediaId])) {
                continue;
            }
            $seenMediaIds[$mediaId] = true;

            $content = $this->fileLoader->load($mediaId, $event->getContext());
            if ($content === null) {
                $this->logger?->warning(
                    'HugEuLabel: GARAN conditions PDF unavailable; the order confirmation is sent without it.',
                    ['mediaId' => $mediaId, 'manufacturer' => $item['data']->manufacturerName],
                );

                continue;
            }

            $attachments[] = [
                'content' => $content,
                'fileName' => \sprintf('Garantiebedingungen-%s.pdf', $this->slugify($item['data']->manufacturerName)),
                'mimeType' => 'application/pdf',
            ];
        }

        return $attachments;
    }

    /**
     * @param list<array{content: string, fileName: string, mimeType: string}> $newAttachments
     */
    private function appendAttachments(DataBag $dataBag, array $newAttachments): void
    {
        $attachments = $dataBag->get('binAttachments');
        if ($attachments instanceof DataBag) {
            $attachments = $attachments->all();
        } elseif (!\is_array($attachments)) {
            $attachments = [];
        }

        $dataBag->set('binAttachments', [...$attachments, ...$newAttachments]);
    }

    private function slugify(string $value): string
    {
        $slug = preg_replace('/[^A-Za-z0-9]+/', '-', $value) ?? '';

        return trim($slug, '-') ?: 'Hersteller';
    }
}
