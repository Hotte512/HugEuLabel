<?php

declare(strict_types=1);

namespace Hug\EuLabel\Service;

use Hug\EuLabel\Lifecycle\GpsrCustomFieldSetInstaller;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Entscheidet, welche GPSR-Pflichtangaben (Art. 19 Verordnung (EU) 2023/988)
 * auf der Produktdetailseite erscheinen:
 *
 *   1. gpsrActive aus → nichts
 *   2. Hersteller hat GPSR-Herstellerangaben → diese (plus verantwortliche
 *      Person in der EU, sofern gepflegt)
 *   3. sonst der globale Fallback-Text (z. B. eigene Firmendaten als
 *      Quasi-Hersteller), sofern gepflegt
 *   4. sonst nichts — bewusst OHNE Logging (ein Log-Eintrag pro PDP-Aufruf
 *      wäre Spam); das Rechtsrisiko ist im README dokumentiert.
 */
class GpsrProvider
{
    private const CONFIG_ACTIVE = 'HugEuLabel.config.gpsrActive';

    private const CONFIG_FALLBACK_TEXT = 'HugEuLabel.config.gpsrFallbackText';

    public function __construct(
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    public function resolve(?ProductManufacturerEntity $manufacturer, SalesChannelContext $context): ?GpsrInfo
    {
        $salesChannelId = $context->getSalesChannelId();

        if (!$this->systemConfigService->getBool(self::CONFIG_ACTIVE, $salesChannelId)) {
            return null;
        }

        $manufacturerInfo = $this->readCustomField($manufacturer, GpsrCustomFieldSetInstaller::FIELD_MANUFACTURER_INFO);

        if ($manufacturerInfo !== null) {
            return new GpsrInfo(
                $manufacturerInfo,
                $this->readCustomField($manufacturer, GpsrCustomFieldSetInstaller::FIELD_RESPONSIBLE_PERSON),
                false,
            );
        }

        $fallback = trim($this->systemConfigService->getString(self::CONFIG_FALLBACK_TEXT, $salesChannelId));

        if ($fallback === '') {
            return null;
        }

        return new GpsrInfo($fallback, null, true);
    }

    private function readCustomField(?ProductManufacturerEntity $manufacturer, string $fieldName): ?string
    {
        if ($manufacturer === null) {
            return null;
        }

        $customFields = $manufacturer->getTranslation('customFields');
        $value = \is_array($customFields) ? ($customFields[$fieldName] ?? null) : null;

        if (!\is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
