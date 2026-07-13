<?php

declare(strict_types=1);

namespace Hug\EuLabel\Tests\Unit\Subscriber;

use Hug\EuLabel\Service\GaranConditionsFileLoader;
use Hug\EuLabel\Service\GaranData;
use Hug\EuLabel\Service\GaranDataResolver;
use Hug\EuLabel\Service\GaranSummaryPdfGenerator;
use Hug\EuLabel\Service\OrderConfirmationMailChecker;
use Hug\EuLabel\Subscriber\GaranMailSubscriber;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Events\FlowSendMailActionEvent;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

final class GaranMailSubscriberTest extends TestCase
{
    private string $salesChannelId;

    private string $orderId;

    protected function setUp(): void
    {
        $this->salesChannelId = Uuid::randomHex();
        $this->orderId = Uuid::randomHex();
    }

    public function testDisabledMailModeAttachesNothing(): void
    {
        $event = $this->createEvent();

        $subscriber = $this->createSubscriber(mailMode: 'disabled');
        $subscriber->onFlowSendMail($event);

        self::assertFalse($event->getDataBag()->has('binAttachments'));
    }

    public function testWrongMailTypeAttachesNothing(): void
    {
        $event = $this->createEvent();

        $subscriber = $this->createSubscriber(isOrderConfirmation: false);
        $subscriber->onFlowSendMail($event);

        self::assertFalse($event->getDataBag()->has('binAttachments'));
    }

    public function testConditionsPdfsAreDedupedPerMedia(): void
    {
        $mediaId = Uuid::randomHex();
        $event = $this->createEvent();

        // Zwei Positionen desselben Herstellers → dasselbe Bedingungs-PDF nur einmal.
        $subscriber = $this->createSubscriber(
            mailMode: 'conditions_pdfs',
            order: $this->createOrder([$this->createProduct(), $this->createProduct()]),
            garanData: new GaranData(5, 'https://example.com/g', 'Holzwerk GmbH', $mediaId),
            fileLoaderContent: '%PDF-1.4 bedingungen',
        );
        $subscriber->onFlowSendMail($event);

        $attachments = $event->getDataBag()->all()['binAttachments'] ?? null;
        self::assertIsArray($attachments);
        self::assertCount(1, $attachments);
        self::assertIsArray($attachments[0]);
        self::assertSame('Garantiebedingungen-Holzwerk-GmbH.pdf', $attachments[0]['fileName']);
        self::assertSame('%PDF-1.4 bedingungen', $attachments[0]['content']);
    }

    public function testSummaryPdfIsAttached(): void
    {
        $event = $this->createEvent();

        $subscriber = $this->createSubscriber(
            mailMode: 'summary_pdf',
            order: $this->createOrder([$this->createProduct()]),
            garanData: new GaranData(5, 'https://example.com/g', 'Holzwerk GmbH'),
            summaryPdf: '%PDF-1.7 uebersicht',
        );
        $subscriber->onFlowSendMail($event);

        $attachments = $event->getDataBag()->all()['binAttachments'] ?? null;
        self::assertIsArray($attachments);
        self::assertCount(1, $attachments);
        self::assertIsArray($attachments[0]);
        self::assertSame('Garantie-Uebersicht.pdf', $attachments[0]['fileName']);
        self::assertStringStartsWith('%PDF', $attachments[0]['content']);
    }

    public function testFailingMediaLoadLogsWarningAndSendsMailWithoutAttachment(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::atLeastOnce())->method('warning');

        $event = $this->createEvent();

        $subscriber = $this->createSubscriber(
            mailMode: 'conditions_pdfs',
            order: $this->createOrder([$this->createProduct()]),
            garanData: new GaranData(5, 'https://example.com/g', 'Holzwerk GmbH', Uuid::randomHex()),
            fileLoaderContent: null,
            logger: $logger,
        );
        $subscriber->onFlowSendMail($event);

