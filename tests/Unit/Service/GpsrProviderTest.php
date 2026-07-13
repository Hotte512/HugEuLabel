<?php

declare(strict_types=1);

namespace Hug\EuLabel\Tests\Unit\Service;

use Hug\EuLabel\Lifecycle\GpsrCustomFieldSetInstaller;
use Hug\EuLabel\Service\GpsrProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;

final class GpsrProviderTest extends TestCase
{
    private const SALES_CHANNEL_ID = 'sales-channel-id';

    public function testReturnsNullWhenGpsrIsInactive(): void
    {
        $provider = $this->createProvider([
            'HugEuLabel.config.gpsrActive' => false,
            'HugEuLabel.config.gpsrFallbackText' => 'Fallback GmbH',
        ]);

        $manufacturer = $this->createManufacturer([
            GpsrCustomFieldSetInstaller::FIELD_MANUFACTURER_INFO => 'Hersteller GmbH',
        ]);

        self::assertNull($provider->resolve($manufacturer, $this->createSalesChannelContext()));
    }

    public function testReturnsManufacturerInfoWhenPresent(): void
    {
        $provider = $this->createProvider([
            'HugEuLabel.config.gpsrActive' => true,
            'HugEuLabel.config.gpsrFallbackText' => 'Fallback GmbH',
        ]);

        $manufacturer = $this->createManufacturer([
            GpsrCustomFieldSetInstaller::FIELD_MANUFACTURER_INFO => "Hersteller GmbH\nMusterstraße 1\ninfo@hersteller.example",
        ]);

        $info = $provider->resolve($manufacturer, $this->createSalesChannelContext());

        self::assertNotNull($info);
        self::assertSame("Hersteller GmbH\nMusterstraße 1\ninfo@hersteller.example", $info->text);
        self::assertNull($info->responsiblePerson);
        self::assertFalse($info->isFallback);
    }

    public function testIncludesResponsiblePersonWhenFilled(): void
    {
        $provider = $this->createProvider([
            'HugEuLabel.config.gpsrActive' => true,
        ]);

        $manufacturer = $this->createManufacturer([
            GpsrCustomFieldSetInstaller::FIELD_MANUFACTURER_INFO => 'Overseas Ltd.',
            GpsrCustomFieldSetInstaller::FIELD_RESPONSIBLE_PERSON => "EU-Vertretung GmbH\nBrüsseler Platz 2",
        ]);

        $info = $provider->resolve($manufacturer, $this->createSalesChannelContext());

        self::assertNotNull($info);
        self::assertSame("EU-Vertretung GmbH\nBrüsseler Platz 2", $info->responsiblePerson);
    }

    public function testResponsiblePersonAloneDoesNotCountAsManufacturerData(): void
    {
        // Nur die verantwortliche Person ohne Herstellerangaben ist keine
        // vollständige GPSR-Angabe — es greift der Fallback.
        $provider = $this->createProvider([
            'HugEuLabel.config.gpsrActive' => true,
            'HugEuLabel.config.gpsrFallbackText' => 'Fallback GmbH',
        ]);

        $manufacturer = $this->createManufacturer([
            GpsrCustomFieldSetInstaller::FIELD_RESPONSIBLE_PERSON => 'EU-Vertretung GmbH',
        ]);

        $info = $provider->resolve($manufacturer, $this->createSalesChannelContext());

        self::assertNotNull($info);
        self::assertSame('Fallback GmbH', $info->text);
        self::assertNull($info->responsiblePerson);
        self::assertTrue($info->isFallback);
    }

    public function testFallsBackWhenManufacturerHasNoGpsrData(): void
    {
        $provider = $this->createProvider([
            'HugEuLabel.config.gpsrActive' => true,
            'HugEuLabel.config.gpsrFallbackText' => "Eigenfirma GmbH\nEigene Straße 3",
        ]);

        $info = $provider->resolve($this->createManufacturer([]), $this->createSalesChannelContext());

        self::assertNotNull($info);
        self::assertSame("Eigenfirma GmbH\nEigene Straße 3", $info->text);
        self::assertTrue($info->isFallback);
    }

    public function testFallsBackWhenProductHasNoManufacturer(): void
    {
        $provider = $this->createProvider([
            'HugEuLabel.config.gpsrActive' => true,
            'HugEuLabel.config.gpsrFallbackText' => 'Fallback GmbH',
        ]);

        $info = $provider->resolve(null, $this->createSalesChannelContext());

        self::assertNotNull($info);
        self::assertSame('Fallback GmbH', $info->text);
        self::assertTrue($info->isFallback);
    }

    public function testReturnsNullWhenNoDataAndFallbackIsEmpty(): void
    {
        $provider = $this->createProvider([
            'HugEuLabel.config.gpsrActive' => true,
            'HugEuLabel.config.gpsrFallbackText' => "   \n  ",
        ]);

        self::assertNull($provider->resolve(null, $this->createSalesChannelContext()));
        self::assertNull($provider->resolve($this->createManufacturer([]), $this->createSalesChannelContext()));
    }

    public function testWhitespaceOnlyManufacturerInfoCountsAsMissing(): void
    {
        $provider = $this->createProvider([
            'HugEuLabel.config.gpsrActive' => true,
            'HugEuLabel.config.gpsrFallbackText' => 'Fallback GmbH',
        ]);

        $manufacturer = $this->createManufacturer([
            GpsrCustomFieldSetInstaller::FIELD_MANUFACTURER_INFO => "  \n\t ",
        ]);

        $info = $provider->resolve($manufacturer, $this->createSalesChannelContext());

        self::assertNotNull($info);
        self::assertTrue($info->isFallback);
    }

    public function testManufacturerInfoIsTrimmed(): void
    {
        $provider = $this->createProvider([
            'HugEuLabel.config.gpsrActive' => true,
        ]);

        $manufacturer = $this->createManufacturer([
            GpsrCustomFieldSetInstaller::FIELD_MANUFACTURER_INFO => "  Hersteller GmbH  \n",
        ]);

        $info = $provider->resolve($manufacturer, $this->createSalesChannelContext());

        self::assertNotNull($info);
        self::assertSame('Hersteller GmbH', $info->text);
    }

    public function testReadsConfigPerSalesChannel(): void
    {
        // Global inaktiv, für den Sales Channel aktiv — der Kanal-Wert gewinnt.
        $provider = $this->createProvider([
            'HugEuLabel.config.gpsrActive' => false,
            self::SALES_CHANNEL_ID => [
                'HugEuLabel.config.gpsrActive' => true,
                'HugEuLabel.config.gpsrFallbackText' => 'Kanal-Fallback',
            ],
        ]);

        $info = $provider->resolve(null, $this->createSalesChannelContext());

        self::assertNotNull($info);
        self::assertSame('Kanal-Fallback', $info->text);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createProvider(array $config): GpsrProvider
    {
        return new GpsrProvider(new StaticSystemConfigService($config));
    }

    /**
     * @param array<string, string> $customFields
     */
    private function createManufacturer(array $customFields): ProductManufacturerEntity
    {
        $manufacturer = new ProductManufacturerEntity();
        $manufacturer->setTranslated(['customFields' => $customFields]);

        return $manufacturer;
    }

    private function createSalesChannelContext(): SalesChannelContext
    {
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn(self::SALES_CHANNEL_ID);

        return $context;
    }
}
