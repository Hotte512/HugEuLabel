<?php

declare(strict_types=1);

namespace Hug\EuLabel\Storefront\Twig;

use Hug\EuLabel\Service\LabelProvider;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Exposes the locale-resolved label path to storefront templates:
 *
 *   {% set path = hug_eu_label_path(context) %}
 *   <img src="{{ asset('bundles/hugeulabel/' ~ path) }}" ...>
 */
class EuLabelExtension extends AbstractExtension
{
    public function __construct(
        private readonly LabelProvider $labelProvider,
    ) {
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('hug_eu_label_path', $this->getLabelPath(...)),
        ];
    }

    public function getLabelPath(SalesChannelContext $context, string $format = LabelProvider::FORMAT_SVG): ?string
    {
        return $this->labelProvider->getLabelPath($format, $context);
    }
}
