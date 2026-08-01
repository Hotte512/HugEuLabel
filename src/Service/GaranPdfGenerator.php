<?php

declare(strict_types=1);

namespace Hug\EuLabel\Service;

use Dompdf\Dompdf;
use Dompdf\Options;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Wandelt ein gerendertes GARAN-Label-SVG in ein PDF (dompdf ist
 * Shopware-Bestandteil, php-svg-lib liegt als dompdf-Abhängigkeit bei).
 *
 * Das Seitenformat richtet sich nach dem Label, nicht umgekehrt: die Seite
 * bekommt exakt das Seitenverhältnis der viewBox plus einen schmalen Rand.
 * Auf A4 gedruckt bliebe rund ein Drittel der Seite leer, weil die Vorlage
 * nahezu quadratisch ist.
 *
 * Bekannte Fidelity-Grenze: php-svg-lib kann die Vorlagenschrift „Inter"
 * nicht auflösen und fällt auf seine Default-Schrift (Times, Serife) zurück.
 * substituteFonts() setzt deshalb auf den Textelementen eine der PDF-Kern-
 * schriften (Helvetica/Helvetica-Bold) — eine Grotesk wie Inter, wenn auch
 * nicht dieselbe. Betroffen sind nur die drei befüllten Textfelder (Marke,
 * Modell-Kennung, Garantiedauer); Rahmen, GARAN-Schriftzug, EU-Flagge,
 * QR-Code und die 24 Sprachzeilen liegen als Pfade vor und bleiben
 * originalgetreu. Das PDF ist eine Zusatzleistung — maßgeblich bleibt die
 * Storefront-Darstellung des unveränderten SVGs.
 *
 * Wirft nie: Bei jedem Fehler wird null geliefert und im Channel
 * hug_eu_label geloggt.
 */
class GaranPdfGenerator
{
    private const CACHE_TTL = 3600;

    /**
     * PDF-Kernschrift, die der Vorlagenschrift Inter am nächsten kommt.
     * Ohne Verzeichnis-Präfix findet dompdf die Metrikdatei nicht (es sucht
     * relativ zum Arbeitsverzeichnis) und bliebe still bei Times.
     */
    private const PDF_FONT = 'Helvetica';

    private const PDF_FONT_BOLD = 'Helvetica-Bold';

    /**
     * Darstellungsbreite des Labels. Entspricht der druckbaren Breite von A4
     * hochkant — so passt das PDF auch dann in Originalgröße auf ein A4-Blatt,
     * wenn jemand es doch auf Normalpapier druckt.
     */
    private const LABEL_WIDTH_MM = 190.0;

    /** Schmaler Rand, damit das Label nicht am Blattrand klebt. */
    private const PAGE_MARGIN_MM = 5.0;

    /**
     * Zusätzliche Höhe der Seite. Ohne diesen Puffer schiebt dompdf das Bild
     * wegen Rundungen auf Punkt-Genauigkeit auf eine zweite, leere Seite.
     */
    private const PAGE_SLACK_MM = 1.0;

    /** Notbremse gegen absurde viewBox-Werte (sonst meterlange Seiten). */
    private const MAX_LABEL_HEIGHT_MM = 500.0;

    private const MM_TO_PT = 72 / 25.4;

    /** viewBox der offiziellen Vorlage: 269.29 × 283.46. */
    private const TEMPLATE_ASPECT_RATIO = 269.29 / 283.46;

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function generate(string $svg): ?string
    {
        if (trim($svg) === '') {
            return null;
        }

        // Inhaltsadressiert wie in GaranLabelService: identische Label-Daten
        // ergeben denselben Key, die TTL räumt nur Altlasten weg. Ohne Cache
        // würde jeder Aufruf die ~690 Pfade der Vorlage neu rastern.
        $cacheKey = 'hug-garan-pdf-' . md5($svg);

        $pdf = $this->cache->get($cacheKey, function (ItemInterface $item) use ($svg): string {
            $item->expiresAfter(self::CACHE_TTL);

            return $this->render($svg) ?? '';
        });

        return $pdf !== '' ? $pdf : null;
    }

