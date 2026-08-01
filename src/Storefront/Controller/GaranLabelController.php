<?php

declare(strict_types=1);

namespace Hug\EuLabel\Storefront\Controller;

use Hug\EuLabel\Service\GaranLabelService;
use Hug\EuLabel\Service\GaranPdfGenerator;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Liefert das befüllte GARAN-Label als eigenständiges SVG (browser-cachebar).
 * Das volle Label wiegt ~280 KB — als <img> eingebunden statt inline
 * gerendert bleibt die Produktseite leicht.
 *
 * Zusätzlich als PDF für die optionalen Download-Schaltflächen unter dem
 * Label.
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
        private readonly GaranPdfGenerator $pdfGenerator,
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
        $svg = $this->labelService->render($this->loadProduct($productId, $context), $variant, $context);
        if ($svg === '') {
            throw new NotFoundHttpException();
        }

        $response = new Response($svg, Response::HTTP_OK, ['Content-Type' => 'image/svg+xml']);

        return $this->finish($response, $svg, $request);
    }

    /**
     * Inline statt attachment: Der Download-Button erzwingt das Speichern
     * ohnehin über das download-Attribut, „In neuem Tab öffnen" soll den
     * PDF-Viewer zeigen. So genügt eine URL und ein Cache-Eintrag.
     */
    #[Route(
        path: '/hug-garan-label/{productId}/label.pdf',
        name: 'frontend.hug_garan.label_pdf',
        methods: ['GET'],
        requirements: ['productId' => '[0-9a-f]{32}'],
    )]
    public function labelPdf(string $productId, SalesChannelContext $context, Request $request): Response
    {
        $product = $this->loadProduct($productId, $context);

        $svg = $this->labelService->render($product, 'full', $context);
        if ($svg === '') {
            throw new NotFoundHttpException();
        }

        $pdf = $this->pdfGenerator->generate($svg);
        if ($pdf === null) {
            // Fehlerdetails stehen bereits im Channel hug_eu_label.
            throw new NotFoundHttpException();
        }

        $response = new Response($pdf, Response::HTTP_OK, ['Content-Type' => 'application/pdf']);
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_INLINE,
            $this->buildFileName($product),
        ));

        return $this->finish($response, $pdf, $request);
    }

    private function loadProduct(string $productId, SalesChannelContext $context): ?ProductEntity
    {
        if (!Uuid::isValid($productId)) {
            throw new NotFoundHttpException();
        }

        $criteria = new Criteria([$productId]);
        $criteria->setTitle('hug-eu-label::garan-label-route');
        $criteria->addAssociation('manufacturer');

        // Sales-Channel-Repository erzwingt Sichtbarkeit/Aktiv-Status/Kanal-
        // Zuordnung: im Kanal unsichtbare Produkte liefern null → 404.
        return $this->productRepository->search($criteria, $context)->getEntities()->first();
    }

    private function buildFileName(?ProductEntity $product): string
    {
        $number = (string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $product?->getProductNumber() ?? '');
        $number = trim($number, '-');

        if ($number === '') {
            return 'garantielabel.pdf';
        }

        return 'garantielabel-' . mb_substr($number, 0, 60) . '.pdf';
    }

    private function finish(Response $response, string $body, Request $request): Response
    {
        $response->setPublic();
        $response->setMaxAge(3600);
        // Sonst überschreibt Symfonys Session-Listener die Cache-Header
        // mit "no-cache, private".
        $response->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true');

        // ETag/304 als Absicherung: Selbst wenn ein Cache-Subscriber die
        // Header auf no-cache umschreibt, spart die Revalidierung den
        // großen Transfer.
        $response->setEtag(md5($body));
        $response->isNotModified($request);

        return $response;
    }
}
