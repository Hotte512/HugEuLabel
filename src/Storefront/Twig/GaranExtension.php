<?php

declare(strict_types=1);

namespace Hug\EuLabel\Storefront\Twig;

use Hug\EuLabel\Service\GaranLabelService;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Stellt das GARAN-Label den Storefront-Templates bereit (Logik liegt im
 * GaranLabelService, den auch der Label-Controller nutzt):
 *
 *   {% set garanSvg = hug_garan_label(product, 'nested', context) %}
 *   {% if garanSvg %} {{ garanSvg|raw }} … {% endif %}
 */
class GaranExtension extends AbstractExtension
{
    /** @var array<string, ?ProductEntity> */
    private array $lineItemProductCache = [];

    /**
     * @param EntityRepository<ProductCollection> $productRepository
     */
    public function __construct(
        private readonly GaranLabelService $labelService,
        private readonly EntityRepository $productRepository,
    ) {
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('hug_garan_label', $this->labelService->render(...), ['is_safe' => ['html']]),
            new TwigFunction('hug_garan_conditions_url', $this->labelService->getConditionsUrl(...)),
            new TwigFunction('hug_garan_line_item_product', $this->getLineItemProduct(...)),
        ];
    }

    /**
     * Lädt das Produkt einer Warenkorb-Position inklusive Hersteller — für
     * das GARAN-Label an den Bestellpositionen der Confirm-Seite. Pro Request
     * gecacht (dieselbe Position rendert in Haupt- und Offcanvas-Bereich).
     */
    public function getLineItemProduct(LineItem $lineItem, SalesChannelContext $context): ?ProductEntity
    {
        if ($lineItem->getType() !== LineItem::PRODUCT_LINE_ITEM_TYPE || $lineItem->getReferencedId() === null) {
            return null;
        }

        $productId = $lineItem->getReferencedId();
        if (\array_key_exists($productId, $this->lineItemProductCache)) {
            return $this->lineItemProductCache[$productId];
        }

        $criteria = new Criteria([$productId]);
        $criteria->addAssociation('manufacturer');

        return $this->lineItemProductCache[$productId] = $this->productRepository
            ->search($criteria, $context->getContext())
            ->getEntities()
            ->first();
    }
}
