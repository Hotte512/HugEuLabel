<?php

declare(strict_types=1);

namespace Hug\EuLabel\Tests\Unit\Storefront;

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Node\Node;
use Twig\Node\TextNode;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;
use Twig\TokenParser\IncludeTokenParser;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Rendert die echten Label-Templates in einem nackten Twig-Environment und
 * prüft die Breitenauflösung pro Anzeigefläche: PDP/Listing optional mit
 * eigenem Wert, sonst die allgemeine Breite (Warenkorb/Checkout).
 * sw_include wird auf das native include abgebildet, sw_icon gestubbt.
 */
final class LabelWidthTemplateTest extends TestCase
{
    private const EU_LABEL = '@HugEuLabel/storefront/component/hug-eu-label/label.html.twig';
    private const GARAN_LABEL = '@HugEuLabel/storefront/component/hug-garan/garan-label.html.twig';

    /**
     * @var array<string, mixed>
     */
    private array $config = [
        'HugEuLabel.config.active' => true,
        'HugEuLabel.config.garanEnabled' => true,
        'HugEuLabel.config.maxWidth' => 400,
        'HugEuLabel.config.compactWidth' => 350,
        'HugEuLabel.config.garanNestedWidth' => 368,
    ];

    public function testPdpUsesGlobalWidthWithoutOverride(): void
    {
        self::assertStringContainsString('max-width: 400px', $this->renderEu('full', 'pdp'));
    }

    public function testPdpOverrideAppliesOnlyToPdp(): void
    {
        $this->config['HugEuLabel.config.pdpMaxWidth'] = 220;

        $pdp = $this->renderEu('full', 'pdp');
        self::assertStringContainsString('max-width: 220px', $pdp);
        self::assertStringNotContainsString('max-width: 400px', $pdp);

        self::assertStringContainsString('max-width: 400px', $this->renderEu('full', 'confirm'));
        self::assertStringContainsString('max-width: 400px', $this->renderEu('full', 'cart'));
    }

    public function testZeroOverrideFallsBackToGlobalWidth(): void
    {
        $this->config['HugEuLabel.config.pdpMaxWidth'] = 0;

        self::assertStringContainsString('max-width: 400px', $this->renderEu('full', 'pdp'));
    }

    public function testMissingGlobalWidthFallsBackTo300(): void
    {
        unset($this->config['HugEuLabel.config.maxWidth']);

        self::assertStringContainsString('max-width: 300px', $this->renderEu('full', 'confirm'));
    }

    public function testPdpCompactOverride(): void
    {
        $this->config['HugEuLabel.config.pdpCompactWidth'] = 250;
        $this->config['HugEuLabel.config.pdpMaxWidth'] = 220;

        $pdp = $this->renderEu('compact', 'pdp');
        self::assertStringContainsString('max-width: 250px; width: 100%; aspect-ratio', $pdp);
        // Das aufgeklappte volle Label nutzt die PDP-Breite.
        self::assertStringContainsString('max-width: 220px', $pdp);

        self::assertStringContainsString('max-width: 350px; width: 100%; aspect-ratio', $this->renderEu('compact', 'confirm'));
    }

    public function testCompactWithoutAnyCompactWidthUsesResolvedLabelWidth(): void
    {
        unset($this->config['HugEuLabel.config.compactWidth']);
        $this->config['HugEuLabel.config.pdpMaxWidth'] = 220;

        self::assertStringContainsString('max-width: 220px; width: 100%; aspect-ratio', $this->renderEu('compact', 'pdp'));
    }

    public function testGaranNestedWidthPerSurface(): void
    {
        $this->config['HugEuLabel.config.garanNestedWidthPdp'] = 300;
        $this->config['HugEuLabel.config.garanNestedWidthListing'] = 200;

        self::assertStringContainsString('max-width: 300px', $this->renderGaran('nested', 'pdp'));
        self::assertStringContainsString('max-width: 200px', $this->renderGaran('nested', 'listing'));
        self::assertStringContainsString('max-width: 368px', $this->renderGaran('nested', 'confirm'));
    }

    public function testGaranNestedFallsBackToGlobalAnd368(): void
    {
        $this->config['HugEuLabel.config.garanNestedWidth'] = 320;
        self::assertStringContainsString('max-width: 320px', $this->renderGaran('nested', 'listing'));

        unset($this->config['HugEuLabel.config.garanNestedWidth']);
        self::assertStringContainsString('max-width: 368px', $this->renderGaran('nested', 'listing'));
    }

    public function testGaranFullUsesPdpWidthOnPdpOnly(): void
    {
        $this->config['HugEuLabel.config.pdpMaxWidth'] = 220;

        self::assertStringContainsString('max-width: 220px', $this->renderGaran('full', 'pdp'));
        self::assertStringContainsString('max-width: 400px', $this->renderGaran('full', 'confirm'));
    }

    private function renderEu(string $mode, string $surface): string
    {
        return $this->twig()->render(self::EU_LABEL, [
            'context' => null,
            'hugEuLabelMode' => $mode,
            'hugEuLabelSurface' => $surface,
        ]);
    }

    private function renderGaran(string $mode, string $surface): string
    {
        return $this->twig()->render(self::GARAN_LABEL, [
            'context' => null,
            'hugGaranProduct' => new class {
                public string $id = 'p1';
            },
            'hugGaranMode' => $mode,
            'hugGaranSurface' => $surface,
        ]);
    }

    private function twig(): Environment
    {
        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 3) . '/src/Resources/views', 'HugEuLabel');

        $twig = new Environment($loader, ['strict_variables' => false]);
        $twig->addTokenParser(new class extends IncludeTokenParser {
            public function getTag(): string
            {
                return 'sw_include';
            }
        });
        $twig->addTokenParser($this->createIconTokenParser());
        $twig->addFunction(new TwigFunction('config', fn (string $key): mixed => $this->config[$key] ?? null));
        $twig->addFunction(new TwigFunction('hug_eu_label_path', static fn (mixed $context, string $type = 'svg'): ?string => $type === 'svg' ? 'labels/de-DE/gewaehrleistungslabel.svg' : null));
        $twig->addFunction(new TwigFunction('asset', static fn (string $path): string => '/' . $path));
        $twig->addFunction(new TwigFunction('path', static fn (string $route): string => '/' . $route));
        $twig->addFunction(new TwigFunction('hug_garan_conditions_url', static fn (): string => '/conditions'));
        $twig->addFunction(new TwigFunction('hug_garan_label', static fn (): string => '<svg></svg>', ['is_safe' => ['html']]));
        $twig->addFilter(new TwigFilter('trans', static fn (?string $key): string => 'SNIPPET[' . $key . ']'));
        $twig->addFilter(new TwigFilter('sw_sanitize', static fn (?string $value): ?string => $value, ['is_safe' => ['html']]));

        return $twig;
    }

    /**
     * Verwirft {% sw_icon … %} — die PDF-Leiste ist hier nicht Gegenstand
     * (PdfActionsTemplateTest deckt sie ab).
     */
    private function createIconTokenParser(): AbstractTokenParser
    {
        return new class extends AbstractTokenParser {
            public function parse(Token $token): Node
            {
                $this->parser->parseExpression();
                $stream = $this->parser->getStream();

                if ($stream->nextIf(Token::NAME_TYPE, 'style')) {
                    $this->parser->parseExpression();
                }

                $stream->expect(Token::BLOCK_END_TYPE);

                return new TextNode('', $token->getLine());
            }

            public function getTag(): string
            {
                return 'sw_icon';
            }
        };
    }
}