    private function render(string $svg): ?string
    {
        try {
            // Defense-in-depth: Remote-Ressourcen (SSRF) und eingebettetes
            // PHP (RCE) explizit aus. Das SVG reist als data:-URI ein — der
            // einzige Kanal, den dompdf ohne Remote-Zugriff zulässt.
            $options = new Options();
            $options->setIsRemoteEnabled(false);
            $options->setIsPhpEnabled(false);

            $label = $this->labelSize($this->readAspectRatio($svg));

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($this->buildHtml($this->substituteFonts($svg, $options->getFontDir()), $label));
            $dompdf->setPaper([
                0.0,
                0.0,
                ($label['width'] + 2 * self::PAGE_MARGIN_MM) * self::MM_TO_PT,
                ($label['height'] + 2 * self::PAGE_MARGIN_MM + self::PAGE_SLACK_MM) * self::MM_TO_PT,
            ]);
            $dompdf->render();

            $output = $dompdf->output();

            return \is_string($output) && $output !== '' ? $output : null;
        } catch (\Throwable $exception) {
            $this->logger?->error('HugEuLabel: GARAN label PDF generation failed.', [
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Setzt auf jedem <text> ein font-family-Attribut mit ABSOLUTEM Pfad zur
     * Metrikdatei. Nur so findet dompdf die Schrift: php-svg-lib reicht den
     * Wert unverändert an Cpdf::selectFont() weiter, und dessen openFont()
     * sucht einen bloßen Namen wie „Helvetica" relativ zum Arbeitsverzeichnis
     * (nur Times-Roman liegt vorab als JSON-Cache bereit — daher der
     * Serifen-Fallback). Über die CSS-Klassen der Vorlage funktioniert das
     * nicht, das Attribut ist der einzige Weg.
     *
     * Bei jedem Zweifel bleibt das SVG unverändert (dann greift der
     * Times-Fallback wie zuvor).
     */
    private function substituteFonts(string $svg, string $fontDir): string
    {
        if (!is_file($fontDir . '/' . self::PDF_FONT . '.afm')) {
            return $svg;
        }

        $document = new \DOMDocument();
        if (!@$document->loadXML($svg)) {
            return $svg;
        }

        $boldClasses = $this->collectBoldClasses($document);

        foreach ($document->getElementsByTagName('text') as $text) {
            $classes = preg_split('/\s+/', trim($text->getAttribute('class'))) ?: [];
            $bold = \count(array_intersect($classes, $boldClasses)) > 0;

            $text->setAttribute(
                'font-family',
                $fontDir . '/' . ($bold ? self::PDF_FONT_BOLD : self::PDF_FONT),
            );
        }

        $rendered = $document->saveXML($document->documentElement);

        return \is_string($rendered) ? $rendered : $svg;
    }

    /**
     * Liest die <style>-Blöcke der Vorlage und liefert die Klassennamen, die
     * fett gesetzt sind — entweder per font-weight oder über einen
     * Schriftschnitt im Familiennamen (z. B. „Inter-ExtraBold").
     *
     * @return list<string>
     */
    private function collectBoldClasses(\DOMDocument $document): array
    {
        $classes = [];

        foreach ($document->getElementsByTagName('style') as $style) {
            preg_match_all('/([^{}]+)\{([^{}]*)\}/', $style->textContent, $rules, \PREG_SET_ORDER);

            foreach ($rules as $rule) {
                $isBold = preg_match('/font-weight:\s*(bold|[6-9]00)/i', $rule[2]) === 1
                    || preg_match('/font-family:[^;]*(bold|black|heavy)/i', $rule[2]) === 1;

                if (!$isBold) {
                    continue;
                }

                foreach (explode(',', $rule[1]) as $selector) {
                    $selector = trim($selector);

                    if (str_starts_with($selector, '.') && preg_match('/^\.[A-Za-z0-9_-]+$/', $selector) === 1) {
                        $classes[] = substr($selector, 1);
                    }
                }
            }
        }

        return array_values(array_unique($classes));
    }

    /**
     * Label-Größe in mm. Die Breite ist fix, die Höhe folgt dem
     * Seitenverhältnis; nur bei extrem hochformatigen Vorlagen greift die
     * Höhenbegrenzung.
     *
     * @return array{width: float, height: float}
     */
    private function labelSize(float $aspectRatio): array
    {
        $width = self::LABEL_WIDTH_MM;
        $height = $width / $aspectRatio;

        if ($height > self::MAX_LABEL_HEIGHT_MM) {
            $height = self::MAX_LABEL_HEIGHT_MM;
            $width = $height * $aspectRatio;
        }

        return ['width' => $width, 'height' => $height];
    }

    /**
     * Die Seite ist exakt so groß wie das Label plus Rand — deshalb füllt das
     * Bild sie ohne weitere Positionierung randlos aus.
     *
     * @param array{width: float, height: float} $label
     */
    private function buildHtml(string $svg, array $label): string
    {
        return \sprintf(
            '<html><head><meta charset="utf-8"><style>'
            . '@page { margin: %1$smm } body { margin: 0 }'
            . ' img { display: block; width: %2$smm; height: %3$smm }'
            . '</style></head><body><img src="data:image/svg+xml;base64,%4$s"></body></html>',
            self::PAGE_MARGIN_MM,
            round($label['width'], 2),
            round($label['height'], 2),
            base64_encode($svg),
        );
    }

    /**
     * Breite/Höhe aus der viewBox. Fällt auf das Seitenverhältnis der
     * offiziellen Vorlage zurück, wenn die viewBox fehlt oder unbrauchbar
     * ist — dann stimmt die Skalierung immer noch für das Standard-Label.
     */
    private function readAspectRatio(string $svg): float
    {
        if (preg_match('/\bviewBox\s*=\s*"([^"]+)"/i', $svg, $matches) !== 1) {
            return self::TEMPLATE_ASPECT_RATIO;
        }

        $parts = preg_split('/[\s,]+/', trim($matches[1])) ?: [];
        if (\count($parts) !== 4) {
            return self::TEMPLATE_ASPECT_RATIO;
        }

        $width = (float) $parts[2];
        $height = (float) $parts[3];

        return $width > 0.0 && $height > 0.0 ? $width / $height : self::TEMPLATE_ASPECT_RATIO;
    }
}
