<?php

declare(strict_types=1);

namespace Hug\EuLabel\Tests\Unit\Storefront;

use Hug\EuLabel\Service\GpsrInfo;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Rendert das echte gpsr-info.html.twig in einem nackten Twig-Environment
 * (config/trans/sw_sanitize/hug_gpsr_info als Stubs) und prüft vor allem das
 * Escaping: Nutzereingaben aus den Custom Fields dürfen NIE als HTML
 * ankommen, Zeilenumbrüche müssen zu <br> werden.
 */
final class GpsrInfoTemplateTest extends TestCase
{
    private const TEMPLATE = 'storefront/component/hug-gpsr/gpsr-info.html.twig';

    /**
     * @var array<string, mixed>
     */
    private array $config = [];

    private ?GpsrInfo $info = null;

    public function testHtmlInUserInputIsEscapedAndNewlinesBecomeBr(): void
    {
        $this->info = new GpsrInfo(
            "<script>alert('xss')</script>\nZeile 2",
            null,
            false,
        );

        $html = $this->render();

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringContainsString('<br', $html);
    }

    public function testResponsiblePersonRendersWithOwnSubheadlineAndEscaping(): void
    {
        $this->info = new GpsrInfo(
            'Overseas Ltd.',
            "EU-Vertretung GmbH\n<b>fett</b>",
            false,
        );

        $html = $this->render();

        self::assertStringContainsString('SNIPPET[hugEuLabel.gpsr.responsiblePerson]', $html);
        self::assertStringContainsString('hug-gpsr-subheadline', $html);
        self::assertStringContainsString('&lt;b&gt;fett&lt;/b&gt;', $html);
        self::assertStringNotContainsString('<b>fett</b>', $html);
    }

    public function testResponsiblePersonBlockIsOmittedWhenNotSet(): void
    {
        $this->info = new GpsrInfo('Hersteller GmbH', null, false);

        $html = $this->render();

        self::assertStringNotContainsString('hug-gpsr-subheadline', $html);
        self::assertStringNotContainsString('SNIPPET[hugEuLabel.gpsr.responsiblePerson]', $html);
    }

    public function testRendersNothingWithoutGpsrInfo(): void
    {
        $this->info = null;

        self::assertSame('', trim($this->render()));
    }

    public function testHeadlineFallsBackToSnippetAndConfigValueIsEscaped(): void
    {
        $this->info = new GpsrInfo('Hersteller GmbH', null, false);

        $html = $this->render();
        self::assertStringContainsString('SNIPPET[hugEuLabel.gpsr.headline]', $html);

        $this->config['HugEuLabel.config.gpsrHeadline'] = 'Eigene <i>Überschrift</i>';

        $html = $this->render();
        self::assertStringNotContainsString('SNIPPET[hugEuLabel.gpsr.headline]', $html);
        self::assertStringContainsString('Eigene &lt;i&gt;Überschrift&lt;/i&gt;', $html);
    }

    public function testBannerModeUsesThemeClassAndPlainModeDoesNot(): void
    {
        $this->info = new GpsrInfo('Hersteller GmbH', null, false);
        $this->config['HugEuLabel.config.gpsrBannerTheme'] = 'soft_blue';

        $html = $this->render();
        self::assertStringContainsString('hug-gpsr-banner hug-gpsr-banner--soft-blue', $html);

        $this->config['HugEuLabel.config.gpsrDisplayMode'] = 'plain';

        $html = $this->render();
        self::assertStringContainsString('hug-gpsr--plain', $html);
        self::assertStringNotContainsString('hug-gpsr-banner', $html);
    }

    private function render(): string
    {
        $loader = new FilesystemLoader(\dirname(__DIR__, 3) . '/src/Resources/views');
        $twig = new Environment($loader);

        $twig->addFunction(new TwigFunction('config', fn (string $key): mixed => $this->config[$key] ?? null));
        $twig->addFunction(new TwigFunction('hug_gpsr_info', fn (): ?GpsrInfo => $this->info));
        $twig->addFilter(new TwigFilter('trans', static fn (?string $key): string => 'SNIPPET[' . $key . ']'));
        $twig->addFilter(new TwigFilter('sw_sanitize', static fn (?string $value): ?string => $value, ['is_safe' => ['html']]));

        return $twig->render(self::TEMPLATE);
    }
}
