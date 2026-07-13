<?php

declare(strict_types=1);

namespace Hug\EuLabel\Service;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Psr\Log\LoggerInterface;

/**
 * Befüllt die aufbereiteten EU-GARAN-Vorlagen (siehe ASSETS.md) mit den
 * Produktdaten: Marke, Modell-Kennung, Garantiedauer und QR-Code (Ziel:
 * Garantiebedingungen). Die Vorlagen selbst bleiben unverändert — es werden
 * ausschließlich die vorgesehenen Platzhalter ersetzt.
 *
 * Wirft nie: Bei Fehlern (Vorlage fehlt/kaputt, QR-Generierung schlägt fehl)
 * wird ein leerer String geliefert und der Fehler geloggt.
 */
class GaranLabelRenderer
{
    public const VARIANT_FULL = 'full';
    public const VARIANT_NESTED = 'nested';

    private const SVG_NS = 'http://www.w3.org/2000/svg';

    // Platzhalter-Texte der offiziellen Vorlage (Whitespace-normalisiert).
    private const PLACEHOLDER_BRAND = 'Brand/Trademark';
    private const PLACEHOLDER_MODEL = 'Model identifier';
    private const PLACEHOLDER_DURATION = 'XX';

    // Zeichen-Budgets bei Originalschriftgröße (9px); längere Werte werden
    // proportional verkleinert, damit sie ihre Spalte nicht sprengen.
    private const BRAND_MAX_CHARS = 34;
    private const MODEL_MAX_CHARS = 12;
    private const LABEL_FONT_SIZE = 9.0;

    // QR-Zielfläche der Farb-Vorlage: weißes Feld bei 192.33/80.3, 74.75².
    // Mit ~3.7px Rand sitzt der Code mittig mit ruhiger Zone.
    private const QR_X = 196.07;
    private const QR_Y = 84.04;
    private const QR_SIZE = 67.27;

    public function __construct(
        private readonly ?LoggerInterface $logger = null,
        private readonly ?string $templateDir = null,
    ) {
    }

    public function render(GaranData $data, string $modelId, string $variant): string
    {
        try {
            return $this->doRender($data, $modelId, $variant);
        } catch (\Throwable $e) {
            $this->logger?->error('HugEuLabel: GARAN label rendering failed.', [
                'variant' => $variant,
                'error' => $e->getMessage(),
            ]);

            return '';
        }
    }

    private function doRender(GaranData $data, string $modelId, string $variant): string
    {
        $file = $this->templateDirectory() . '/' . ($variant === self::VARIANT_NESTED ? 'garan-nested.svg' : 'garan-colour.svg');
        $svg = @file_get_contents($file);
        if ($svg === false) {
            throw new \RuntimeException(\sprintf('GARAN template "%s" not readable', $file));
        }

        $document = new \DOMDocument();
        if (!@$document->loadXML($svg)) {
            throw new \RuntimeException(\sprintf('GARAN template "%s" is not valid XML', $file));
        }

        $this->replacePlaceholder($document, self::PLACEHOLDER_DURATION, (string) $data->durationYears);

        if ($variant !== self::VARIANT_NESTED) {
            $this->replacePlaceholder($document, self::PLACEHOLDER_BRAND, $data->manufacturerName, self::BRAND_MAX_CHARS);
            $this->replacePlaceholder($document, self::PLACEHOLDER_MODEL, $modelId, self::MODEL_MAX_CHARS);
            $this->injectQrCode($document, $data->conditionsUrl);
        }

        $rendered = $document->saveXML($document->documentElement);
        if ($rendered === false) {
            throw new \RuntimeException('GARAN label serialization failed');
        }

        return $this->namespaceIds($rendered, $variant, $data, $modelId);
    }

    /**
     * Beide EU-Vorlagen verwenden intern dieselben IDs (clippath, Layer_1, …).
     * Stehen mehrere Labels auf einer Seite (nested + aufgeklapptes volles
     * Label, Listing-Kacheln), würden url(#…)-Referenzen auf das jeweils
     * erste Vorkommen zeigen und die Darstellung zerstören — daher bekommt
     * jede Render-Variante ein eindeutiges ID-Suffix.
     */
    private function namespaceIds(string $svg, string $variant, GaranData $data, string $modelId): string
    {
        $suffix = '-' . substr(md5($variant . '|' . $modelId . '|' . $data->durationYears . '|' . $data->conditionsUrl . '|' . $data->manufacturerName), 0, 8);

        $svg = (string) preg_replace('/\bid="([^"]+)"/', 'id="$1' . $suffix . '"', $svg);

        return (string) preg_replace('/url\(#([^)]+)\)/', 'url(#$1' . $suffix . ')', $svg);
    }

    private function templateDirectory(): string
    {
        return $this->templateDir ?? __DIR__ . '/../Resources/garan-templates';
    }

    private function replacePlaceholder(\DOMDocument $document, string $placeholder, string $value, ?int $maxChars = null): void
    {
        foreach ($document->getElementsByTagName('text') as $text) {
            $content = preg_replace('/\s+/', '', $text->textContent);
            if ($content !== preg_replace('/\s+/', '', $placeholder)) {
                continue;
            }

            while ($text->firstChild !== null) {
                $text->removeChild($text->firstChild);
            }

            $tspan = $document->createElementNS(self::SVG_NS, 'tspan');
            $tspan->setAttribute('x', '0');
            $tspan->setAttribute('y', '0');
            $tspan->appendChild($document->createTextNode($value));
            $text->appendChild($tspan);

            $length = mb_strlen($value);
            if ($maxChars !== null && $length > $maxChars) {
                $fontSize = round(self::LABEL_FONT_SIZE * $maxChars / $length, 2);
                $text->setAttribute('style', \sprintf('font-size: %spx', $fontSize));
            }

            return;
        }
    }

    private function injectQrCode(\DOMDocument $document, string $url): void
    {
        $target = null;
        foreach ($document->getElementsByTagName('g') as $group) {
            if ($group->getAttribute('id') === 'hug-garan-qr') {
                $target = $group;
                break;
            }
        }
        if ($target === null) {
            throw new \RuntimeException('GARAN template misses the hug-garan-qr target group');
        }

        $writer = new Writer(new ImageRenderer(new RendererStyle(400, 0), new SvgImageBackEnd()));
        $qrSvg = $writer->writeString($url);

        $qrDocument = new \DOMDocument();
        if (!@$qrDocument->loadXML($qrSvg)) {
            throw new \RuntimeException('QR code SVG could not be parsed');
        }

        $qrRoot = $qrDocument->documentElement;
        \assert($qrRoot !== null);
        $viewBox = explode(' ', $qrRoot->getAttribute('viewBox'));
        $qrWidth = (float) ($viewBox[2] ?? 400.0);

        $target->setAttribute('transform', \sprintf(
            'translate(%s,%s) scale(%s)',
            self::QR_X,
            self::QR_Y,
            round(self::QR_SIZE / $qrWidth, 6),
        ));

        foreach (iterator_to_array($qrRoot->childNodes) as $child) {
            $target->appendChild($document->importNode($child, true));
        }
    }
}
