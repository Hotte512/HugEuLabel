<?php

declare(strict_types=1);

namespace Hug\EuLabel\Tests\Unit\Service;

use Hug\EuLabel\Service\GaranData;
use Hug\EuLabel\Service\GaranLabelRenderer;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class GaranLabelRendererTest extends TestCase
{
    private function createRenderer(?LoggerInterface $logger = null, ?string $templateDir = null): GaranLabelRenderer
    {
        return new GaranLabelRenderer($logger, $templateDir ?? \dirname(__DIR__, 3) . '/src/Resources/garan-templates');
    }

    private function createData(string $manufacturer = 'Holzwerk GmbH'): GaranData
    {
        return new GaranData(5, 'https://example.com/garantiebedingungen', $manufacturer);
    }

    public function testFullVariantFillsAllPlaceholders(): void
    {
        $svg = $this->createRenderer()->render($this->createData(), 'HW-12345', GaranLabelRenderer::VARIANT_FULL);

        self::assertStringContainsString('Holzwerk GmbH', $svg);
        self::assertStringContainsString('HW-12345', $svg);
        self::assertStringNotContainsString('Brand/', $svg);
        self::assertStringNotContainsString('Model identifier', $svg);
        self::assertStringNotContainsString('>XX<', $svg);
        self::assertStringContainsString('viewBox="0 0 269.29 283.46"', $svg);
    }

    public function testFullVariantInjectsQrCodeIntoTargetGroup(): void
    {
        $svg = $this->createRenderer()->render($this->createData(), 'HW-1', GaranLabelRenderer::VARIANT_FULL);

        self::assertMatchesRegularExpression(
            '/<g id="hug-garan-qr[^"]*" transform="translate\([^)]+\) scale\([^)]+\)">.*<path/s',
            $svg,
        );
        // Die URL selbst darf nur im QR stecken, nicht als Klartext.
        self::assertStringNotContainsString('https://example.com/garantiebedingungen', $svg);
    }

    public function testNestedVariantReplacesDuration(): void
    {
        $svg = $this->createRenderer()->render($this->createData(), 'HW-1', GaranLabelRenderer::VARIANT_NESTED);

        self::assertStringNotContainsString('>XX<', $svg);
        self::assertStringContainsString('>5<', $svg);
        self::assertStringContainsString('viewBox="0 0 368.5 56.69"', $svg);
        // Nested enthält weder Marke noch QR.
        self::assertStringNotContainsString('Holzwerk', $svg);
    }

    public function testLongManufacturerNameShrinksFontSize(): void
    {
        $svg = $this->createRenderer()->render(
            $this->createData('Sehr Lange Holzmanufaktur Norddeutschland GmbH & Co. KG'),
            'HW-1',
            GaranLabelRenderer::VARIANT_FULL,
        );

        self::assertMatchesRegularExpression('/font-size:\s*[0-8](?:\.\d+)?px/', $svg);
    }

    public function testIdsAreNamespacedSoMultipleLabelsShareOnePage(): void
    {
        // Beide Vorlagen nutzen intern dieselben IDs (clippath, Layer_1, …).
        // Stehen zwei Labels auf einer Seite (z. B. nested + aufgeklapptes
        // volles Label), würden kollidierende IDs die Darstellung zerstören.
        $renderer = $this->createRenderer();
        $full = $renderer->render($this->createData(), 'HW-1', GaranLabelRenderer::VARIANT_FULL);
        $nested = $renderer->render($this->createData(), 'HW-1', GaranLabelRenderer::VARIANT_NESTED);

        preg_match_all('/id="([^"]+)"/', $full, $fullIds);
        preg_match_all('/id="([^"]+)"/', $nested, $nestedIds);

        self::assertNotEmpty($fullIds[1]);
        self::assertSame([], array_values(array_intersect($fullIds[1], $nestedIds[1])));
        // Referenzen müssen mitziehen: keine url(#...) auf eine fremde/alte ID.
        preg_match_all('/url\(#([^)]+)\)/', $full, $refs);
        foreach (array_unique($refs[1]) as $ref) {
            self::assertContains($ref, $fullIds[1], \sprintf('url(#%s) zeigt ins Leere', $ref));
        }
    }

    public function testBrokenTemplateDirReturnsEmptyStringAndLogsError(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $svg = $this->createRenderer($logger, '/gibt/es/nicht')->render($this->createData(), 'HW-1', GaranLabelRenderer::VARIANT_FULL);

        self::assertSame('', $svg);
    }
}
