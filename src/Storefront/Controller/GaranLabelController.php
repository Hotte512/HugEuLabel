<?php

declare(strict_types=1);

namespace Hug\EuLabel\Storefront\Controller;

use Hug\EuLabel\Service\GaranLabelService;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Liefert das befüllte GARAN-Label als eigenständiges SVG (browser-cachebar).
 * Das volle Label wiegt ~280 KB — als <img> eingebunden statt inline
 * gerendert bleibt die Produktseite leicht.
 */
#[Route(defaults: ['_routeScope' => ['storefront']])]
class GaranLabelController
{
    /**
     * @param SalesChannelRepository<ProductCollection> $productRepository
     */
    public function __construct(
        private readonly GaranLabelService $labelService,
        private readonly SalesChannelRepository $productRepository,
    ) {
    }

    #[Route(
        path: '/hug-garan-label/{productId}/{variant}',
        name: 'frontend.hug_garan.label',
        methods: ['GET'],
        requirements: ['productId' => '[0-9a-f]{32}', 'variant' => 'full|nested'],
    )]
    public function label(string $productId, string $variant, SalesChannelContext $context, Request $request): Response
    {
        if (!Uuid::isValid($productId)) {
            throw new NotFoundHttpException();
        }

        $criteria = new Criteria([$productId]);
        $criteria->setTitle('hug-eu-label::garan-label-route');
        $criteria->addAssociation('manufacturer');

        // Sales-Channel-Repository erzwingt Sichtbarkeit/Aktiv-Status/Kanal-
        // Zuordnung: im Kanal unsichtbare Produkte liefern null → 404.
        $product = $this->productRepository->search($criteria, $context)->getEntities()->first();

        $svg = $this->labelService->render($product, $variant, $context);
        if ($svg === '') {
            throw new NotFoundHttpException();
        }

        $response = new Response($svg, Response::HTTP_OK, ['Content-Type' => 'image/svg+xml']);
        $response->setPublic();
        $response->setMaxAge(3600);
        // Sonst überschreibt Symfonys Session-Listener die Cache-Header
        // mit "no-cache, private".
        $response->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true');

        // ETag/304 als Absicherung: Selbst wenn ein Cache-Subscriber die
        // Header auf no-cache umschreibt, spart die Revalidierung den
        // ~280-KB-Transfer.
        $response->setEtag(md5($svg));
        $response->isNotModified($request);

        return $response;
    }
}
