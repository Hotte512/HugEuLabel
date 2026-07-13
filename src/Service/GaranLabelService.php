<?php

declare(strict_types=1);

namespace Hug\EuLabel\Service;

use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Liefert das befüllte GARAN-Label eines Produkts (Resolver → Renderer,
 * Ergebnis gecacht). Gemeinsame Logik für die Twig-Extension und den
 * Label-Controller. Der Cache-Key ist inhaltsadressiert — Datenänderungen
 * erzeugen sofort einen neuen Key, die TTL räumt nur Altlasten weg.
 */
class GaranLabelService
{
    private const CACHE_TTL = 3600;

    public function __construct(
        private readonly GaranDataResolver $resolver,
        private readonly GaranLabelRenderer $renderer,
        private readonly SystemConfigService $systemConfig,
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * Leerer String = kein Label (keine Garantie, unvollständige Daten oder
     * Render-Fehler — Details im Log-Channel hug_eu_label).
     */
    public function render(?ProductEntity $product, string $variant, SalesChannelContext $context): string
    {
        if ($product === null) {
            return '';
        }

        $data = $this->resolver->resolve($product, $context);
        if ($data === null) {
            return '';
        }

        $modelId = $this->resolveModelId($product, $context->getSalesChannelId());

        $cacheKey = 'hug-garan-' . md5(implode('|', [
            $product->getId(),
            $variant,
            $context->getLanguageId(),
            $modelId,
            $data->durationYears,
            $data->conditionsUrl,
            $data->manufacturerName,
        ]));

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($data, $modelId, $variant): string {
            $item->expiresAfter(self::CACHE_TTL);

            return $this->renderer->render($data, $modelId, $variant);
        });
    }

    public function getConditionsUrl(?ProductEntity $product, SalesChannelContext $context): ?string
    {
        if ($product === null) {
            return null;
        }

        return $this->resolver->resolve($product, $context)?->conditionsUrl;
    }

    private function resolveModelId(ProductEntity $product, string $salesChannelId): string
    {
        $source = $this->systemConfig->getString('HugEuLabel.config.garanModelIdSource', $salesChannelId);

        $value = match ($source) {
            'manufacturer_number' => $product->getManufacturerNumber(),
            'ean' => $product->getEan(),
            default => $product->getProductNumber(),
        };

        if ($value === null || trim($value) === '') {
            $value = $product->getProductNumber();
        }

        return $value;
    }
}
