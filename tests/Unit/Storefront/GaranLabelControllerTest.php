<?php

declare(strict_types=1);

namespace Hug\EuLabel\Tests\Unit\Storefront;

use Hug\EuLabel\Service\GaranLabelService;
use Hug\EuLabel\Storefront\Controller\GaranLabelController;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticSalesChannelRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class GaranLabelControllerTest extends TestCase
{
    public function testDeliversSvgWithCacheHeaders(): void
    {
        $controller = $this->createController('<svg>label</svg>');

        $response = $controller->label(Uuid::randomHex(), 'full', $this->createSalesChannelContext(), new Request());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('<svg>label</svg>', $response->getContent());
        self::assertSame('image/svg+xml', $response->headers->get('Content-Type'));
        self::assertStringContainsString('public', (string) $response->headers->get('Cache-Control'));
    }

    public function testReturns404WhenNoLabelExists(): void
    {
        $controller = $this->createController('');

        $this->expectException(NotFoundHttpException::class);

        $controller->label(Uuid::randomHex(), 'full', $this->createSalesChannelContext(), new Request());
    }

    public function testReturns404ForInvalidProductId(): void
    {
        $controller = $this->createController('<svg/>');

        $this->expectException(NotFoundHttpException::class);

        $controller->label('kein-uuid', 'full', $this->createSalesChannelContext(), new Request());
    }

    private function createController(string $renderedSvg): GaranLabelController
    {
        $service = $this->createMock(GaranLabelService::class);
        $service->method('render')->willReturn($renderedSvg);

        $product = new ProductEntity();
        $product->setId(Uuid::randomHex());
        $product->setUniqueIdentifier($product->getId());

        // Sales-Channel-Repository: erzwingt Sichtbarkeit/Aktiv-Status —
        // ein im Kanal unsichtbares Produkt käme hier gar nicht durch.
        /** @var StaticSalesChannelRepository<ProductCollection> $productRepository */
        $productRepository = new StaticSalesChannelRepository([
            new ProductCollection([$product]),
        ]);

        return new GaranLabelController($service, $productRepository);
    }

    private function createSalesChannelContext(): SalesChannelContext
    {
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getContext')->willReturn(new Context(new SystemSource()));

        return $context;
    }
}
