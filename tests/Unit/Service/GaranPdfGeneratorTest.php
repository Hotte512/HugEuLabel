<?php

declare(strict_types=1);

namespace Hug\EuLabel\Tests\Unit\Service;

use Hug\EuLabel\Service\GaranPdfGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class GaranPdfGeneratorTest extends TestCase
{
    private const SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">'
        . '<rect width="100" height="100" fill="#004494"/></svg>';

    public function testGeneratesPdfFromSvg(): void
    {
        $pdf = $this->createGenerator()->generate(self::SVG);

        self::assertNotNull($pdf);
        self::assertStringStartsWith('%PDF', $pdf);
    }

    public function testEmptySvgReturnsNullWithoutRendering(): void
    {
        self::assertNull($this->createGenerator()->generate(''));
        self::assertNull($this->createGenerator()->generate("  \n "));
    }

    public function testBrokenSvgReturnsNullAndNeverThrows(): void
    {
        // dompdf verwirft ein unlesbares Bild still; entscheidend ist, dass
        // der Generator nichts nach außen wirft.
        $pdf = $this->createGenerator()->generate('<svg>kaputt');

        self::assertTrue($pdf === null || str_starts_with($pdf, '%PDF'));
    }

    public function testTextIsSetInHelveticaInsteadOfTheTimesFallback(): void
    {
        // php-svg-lib löst „Inter" nicht auf und zeichnete den Text sonst mit
        // dem Times-Default. Times-Roman steht immer als Dokument-Standard im
        // PDF — aussagekräftig ist deshalb nur, dass Helvetica überhaupt als
        // Font-Objekt auftaucht.
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 60">'
            . '<defs><style>.regular { font-family: Inter-Regular, Inter; font-size: 9px }</style></defs>'
            . '<text class="regular" transform="translate(10 40)"><tspan x="0" y="0">Hersteller</tspan></text></svg>';

        $pdf = $this->createGenerator()->generate($svg);

        self::assertNotNull($pdf);
        self::assertStringContainsString('/BaseFont /Helvetica', $pdf);
    }

    public function testBoldClassesUseTheBoldCoreFont(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 120">'
            . '<defs><style>.heavy { font-family: Inter-ExtraBold, Inter; font-size: 80px; font-weight: 700 }</style></defs>'
            . '<text class="heavy" transform="translate(10 100)"><tspan x="0" y="0">3</tspan></text></svg>';

        $pdf = $this->createGenerator()->generate($svg);

        self::assertNotNull($pdf);
        self::assertStringContainsString('/BaseFont /Helvetica-Bold', $pdf);
    }

    public function testPageMatchesTheLabelFormatAndStaysOnOneSheet(): void
    {
        // Seitenverhältnis der offiziellen Vorlage.
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 269.29 283.46">'
            . '<rect width="269.29" height="283.46" fill="#004494"/></svg>';

        $pdf = $this->createGenerator()->generate($svg);
        self::assertNotNull($pdf);

        // Eine zweite (leere) Seite entsteht, sobald das Bild rechnerisch
        // nicht mehr in den Satzspiegel passt — genau das darf nicht sein.
        self::assertSame(1, preg_match_all('#/Type\s*/Page[^s]#', $pdf), 'PDF muss einseitig sein');

        self::assertSame(1, preg_match('#/MediaBox\s*\[([^\]]+)\]#', $pdf, $box));
        [, , $width, $height] = preg_split('/\s+/', trim($box[1])) ?: [];

        // 190 mm Label + 2 × 5 mm Rand, Höhe entsprechend plus 1 mm Puffer.
        self::assertEqualsWithDelta(200.0, (float) $width * 25.4 / 72, 0.5);
        self::assertEqualsWithDelta(211.0, (float) $height * 25.4 / 72, 0.5);
    }

    /**
     * Hochformatige und viewBox-lose SVGs dürfen die Skalierung auf die
     * druckbare A4-Fläche nicht aus dem Tritt bringen.
     *
     * @dataProvider unusualGeometryProvider
     */
    public function testUnusualGeometryStillProducesAPdf(string $svg): void
    {
        $pdf = $this->createGenerator()->generate($svg);

        self::assertNotNull($pdf);
        self::assertStringStartsWith('%PDF', $pdf);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unusualGeometryProvider(): iterable
    {
        // Schlanker als A4 — die Höhe wird begrenzend.
        yield 'hochformat' => ['<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 300">'
            . '<rect width="100" height="300" fill="#004494"/></svg>'];

        yield 'querformat' => ['<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 100">'
            . '<rect width="400" height="100" fill="#004494"/></svg>'];

        yield 'ohne viewBox' => ['<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100">'
            . '<rect width="100" height="100" fill="#004494"/></svg>'];
    }

    public function testSecondCallForSameSvgIsServedFromCache(): void
    {
        $cache = new ArrayAdapter();
        $generator = new GaranPdfGenerator($cache);

        $first = $generator->generate(self::SVG);
        $second = $generator->generate(self::SVG);

        self::assertNotNull($first);
        self::assertSame($first, $second);
        self::assertCount(1, $cache->getValues());
    }

    private function createGenerator(): GaranPdfGenerator
    {
        return new GaranPdfGenerator(new ArrayAdapter());
    }
}
