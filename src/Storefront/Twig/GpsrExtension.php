<?php

declare(strict_types=1);

namespace Hug\EuLabel\Storefront\Twig;

use Hug\EuLabel\Service\GpsrInfo;
use Hug\EuLabel\Service\GpsrProvider;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Stellt die aufgelösten GPSR-Pflichtangaben den Storefront-Templates bereit:
 *
 *   {% set gpsrInfo = hug_gpsr_info(context, product.manufacturer) %}
 *   {% if gpsrInfo %} … {{ gpsrInfo.text|nl2br }} … {% endif %}
 */
class GpsrExtension extends AbstractExtension
{
    public function __construct(
        private readonly GpsrProvider $gpsrProvider,
    ) {
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('hug_gpsr_info', $this->getGpsrInfo(...)),
        ];
    }

    public function getGpsrInfo(SalesChannelContext $context, ?ProductManufacturerEntity $manufacturer = null): ?GpsrInfo
    {
        return $this->gpsrProvider->resolve($manufacturer, $context);
    }
}
