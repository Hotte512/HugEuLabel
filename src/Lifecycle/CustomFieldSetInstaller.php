<?php

declare(strict_types=1);

namespace Hug\EuLabel\Lifecycle;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\CustomFieldTypes;

/**
 * Legt die GARAN-Custom-Field-Sets idempotent an (Hersteller-Defaults +
 * Produkt-Overrides). Bestehende Sets werden über ihren Namen wiedererkannt
 * und aktualisiert, damit install() und update() denselben Code nutzen können.
 */
class CustomFieldSetInstaller
{
    public const SET_MANUFACTURER = 'hug_garan_manufacturer';
    public const SET_PRODUCT = 'hug_garan_product';

    /**
     * @param EntityRepository<\Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetCollection> $customFieldSetRepository
     */
    public function __construct(
        private readonly EntityRepository $customFieldSetRepository,
    ) {
    }

    public function install(Context $context): void
    {
        $existing = $this->findExistingSetIds($context);

        $this->customFieldSetRepository->upsert([
            $this->manufacturerSet($existing[self::SET_MANUFACTURER] ?? Uuid::randomHex()),
            $this->productSet($existing[self::SET_PRODUCT] ?? Uuid::randomHex()),
        ], $context);
    }

    /**
     * @return array<string, string> name => id
     */
    private function findExistingSetIds(Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('name', [self::SET_MANUFACTURER, self::SET_PRODUCT]));

        $ids = [];
        foreach ($this->customFieldSetRepository->search($criteria, $context)->getEntities() as $set) {
            $ids[$set->getName()] = $set->getId();
        }

        return $ids;
    }

    /**
     * @return array<string, mixed>
     */
    private function manufacturerSet(string $id): array
    {
        return [
            'id' => $id,
            'name' => self::SET_MANUFACTURER,
            'config' => [
                'label' => [
                    'de-DE' => 'GARAN-Garantie (Hersteller-Standard)',
                    'en-GB' => 'GARAN guarantee (manufacturer defaults)',
                ],
            ],
            'customFields' => [
                $this->boolField($id, 'hug_garan_active', 1, 'Haltbarkeitsgarantie aktiv', 'Durability guarantee active'),
                $this->intField($id, 'hug_garan_duration_years', 2),
                $this->urlField($id, 'hug_garan_conditions_url', 3),
                $this->mediaField($id, 'hug_garan_conditions_media', 4),
            ],
            'relations' => [
                ['id' => $this->relationId($id, 'product_manufacturer'), 'entityName' => 'product_manufacturer'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function productSet(string $id): array
    {
        return [
            'id' => $id,
            'name' => self::SET_PRODUCT,
            'config' => [
                'label' => [
                    'de-DE' => 'GARAN-Garantie (Produkt-Abweichungen)',
                    'en-GB' => 'GARAN guarantee (product overrides)',
                ],
            ],
            'customFields' => [
                // Custom-Field-Namen sind global eindeutig, daher eigenes Präfix.
                $this->boolField($id, 'hug_garan_disabled', 1, 'Garantielabel für dieses Produkt deaktivieren', 'Disable guarantee label for this product'),
                $this->intField($id, 'hug_garan_product_duration_years', 2),
                $this->urlField($id, 'hug_garan_product_conditions_url', 3),
                $this->mediaField($id, 'hug_garan_product_conditions_media', 4),
            ],
            'relations' => [
                ['id' => $this->relationId($id, 'product'), 'entityName' => 'product'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function boolField(string $setId, string $name, int $position, string $labelDe, string $labelEn): array
    {
        return [
            'id' => $this->fieldId($setId, $name),
            'name' => $name,
            'type' => CustomFieldTypes::SWITCH,
            'config' => [
                'componentName' => 'sw-field',
                'type' => 'switch',
                'customFieldType' => 'switch',
                'customFieldPosition' => $position,
                'label' => ['de-DE' => $labelDe, 'en-GB' => $labelEn],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function intField(string $setId, string $name, int $position): array
    {
        return [
            'id' => $this->fieldId($setId, $name),
            'name' => $name,
            'type' => CustomFieldTypes::INT,
            'config' => [
                'componentName' => 'sw-field',
                'type' => 'number',
                'numberType' => 'int',
                'customFieldType' => 'number',
                'customFieldPosition' => $position,
                'min' => 0,
                'label' => ['de-DE' => 'Garantiedauer (Jahre)', 'en-GB' => 'Guarantee duration (years)'],
                'helpText' => [
                    'de-DE' => 'Das GARAN-Label erscheint nur bei mehr als 2 Jahren.',
                    'en-GB' => 'The GARAN label only appears for more than 2 years.',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function urlField(string $setId, string $name, int $position): array
    {
        return [
            'id' => $this->fieldId($setId, $name),
            'name' => $name,
            'type' => CustomFieldTypes::TEXT,
            'config' => [
                'componentName' => 'sw-field',
                'type' => 'text',
                'customFieldType' => 'text',
                'customFieldPosition' => $position,
                'placeholder' => ['de-DE' => 'https://…', 'en-GB' => 'https://…'],
                'label' => ['de-DE' => 'Garantiebedingungen (URL)', 'en-GB' => 'Guarantee conditions (URL)'],
                'helpText' => [
                    'de-DE' => 'Ziel von QR-Code und Bedingungs-Link. Alternativ PDF-Datei hinterlegen.',
                    'en-GB' => 'Target of the QR code and conditions link. Alternatively attach a PDF file.',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mediaField(string $setId, string $name, int $position): array
    {
        return [
            'id' => $this->fieldId($setId, $name),
            'name' => $name,
            'type' => CustomFieldTypes::MEDIA,
            'config' => [
                'componentName' => 'sw-media-field',
                'customFieldType' => 'media',
                'customFieldPosition' => $position,
                'label' => ['de-DE' => 'Garantiebedingungen (PDF)', 'en-GB' => 'Guarantee conditions (PDF)'],
                'helpText' => [
                    'de-DE' => 'Fallback zur URL; wird bei entsprechender Konfiguration an die Bestellbestätigung angehängt.',
                    'en-GB' => 'Fallback for the URL; attached to the order confirmation when configured.',
                ],
            ],
        ];
    }

    private function fieldId(string $setId, string $name): string
    {
        // Deterministisch aus Set-Id + Feldname, damit Upserts Felder aktualisieren statt duplizieren.
        return md5($setId . $name);
    }

    private function relationId(string $setId, string $entityName): string
    {
        return md5($setId . 'relation' . $entityName);
    }
}
