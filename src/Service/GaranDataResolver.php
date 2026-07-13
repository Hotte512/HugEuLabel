<?php

declare(strict_types=1);

namespace Hug\EuLabel\Service;

use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerCollection;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Löst die GARAN-Garantiedaten eines Produkts auf: Produkt-Overrides gewinnen
 * über Hersteller-Defaults; ohne aktiven Hersteller zählen nur vollständige
 * Produkt-Angaben. Liefert null, wenn kein Label anzuzeigen ist — bei
 * unvollständig gepflegten Daten zusätzlich mit Warnung im Log, damit der
 * Shop-Betreiber die Unterdrückung bemerkt.
 */
class GaranDataResolver
{
    // Custom-Field-Namen sind in Shopware global eindeutig — die
    // Produkt-Overrides tragen daher ein eigenes Präfix.
    private const FIELD_DISABLED = 'hug_garan_disabled';
    private const FIELD_P_DURATION = 'hug_garan_product_duration_years';
    private const FIELD_P_URL = 'hug_garan_product_conditions_url';
    private const FIELD_P_MEDIA = 'hug_garan_product_conditions_media';
    private const FIELD_ACTIVE = 'hug_garan_active';
    private const FIELD_DURATION = 'hug_garan_duration_years';
    private const FIELD_URL = 'hug_garan_conditions_url';
    private const FIELD_MEDIA = 'hug_garan_conditions_media';

    /** @var array<string, ?ProductManufacturerEntity> */
    private array $manufacturerCache = [];

    /**
     * @param EntityRepository<ProductManufacturerCollection>|null $manufacturerRepository
     */
    public function __construct(
        private readonly GaranMediaUrlResolver $mediaUrlResolver,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?EntityRepository $manufacturerRepository = null,
    ) {
    }

    public function resolve(ProductEntity $product, Context|SalesChannelContext $context): ?GaranData
    {
        $context = $context instanceof SalesChannelContext ? $context->getContext() : $context;

        $productFields = $product->getCustomFields() ?? [];
        if ($productFields[self::FIELD_DISABLED] ?? false) {
            return null;
        }

        // Listing-/Line-Item-Produkte kommen oft ohne manufacturer-Association;
        // dann wird der Hersteller (per Request gecacht) nachgeladen.
        $manufacturer = $product->getManufacturer()
            ?? $this->loadManufacturer($product->getManufacturerId(), $context);
        $manufacturerFields = $manufacturer?->getCustomFields() ?? [];
        $manufacturerActive = (bool) ($manufacturerFields[self::FIELD_ACTIVE] ?? false);

        // Hersteller-Defaults zählen nur bei aktivem Hersteller; Produkt-Werte immer.
        $duration = $this->intOrNull($productFields[self::FIELD_P_DURATION] ?? null)
            ?? ($manufacturerActive ? $this->intOrNull($manufacturerFields[self::FIELD_DURATION] ?? null) : null);
        $url = $this->stringOrNull($productFields[self::FIELD_P_URL] ?? null)
            ?? ($manufacturerActive ? $this->stringOrNull($manufacturerFields[self::FIELD_URL] ?? null) : null);
        $mediaId = $this->stringOrNull($productFields[self::FIELD_P_MEDIA] ?? null)
            ?? ($manufacturerActive ? $this->stringOrNull($manufacturerFields[self::FIELD_MEDIA] ?? null) : null);

        $anythingMaintained = $duration !== null || $url !== null || $mediaId !== null || $manufacturerActive;
        if (!$anythingMaintained) {
            return null;
        }

        if ($duration === null || ($url === null && $mediaId === null)) {
            $this->logger?->warning('HugEuLabel: GARAN data incomplete; label suppressed.', [
                'productId' => $product->getId(),
                'durationYears' => $duration,
                'hasConditions' => $url !== null || $mediaId !== null,
            ]);

            return null;
        }

        // Garantien bis einschließlich 2 Jahre brauchen kein GARAN-Label.
        if ($duration <= 2) {
            return null;
        }

        $translatedName = $manufacturer?->getTranslation('name');
        $manufacturerName = \is_string($translatedName) && $translatedName !== ''
            ? $translatedName
            : $manufacturer?->getName();
        if ($manufacturerName === null || $manufacturerName === '') {
            $this->logger?->warning('HugEuLabel: GARAN label needs a manufacturer name (brand); label suppressed.', [
                'productId' => $product->getId(),
            ]);

            return null;
        }

        $conditionsUrl = $url ?? $this->mediaUrlResolver->getUrl((string) $mediaId, $context);
        if ($conditionsUrl === null || $conditionsUrl === '') {
            $this->logger?->warning('HugEuLabel: GARAN conditions media could not be resolved; label suppressed.', [
                'productId' => $product->getId(),
                'mediaId' => $mediaId,
            ]);

            return null;
        }

        return new GaranData($duration, $conditionsUrl, $manufacturerName, $mediaId);
    }

    private function loadManufacturer(?string $manufacturerId, Context $context): ?ProductManufacturerEntity
    {
        if ($manufacturerId === null || $this->manufacturerRepository === null) {
            return null;
        }

        if (\array_key_exists($manufacturerId, $this->manufacturerCache)) {
            return $this->manufacturerCache[$manufacturerId];
        }

        return $this->manufacturerCache[$manufacturerId] = $this->manufacturerRepository
            ->search(new Criteria([$manufacturerId]), $context)
            ->getEntities()
            ->first();
    }

    private function intOrNull(mixed $value): ?int
    {
        if (\is_int($value)) {
            return $value;
        }

        if (\is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (!\is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }
}