        self::assertFalse($event->getDataBag()->has('binAttachments'));
    }

    public function testExistingAttachmentsAreKept(): void
    {
        $existing = ['content' => 'label', 'fileName' => 'EU-Gewaehrleistungslabel.pdf', 'mimeType' => 'application/pdf'];
        $event = $this->createEvent();
        $event->getDataBag()->set('binAttachments', [$existing]);

        $subscriber = $this->createSubscriber(
            mailMode: 'conditions_pdfs',
            order: $this->createOrder([$this->createProduct()]),
            garanData: new GaranData(5, 'https://example.com/g', 'Holzwerk GmbH', Uuid::randomHex()),
            fileLoaderContent: '%PDF-1.4 bedingungen',
        );
        $subscriber->onFlowSendMail($event);

        $attachments = $event->getDataBag()->all()['binAttachments'] ?? null;
        self::assertIsArray($attachments);
        self::assertCount(2, $attachments);
        self::assertSame($existing, $attachments[0]);
    }

    public function testOrderWithoutGuaranteesAttachesNothing(): void
    {
        $event = $this->createEvent();

        $subscriber = $this->createSubscriber(
            mailMode: 'both',
            order: $this->createOrder([$this->createProduct()]),
            garanData: null,
        );
        $subscriber->onFlowSendMail($event);

        self::assertFalse($event->getDataBag()->has('binAttachments'));
    }

    private function createSubscriber(
        string $mailMode = 'conditions_pdfs',
        bool $isOrderConfirmation = true,
        ?OrderEntity $order = null,
        ?GaranData $garanData = null,
        ?string $fileLoaderContent = '%PDF-1.4 default',
        ?string $summaryPdf = '%PDF-1.7 default',
        ?LoggerInterface $logger = null,
    ): GaranMailSubscriber {
        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig->method('getBool')->willReturn(true);
        $systemConfig->method('getString')->willReturn($mailMode);

        $checker = $this->createMock(OrderConfirmationMailChecker::class);
        $checker->method('isOrderConfirmation')->willReturn($isOrderConfirmation);

        $resolver = $this->createMock(GaranDataResolver::class);
        $resolver->method('resolve')->willReturn($garanData);

        $fileLoader = $this->createMock(GaranConditionsFileLoader::class);
        $fileLoader->method('load')->willReturn($fileLoaderContent);

        $summaryGenerator = $this->createMock(GaranSummaryPdfGenerator::class);
        $summaryGenerator->method('generate')->willReturn($summaryPdf);

        /** @var StaticEntityRepository<OrderCollection> $orderRepository */
        $orderRepository = new StaticEntityRepository([
            new OrderCollection($order !== null ? [$order] : []),
        ]);

        return new GaranMailSubscriber(
            $resolver,
            $systemConfig,
            $checker,
            $orderRepository,
            $fileLoader,
            $summaryGenerator,
            $logger,
        );
    }

    private function createProduct(): ProductEntity
    {
        $product = new ProductEntity();
        $product->setId(Uuid::randomHex());
        $product->setUniqueIdentifier($product->getId());
        $product->setProductNumber('HW-1');

        return $product;
    }

    /**
     * @param list<ProductEntity> $products
     */
    private function createOrder(array $products): OrderEntity
    {
        $lineItems = new OrderLineItemCollection();
        foreach ($products as $product) {
            $lineItem = new OrderLineItemEntity();
            $lineItem->setId(Uuid::randomHex());
            $lineItem->setUniqueIdentifier($lineItem->getId());
            $lineItem->setIdentifier($lineItem->getId());
            $lineItem->setType(LineItem::PRODUCT_LINE_ITEM_TYPE);
            $lineItem->setLabel('Testprodukt');
            $lineItem->setProduct($product);
            $lineItems->add($lineItem);
        }

        $order = new OrderEntity();
        $order->setId($this->orderId);
        $order->setUniqueIdentifier($this->orderId);
        $order->setLineItems($lineItems);

        return $order;
    }

    private function createEvent(): FlowSendMailActionEvent
    {
        $context = new Context(new SystemSource());

        $mailTemplate = new MailTemplateEntity();
        $mailTemplate->setId(Uuid::randomHex());
        $mailTemplate->setUniqueIdentifier($mailTemplate->getId());

        $flow = new StorableFlow('checkout.order.placed', $context, [], [
            MailAware::SALES_CHANNEL_ID => $this->salesChannelId,
            OrderAware::ORDER_ID => $this->orderId,
        ]);

        return new FlowSendMailActionEvent(new DataBag(), $mailTemplate, $flow);
    }
}
