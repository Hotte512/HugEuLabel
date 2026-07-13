<?php

declare(strict_types=1);

namespace Hug\EuLabel\Tests\Unit\Service;

use Hug\EuLabel\Service\LabelProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

final class LabelProviderTest extends TestCase
{
    private string $labelsDir;

    protected function setUp(): void
    {
        $this->labelsDir = sys_get_temp_dir() . '/hug-eu-label-test-' . bin2hex(random_bytes(6));
        mkdir($this->labelsDir . '/de-DE', 0777, true);
        mkdir($this->labelsDir . '/en-GB', 0777, true);
        file_put_contents($this->labelsDir . '/de-DE/gewaehrleistungslabel.svg', '<svg/>');
        file_put_contents($this->labelsDir . '/de-DE/gewaehrleistungslabel.pdf', '%PDF-1.4 dummy');
        file_put_contents($this->labelsDir . '/en-GB/gewaehrleistungslabel.svg', '<svg/>');
        // Bewusst kein en-GB-PDF: deckt den Fallback auf de-DE ab.
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

    public function testReturnsLocaleSpecificSvgPath(): void
    {
        [$provider, $context] = $this->createProviderForLocale('en-GB');

        self::assertSame(
            'labels/en-GB/gewaehrleistungslabel.svg',
            $provider->getLabelPath(LabelProvider::FORMAT_SVG, $context),
        );
    }

    public function testLocaleIsCachedPerLanguage(): void
    {
        // Das Repository liefert genau EIN Suchergebnis; ein zweiter search()-
        // Aufruf würde im StaticEntityRepository fehlschlagen.
        [$provider, $context] = $this->createProviderForLocale('en-GB');

        $provider->getLabelPath(LabelProvider::FORMAT_SVG, $context);

        self::assertSame(
            'labels/en-GB/gewaehrleistungslabel.svg',
            $provider->getLabelPath(LabelProvider::FORMAT_SVG, $context),
        );
    }

    public function testPdfFallsBackToDefaultLocaleWhenFileMissing(): void
    {
        [$provider, $context] = $this->createProviderForLocale('en-GB');

        self::assertSame(
            'labels/de-DE/gewaehrleistungslabel.pdf',
            $provider->getLabelPath(LabelProvider::FORMAT_PDF, $context),
        );
    }

    public function testFallsBackToDefaultLocaleWhenLocaleFolderMissing(): void
    {
        [$provider, $context] = $this->createProviderForLocale('fr-FR');

        self::assertSame(
            'labels/de-DE/gewaehrleistungslabel.svg',
            $provider->getLabelPath(LabelProvider::FORMAT_SVG, $context),
        );
    }

    public function testReturnsNullWhenNoFileExists(): void
    {
        $emptyDir = $this->labelsDir . '/empty';
        mkdir($emptyDir);

        [$provider, $context] = $this->createProviderForLocale('de-DE', $emptyDir);

        self::assertNull($provider->getLabelPath(LabelProvider::FORMAT_SVG, $context));
        self::assertNull($provider->getAbsolutePdfPath($context));
    }

    public function testGetAbsolutePdfPathReturnsExistingFilesystemPath(): void
    {
        [$provider, $context] = $this->createProviderForLocale('de-DE');

        $path = $provider->getAbsolutePdfPath($context);

        self::assertSame($this->labelsDir . '/de-DE/gewaehrleistungslabel.pdf', $path);
        self::assertFileExists($path);
    }

    public function testThrowsOnUnsupportedFormat(): void
    {
        [$provider, $context] = $this->createProviderForLocale('de-DE');

        $this->expectException(\InvalidArgumentException::class);

        $provider->getLabelPath('png', $context);
    }

    public function testFallsBackAndLogsWhenLocaleLookupFails(): void
    {
        /** @var StaticEntityRepository<LanguageCollection> $repository */
        $repository = new StaticEntityRepository([
            static fn () => throw new \RuntimeException('database unavailable'),
        ]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');

        $provider = new LabelProvider($repository, $logger, $this->labelsDir);
        $context = new Context(new SystemSource());

        self::assertSame(
            'labels/de-DE/gewaehrleistungslabel.svg',
            $provider->getLabelPath(LabelProvider::FORMAT_SVG, $context),
        );
    }

    public function testFallsBackWhenLanguageIsUnknown(): void
    {
        /** @var StaticEntityRepository<LanguageCollection> $repository */
        $repository = new StaticEntityRepository([[]]);
        $provider = new LabelProvider($repository, null, $this->labelsDir);
        $context = new Context(new SystemSource());

        self::assertSame(
            'labels/de-DE/gewaehrleistungslabel.svg',
            $provider->getLabelPath(LabelProvider::FORMAT_SVG, $context),
        );
    }

    public function testAcceptsSalesChannelContext(): void
    {
        [$provider, $context] = $this->createProviderForLocale('en-GB');

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getContext')->willReturn($context);

        self::assertSame(
            'labels/en-GB/gewaehrleistungslabel.svg',
            $provider->getLabelPath(LabelProvider::FORMAT_SVG, $salesChannelContext),
        );
    }

    /**
     * @return array{0: LabelProvider, 1: Context}
     */
    private function createProviderForLocale(string $localeCode, ?string $labelsDir = null): array
    {
        $languageId = Uuid::randomHex();

        $locale = new LocaleEntity();
        $locale->setId(Uuid::randomHex());
        $locale->setUniqueIdentifier($locale->getId());
        $locale->setCode($localeCode);

        $language = new LanguageEntity();
        $language->setId($languageId);
        $language->setUniqueIdentifier($languageId);
        $language->setLocale($locale);

        /** @var StaticEntityRepository<LanguageCollection> $repository */
        $repository = new StaticEntityRepository([[$language]]);

        $provider = new LabelProvider($repository, null, $labelsDir ?? $this->labelsDir);
        $context = new Context(new SystemSource(), [], Defaults::CURRENCY, [$languageId]);

        return [$provider, $context];
    }
}
