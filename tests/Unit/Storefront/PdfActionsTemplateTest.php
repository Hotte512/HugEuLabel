<?php

declare(strict_types=1);

namespace Hug\EuLabel\Tests\Unit\Storefront;

use PHPUnit\Framework\TestCase;
use Shopware\Storefront\Framework\Twig\IconExtension;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Node;
use Twig\Node\TextNode;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Rendert das echte hug-pdf-actions.html.twig in einem nackten Twig-
 * Environment. sw_icon wird durch einen Stub-TokenParser ersetzt (der echte
 * erzeugt einen sw_include und bräuchte den kompletten TemplateFinder der
 * Plattform); dass die verwendeten Icon-Namen im Storefront-Set existieren,
 * prüft testUsedIconsExistInTheStorefrontIconSet separat.
 */
final class PdfActionsTemplateTest extends TestCase
{
    private const TEMPLATE = 'storefront/component/hug-pdf-actions.html.twig';

    /**
     * @var array<string, mixed>
     */
    private array $config = [];

    public function testRendersNothingWhenModeIsOff(): void
    {
        $this->config['HugEuLabel.config.pdfButtonsMode'] = 'off';

        self::assertSame('', trim($this->render()));
    }

    public function testRendersNothingWhenModeIsUnset(): void
    {
        self::assertSame('', trim($this->render()));
    }

    public function testRendersNothingWhenSurfaceIsDisabled(): void
    {
        $this->config['HugEuLabel.config.pdfButtonsMode'] = 'both';

        self::assertSame('', trim($this->render(['hugPdfShow' => false])));
    }

    public function testRendersNothingWithoutUrl(): void
    {
        $this->config['HugEuLabel.config.pdfButtonsMode'] = 'both';

        self::assertSame('', trim($this->render(['hugPdfUrl' => ''])));
    }

    public function testDownloadModeRendersOnlyTheDownloadLink(): void
    {
        $this->config['HugEuLabel.config.pdfButtonsMode'] = 'download';

        $html = $this->render(['hugPdfFileName' => 'EU-Gewaehrleistungslabel.pdf']);

        self::assertSame(1, substr_count($html, '<a '));
        self::assertStringContainsString('download="EU-Gewaehrleistungslabel.pdf"', $html);
        self::assertStringNotContainsString('target="_blank"', $html);
        self::assertStringContainsString('icon-cloud-download', $html);
    }

    public function testDownloadWithoutFileNameRendersBareAttribute(): void
    {
        $this->config['HugEuLabel.config.pdfButtonsMode'] = 'download';

        $html = $this->render();

        self::assertStringContainsString('download', $html);
        self::assertStringNotContainsString('download="', $html);
    }

    public function testNewTabModeRendersOnlyTheNewTabLinkWithNoopener(): void
    {
        $this->config['HugEuLabel.config.pdfButtonsMode'] = 'new_tab';

        $html = $this->render();

        self::assertSame(1, substr_count($html, '<a '));
        self::assertStringContainsString('target="_blank"', $html);
        self::assertStringContainsString('rel="noopener"', $html);
        self::assertStringNotContainsString('download', $html);
        self::assertStringContainsString('icon-external', $html);
    }

    public function testBothModeRendersDownloadBeforeNewTab(): void
    {
        $this->config['HugEuLabel.config.pdfButtonsMode'] = 'both';

        $html = $this->render();

        self::assertSame(2, substr_count($html, '<a '));
        self::assertLessThan(
            (int) strpos($html, 'SNIPPET[hugEuLabel.pdf.newTab]'),
            (int) strpos($html, 'SNIPPET[hugEuLabel.pdf.download]'),
        );
    }

    public function testAriaLabelsAreLabelSpecific(): void
    {
        $this->config['HugEuLabel.config.pdfButtonsMode'] = 'both';

        $eu = $this->render(['hugPdfKind' => 'eu']);
        self::assertStringContainsString('SNIPPET[hugEuLabel.pdf.downloadEu]', $eu);
        self::assertStringContainsString('SNIPPET[hugEuLabel.pdf.newTabEu]', $eu);

        $garan = $this->render(['hugPdfKind' => 'garan']);
        self::assertStringContainsString('SNIPPET[hugEuLabel.pdf.downloadGaran]', $garan);
        self::assertStringContainsString('SNIPPET[hugEuLabel.pdf.newTabGaran]', $garan);
    }

    public function testUrlAndFileNameAreEscaped(): void
    {
        $this->config['HugEuLabel.config.pdfButtonsMode'] = 'both';

        $html = $this->render([
            'hugPdfUrl' => '/label.pdf?a=1&b="x"',
            'hugPdfFileName' => 'label" onload="alert(1)',
        ]);

        self::assertStringNotContainsString('onload="alert(1)"', $html);
        self::assertStringContainsString('&amp;b=', $html);
        self::assertStringContainsString('&quot;', $html);
    }

    public function testUsedIconsExistInTheStorefrontIconSet(): void
    {
        $iconDir = $this->storefrontPath() . '/Resources/app/storefront/dist/assets/icon/default';

        self::assertFileExists($iconDir . '/cloud-download.svg');
        self::assertFileExists($iconDir . '/external.svg');
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function render(array $overrides = []): string
    {
        $loader = new FilesystemLoader(\dirname(__DIR__, 3) . '/src/Resources/views');

        $twig = new Environment($loader);
        $twig->addTokenParser($this->createIconTokenParser());
        $twig->addFunction(new TwigFunction('config', fn (string $key): mixed => $this->config[$key] ?? null));
        $twig->addFilter(new TwigFilter('trans', static fn (?string $key): string => 'SNIPPET[' . $key . ']'));
        $twig->addFilter(new TwigFilter('sw_sanitize', static fn (?string $value): ?string => $value, ['is_safe' => ['html']]));
        $twig->addFilter(new TwigFilter('sw_icon_cache', static fn (?string $value): ?string => $value, ['is_safe' => ['html']]));

        return $twig->render(self::TEMPLATE, array_merge([
            'hugPdfUrl' => '/label.pdf',
            'hugPdfShow' => true,
            'hugPdfKind' => 'eu',
        ], $overrides));
    }

    /**
     * Ersetzt {% sw_icon 'name' style { … } %} durch die Markup-Hülle, die
     * die Storefront ohne konfiguriertes Theme-Icon-Pack ausgibt.
     */
    private function createIconTokenParser(): AbstractTokenParser
    {
        return new class() extends AbstractTokenParser {
            public function parse(Token $token): Node
            {
                $name = $this->parser->parseExpression();
                $stream = $this->parser->getStream();

                if ($stream->nextIf(Token::NAME_TYPE, 'style')) {
                    $this->parser->parseExpression();
                }

                $stream->expect(Token::BLOCK_END_TYPE);

                $value = $name instanceof ConstantExpression ? (string) $name->getAttribute('value') : 'unknown';

                return new TextNode(
                    \sprintf('<span class="icon icon-%s icon-sm" aria-hidden="true"></span>', $value),
                    $token->getLine(),
                );
            }

            public function getTag(): string
            {
                return 'sw_icon';
            }
        };
    }

    private function storefrontPath(): string
    {
        $reflection = new \ReflectionClass(IconExtension::class);
        $file = $reflection->getFileName();
        self::assertIsString($file);

        // …/vendor/shopware/storefront/Framework/Twig/IconExtension.php
        return \dirname($file, 3);
    }
}
