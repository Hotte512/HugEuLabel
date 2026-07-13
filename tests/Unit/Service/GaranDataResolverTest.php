<?php

declare(strict_types=1);

namespace Hug\EuLabel\Tests\Unit\Service;

use Hug\EuLabel\Service\GaranDataResolver;
use Hug\EuLabel\Service\GaranMediaUrlResolver;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerCollection;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

final class GaranDataResolverTest extends TestCase
{
    private Context $context;

    protected function setUp(): void
    {
        $this->context = new Context(new SystemSource());
    }

    public function testResolvesManufacturerDefaults(): void
    {
        $product = $this->createProduct(
            manufacturer: $this->createManufacturer(['hug_garan_active' => true, 'hug_garan_duration_years' => 5, 'hug_garan_conditions_url' => 'https://example.com/garantie']),
        );

        $data = $this->createResolver()->resolve($product, $this->context);

        self::assertNotNull($data);
        self::assertSame(5, $data->durationYears);
        self::assertSame('https://example.com/garantie', $data->conditionsUrl);
        self::assertSame('Holzwerk GmbH', $data->manufacturerName);
        self::assertNull($data->conditionsMediaId);
    }

    public function testProductOverrideWinsOverManufacturerDefault(): void
    {
        $product = $this->createProduct(
            customFields: ['hug_garan_product_duration_years' => 10],
            manufacturer: $this->createManufacturer(['hug_garan_active' => true, 'hug_garan_duration_years' => 5, 'hug_garan_conditions_url' => 'https://example.com/garantie']),
        );

        $data = $this->createResolver()->resolve($product, $this->context);

        self::assertSame(10, $data?->durationYears);
    }

    public function testDisabledProductReturnsNullWithoutWarning(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('warning');

        $product = $this->createProduct(
            customFields: ['hug_garan_disabled' => true],
            manufacturer: $this->createManufacturer(['hug_garan_active' => true, 'hug_garan_duration_years' => 5, 'hug_garan_conditions_url' => 'https://example.com/garantie']),
        );

        self::assertNull($this->createResolver(logger: $logger)->resolve($product, $this->context));
    }

    public function testDurationOfTwoYearsOrLessNeedsNoLabel(): void
    {
        // Garantie ≤ 2 Jahre ist legitim und braucht kein GARAN-Label — keine Warnung.
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('warning');

        $product = $this->createProduct(
            manufacturer: $this->createManufacturer(['hug_garan_active' => true, 'hug_garan_duration_years' => 2, 'hug_garan_conditions_url' => 'https://example.com/garantie']),
        );

        self::assertNull($this->createResolver(logger: $logger)->resolve($product, $this->context));
    }

    public function testDurationWithoutConditionsLogsWarning(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');

        $product = $this->createProduct(
            manufacturer: $this->createManufacturer(['hug_garan_active' => true, 'hug_garan_duration_years' => 5]),
        );

        self::assertNull($this->createResolver(logger: $logger)->resolve($product, $this->context));
    }

    public function testInactiveManufacturerWithCompleteProductOverrides(): void
    {
        $product = $this->createProduct(
            customFields: ['hug_garan_product_duration_years' => 4, 'hug_garan_product_conditions_url' => 'https://example.com/eigene-garantie'],
            manufacturer: $this->createManufacturer([]),
        );

        $data = $this->createResolver()->resolve($product, $this->context);

        self::assertNotNull($data);
        self::assertSame(4, $data->durationYears);
        self::assertSame('https://example.com/eigene-garantie', $data->conditionsUrl);
    }

    public function testInactiveManufacturerWithPartialProductDataLogsWarning(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');

        $product = $this->createProduct(
            customFields: ['hug_garan_product_duration_years' => 4],
            manufacturer: $this->createManufacturer([]),
        );

        self::assertNull($this->createResolver(logger: $logger)->resolve($product, $this->context));
    }

