<?php

declare(strict_types=1);

namespace Hug\EuLabel\Subscriber;

use Hug\EuLabel\Service\LabelProvider;
use Hug\EuLabel\Service\OrderConfirmationMailChecker;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Flow\Events\FlowSendMailActionEvent;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Attaches the EU warranty label PDF to the order confirmation mail
 * (durable medium requirement of Directive (EU) 2024/825).
 */
class MailAttachmentSubscriber implements EventSubscriberInterface
{
    public const ATTACHMENT_FILE_NAME = 'EU-Gewaehrleistungslabel.pdf';

    private const MAIL_MODE_PDF_ATTACHMENT = 'pdf_attachment';

    private const CONFIG_ACTIVE = 'HugEuLabel.config.active';

    private const CONFIG_MAIL_MODE = 'HugEuLabel.config.mailMode';

    public function __construct(
        private readonly LabelProvider $labelProvider,
        private readonly SystemConfigService $systemConfigService,
        private readonly OrderConfirmationMailChecker $mailChecker,
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
        // The mail must never fail because of the label — log errors only.
        try {
            $this->attachLabel($event);
        } catch (\Throwable $exception) {
            $this->logger?->error(
                'HugEuLabel: attaching the warranty label failed; the mail is sent without it.',
                ['exception' => $exception],
            );
        }
    }

    private function attachLabel(FlowSendMailActionEvent $event): void
    {
        $salesChannelId = $event->getStorableFlow()->getData(MailAware::SALES_CHANNEL_ID);
        $salesChannelId = \is_string($salesChannelId) && $salesChannelId !== '' ? $salesChannelId : null;

        if (!$this->systemConfigService->getBool(self::CONFIG_ACTIVE, $salesChannelId)) {
            return;
        }

        if ($this->systemConfigService->getString(self::CONFIG_MAIL_MODE, $salesChannelId) !== self::MAIL_MODE_PDF_ATTACHMENT) {
            return;
        }

        if (!$this->mailChecker->isOrderConfirmation($event)) {
            return;
        }

        $pdfPath = $this->labelProvider->getAbsolutePdfPath($event->getContext());

        if ($pdfPath === null) {
            $this->logger?->warning(
                'HugEuLabel: no warranty label PDF available; the order confirmation is sent without the attachment.',
            );

            return;
        }

        $content = @file_get_contents($pdfPath);

        if ($content === false) {
            $this->logger?->warning(
                'HugEuLabel: the warranty label PDF could not be read; the order confirmation is sent without the attachment.',
                ['path' => $pdfPath],
            );

            return;
        }

        $dataBag = $event->getDataBag();

        $attachments = $dataBag->get('binAttachments');
        if ($attachments instanceof DataBag) {
            $attachments = $attachments->all();
        } elseif (!\is_array($attachments)) {
            $attachments = [];
        }

        $attachments[] = [
            'content' => $content,
            'fileName' => self::ATTACHMENT_FILE_NAME,
            'mimeType' => 'application/pdf',
        ];

        $dataBag->set('binAttachments', $attachments);
    }
}
