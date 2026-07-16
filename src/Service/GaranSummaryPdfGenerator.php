<?php

declare(strict_types=1);

namespace Hug\EuLabel\Service;

use Dompdf\Dompdf;
use Dompdf\Options;
use Psr\Log\LoggerInterface;
use Twig\Environment;

/**
 * Erzeugt die „Garantieinformationen zu Ihrer Bestellung"-Übersicht als PDF
 * (dompdf ist Shopware-Bestandteil). Liefert null bei jedem Fehler.
 */
class GaranSummaryPdfGenerator
{
    private const TEMPLATE = '@HugEuLabel/mail/garan-summary.html.twig';

    public function __construct(
        private readonly Environment $twig,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @param list<array{label: string, productNumber: string, data: GaranData}> $items
     */
    public function generate(array $items): ?string
    {
        try {
            $html = $this->twig->render(self::TEMPLATE, ['items' => $items]);

            // Defense-in-depth: Remote-Ressourcen (SSRF) und eingebettetes
            // PHP (RCE) explizit deaktivieren, damit ein künftiger
            // Library-Default die Sicherheit nicht still ändert. Das Template
            // enthält ohnehin nur Inline-CSS, keine externen Ressourcen.
            $options = new Options();
            $options->setIsRemoteEnabled(false);
            $options->setIsPhpEnabled(false);

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4');
            $dompdf->render();

            $output = $dompdf->output();

            return \is_string($output) && $output !== '' ? $output : null;
        } catch (\Throwable $exception) {
            $this->logger?->error('HugEuLabel: GARAN summary PDF generation failed.', [
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}