    public function testMediaFallbackResolvesUrlAndKeepsMediaId(): void
    {
        $mediaId = Uuid::randomHex();
        $product = $this->createProduct(
            manufacturer: $this->createManufacturer(['hug_garan_active' => true, 'hug_garan_duration_years' => 5, 'hug_garan_conditions_media' => $mediaId]),
        );

        $data = $this->createResolver(mediaUrl: 'https://shop.example/media/garantie.pdf')->resolve($product, $this->context);

        self::assertNotNull($data);
        self::assertSame('https://shop.example/media/garantie.pdf', $data->conditionsUrl);
        self::assertSame($mediaId, $data->conditionsMediaId);
    }

    public function testUnresolvableMediaLogsWarning(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');

        $product = $this->createProduct(
            manufacturer: $this->createManufacturer(['hug_garan_active' => true, 'hug_garan_duration_years' => 5, 'hug_garan_conditions_media' => Uuid::randomHex()]),
        );

        self::assertNull($this->createResolver(logger: $logger, mediaUrl: null)->resolve($product, $this->context));
    }

    public function testUntouchedProductReturnsNullWithoutWarning(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('warning');

        $product = $this->createProduct(manufacturer: $this->createManufacturer([]));

        self::assertNull($this->createResolver(logger: $logger)->resolve($product, $this->context));
    }

    public function testMissingManufacturerEntityMakesBrandUnavailable(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');

        $product = $this->createProduct(
            customFields: ['hug_garan_product_duration_years' => 4, 'hug_garan_product_conditions_url' => 'https://example.com/g'],
            manufacturer: null,
        );

        self::assertNull($this->createResolver(logger: $logger)->resolve($product, $this->context));
    }

    public function testLazyLoadsManufacturerWhenAssociationIsMissing(): void
    {
        // Listing-Produkte kommen oft ohne manufacturer-Association — der
        // Resolver lädt den Hersteller dann über das Repository nach.
        $manufacturer = $this->createManufacturer(['hug_garan_active' => true, 'hug_garan_duration_years' => 5, 'hug_garan_conditions_url' => 'https://example.com/garantie']);

        $product = new ProductEntity();
        $product->setId(Uuid::randomHex());
        $product->setUniqueIdentifier($product->getId());
        $product->setCustomFields([]);
        $product->setManufacturerId($manufacturer->getId());

        /** @var StaticEntityRepository<ProductManufacturerCollection> $manufacturerRepository */
        $manufacturerRepository = new StaticEntityRepository([new ProductManufacturerCollection([$manufacturer])]);

        $data = $this->createResolver(manufacturerRepository: $manufacturerRepository)->resolve($product, $this->context);

        self::assertNotNull($data);
        self::assertSame(5, $data->durationYears);
        self::assertSame('Holzwerk GmbH', $data->manufacturerName);
    }

    /**
     * @param EntityRepository<ProductManufacturerCollection>|null $manufacturerRepository
     */
    private function createResolver(?LoggerInterface $logger = null, ?string $mediaUrl = 'https://shop.example/media/fallback.pdf', ?EntityRepository $manufacturerRepository = null): GaranDataResolver
    {
        $urlResolver = new class($mediaUrl) implements GaranMediaUrlResolver {
            public function __construct(private readonly ?string $url)
            {
            }

            public function getUrl(string $mediaId, \Shopware\Core\Framework\Context $context): ?string
            {
                return $this->url;
            }
        };

        return new GaranDataResolver($urlResolver, $logger, $manufacturerRepository);
    }

    /**
     * @param array<string, mixed> $customFields
     */
    private function createProduct(array $customFields = [], ?ProductManufacturerEntity $manufacturer = null): ProductEntity
    {
        $product = new ProductEntity();
        $product->setId(Uuid::randomHex());
        $product->setUniqueIdentifier($product->getId());
        $product->setCustomFields($customFields);

        if ($manufacturer !== null) {
            $product->setManufacturer($manufacturer);
            $product->setManufacturerId($manufacturer->getId());
        }

        return $product;
    }

    /**
     * @param array<string, mixed> $customFields
     */
    private function createManufacturer(array $customFields): ProductManufacturerEntity
    {
        $manufacturer = new ProductManufacturerEntity();
        $manufacturer->setId(Uuid::randomHex());
        $manufacturer->setUniqueIdentifier($manufacturer->getId());
        $manufacturer->setName('Holzwerk GmbH');
        $manufacturer->setCustomFields($customFields);

        return $manufacturer;
    }
}
