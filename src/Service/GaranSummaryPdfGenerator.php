<?php

declare(strict_types=1);

namespace Hug\EuLabel\Service;

use Dompdf\Dompdf;
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

            $dompdf = new Dompdf();
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
