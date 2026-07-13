<?php

declare(strict_types=1);

namespace Hug\EuLabel\Tests\Unit\Subscriber;

use Hug\EuLabel\Service\LabelProvider;
use Hug\EuLabel\Service\OrderConfirmationMailChecker;
use Hug\EuLabel\Subscriber\MailAttachmentSubscriber;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Events\FlowSendMailActionEvent;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeCollection;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeEntity;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

final class MailAttachmentSubscriberTest extends TestCase
{
    private const PDF_CONTENT = '%PDF-1.4 dummy label';

    private string $labelsDir;

    private string $salesChannelId;

    protected function setUp(): void
    {
        $this->labelsDir = sys_get_temp_dir() . '/hug-eu-label-mail-test-' . bin2hex(random_bytes(6));
        mkdir($this->labelsDir . '/de-DE', 0777, true);
        file_put_contents($this->labelsDir . '/de-DE/gewaehrleistungslabel.pdf', self::PDF_CONTENT);

        $this->salesChannelId = Uuid::randomHex();
    }

    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->labelsDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $file) {
            \assert($file instanceof \SplFileInfo);
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($this->labelsDir);
    }

    public function testAttachesPdfToOrderConfirmation(): void
    {
        $event = $this->createEvent();

        $subscriber = new MailAttachmentSubscriber(
            $this->createLabelProvider(),
            $this->createSystemConfig(true, 'pdf_attachment'),
            new OrderConfirmationMailChecker($this->createTypeRepository('order_confirmation_mail')),
        );

        $subscriber->onFlowSendMail($event);

        $attachments = $event->getDataBag()->all()['binAttachments'] ?? null;

        self::assertSame([
            [
                'content' => self::PDF_CONTENT,
                'fileName' => 'EU-Gewaehrleistungslabel.pdf',
                'mimeType' => 'application/pdf',
            ],
        ], $attachments);
    }

    public function testKeepsExistingAttachments(): void
    {
        $existing = [
            'content' => 'other document',
            'fileName' => 'invoice.pdf',
            'mimeType' => 'application/pdf',
        ];

        $event = $this->createEvent();
        $event->getDataBag()->set('binAttachments', [$existing]);

        $subscriber = new MailAttachmentSubscriber(
            $this->createLabelProvider(),
            $this->createSystemConfig(true, 'pdf_attachment'),
            new OrderConfirmationMailChecker($this->createTypeRepository('order_confirmation_mail')),
        );

        $subscriber->onFlowSendMail($event);

        $attachments = $event->getDataBag()->all()['binAttachments'];

        self::assertIsArray($attachments);
        self::assertCount(2, $attachments);
        self::assertSame($existing, $attachments[0]);
        self::assertIsArray($attachments[1]);
        self::assertSame('EU-Gewaehrleistungslabel.pdf', $attachments[1]['fileName']);
    }

    public function testIgnoresOtherMailTemplateTypes(): void
    {
        $event = $this->createEvent();

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');
        $logger->expects(self::never())->method('warning');

        $subscriber = new MailAttachmentSubscriber(
            $this->createLabelProvider(),
            $this->createSystemConfig(true, 'pdf_attachment'),
            new OrderConfirmationMailChecker($this->createTypeRepository('customer_register')),
            $logger,
        );

        $subscriber->onFlowSendMail($event);

        self::assertFalse($event->getDataBag()->has('binAttachments'));
    }

    public function testDoesNothingWhenMailModeIsNotPdfAttachment(): void
    {
        $event = $this->createEvent();

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');

        // Leeres Repository: ein search()-Aufruf würde fehlschlagen und als
        // Fehler geloggt — so ist sichergestellt, dass bei mailMode !==
        // pdf_attachment gar keine Typ-Abfrage stattfindet.
        /** @var StaticEntityRepository<MailTemplateTypeCollection> $typeRepository */
        $typeRepository = new StaticEntityRepository([]);

        $subscriber = new MailAttachmentSubscriber(
            $this->createLabelProvider(),
            $this->createSystemConfig(true, 'disabled'),
            new OrderConfirmationMailChecker($typeRepository),
            $logger,
        );

        $subscriber->onFlowSendMail($event);

        self::assertFalse($event->getDataBag()->has('binAttachments'));
    }

    public function testDoesNothingWhenPluginIsInactive(): void
    {
        $event = $this->createEvent();

        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig
            ->expects(self::once())
            ->method('getBool')
            ->with('HugEuLabel.config.active', $this->salesChannelId)
            ->willReturn(false);
        $systemConfig->expects(self::never())->method('getString');

        /** @var StaticEntityRepository<MailTemplateTypeCollection> $typeRepository */
        $typeRepository = new StaticEntityRepository([]);

        $subscriber = new MailAttachmentSubscriber(
            $this->createLabelProvider(),
            $systemConfig,
            new OrderConfirmationMailChecker($typeRepository),
        );

        $subscriber->onFlowSendMail($event);

        self::assertFalse($event->getDataBag()->has('binAttachments'));
    }

    public function testMissingPdfLogsWarningAndDoesNotThrow(): void
    {
        $emptyDir = $this->labelsDir . '/empty';
        mkdir($emptyDir);

        $event = $this->createEvent();

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');
        $logger->expects(self::never())->method('error');

        $subscriber = new MailAttachmentSubscriber(
            $this->createLabelProvider($emptyDir),
            $this->createSystemConfig(true, 'pdf_attachment'),
            new OrderConfirmationMailChecker($this->createTypeRepository('order_confirmation_mail')),
            $logger,
        );

        $subscriber->onFlowSendMail($event);

        self::assertFalse($event->getDataBag()->has('binAttachments'));
    }

    public function testNeverThrowsWhenTypeLookupFails(): void
    {
        $event = $this->createEvent();

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        /** @var StaticEntityRepository<MailTemplateTypeCollection> $typeRepository */
        $typeRepository = new StaticEntityRepository([
            static fn () => throw new \RuntimeException('database unavailable'),
        ]);

        $subscriber = new MailAttachmentSubscriber(
            $this->createLabelProvider(),
            $this->createSystemConfig(true, 'pdf_attachment'),
            new OrderConfirmationMailChecker($typeRepository),
            $logger,
        );

        $subscriber->onFlowSendMail($event);

        self::assertFalse($event->getDataBag()->has('binAttachments'));
    }

    private function createEvent(?DataBag $dataBag = null): FlowSendMailActionEvent
    {
        $context = new Context(new SystemSource());

        $mailTemplate = new MailTemplateEntity();
        $mailTemplate->setId(Uuid::randomHex());
        $mailTemplate->setUniqueIdentifier($mailTemplate->getId());
        $mailTemplate->setMailTemplateTypeId(Uuid::randomHex());

        $flow = new StorableFlow('checkout.order.placed', $context, [], [
            MailAware::SALES_CHANNEL_ID => $this->salesChannelId,
        ]);

        return new FlowSendMailActionEvent($dataBag ?? new DataBag(), $mailTemplate, $flow);
    }

    private function createLabelProvider(?string $labelsDir = null): LabelProvider
    {
        // Leere Sprach-Suche → LabelProvider fällt auf de-DE zurück.
        /** @var StaticEntityRepository<LanguageCollection> $languageRepository */
        $languageRepository = new StaticEntityRepository([[]]);

        return new LabelProvider($languageRepository, null, $labelsDir ?? $this->labelsDir);
    }

    private function createSystemConfig(bool $active, string $mailMode): SystemConfigService
    {
        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig
            ->method('getBool')
            ->with('HugEuLabel.config.active', $this->salesChannelId)
            ->willReturn($active);
        $systemConfig
            ->method('getString')
            ->with('HugEuLabel.config.mailMode', $this->salesChannelId)
            ->willReturn($mailMode);

        return $systemConfig;
    }

    /**
     * @return StaticEntityRepository<MailTemplateTypeCollection>
     */
    private function createTypeRepository(string $technicalName): StaticEntityRepository
    {
        $type = new MailTemplateTypeEntity();
        $type->setId(Uuid::randomHex());
        $type->setUniqueIdentifier($type->getId());
        $type->setTechnicalName($technicalName);

        /** @var StaticEntityRepository<MailTemplateTypeCollection> $repository */
        $repository = new StaticEntityRepository([[$type]]);

        return $repository;
    }
}
