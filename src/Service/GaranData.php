<?php

declare(strict_types=1);

namespace Hug\EuLabel\Service;

/**
 * Aufgelöste GARAN-Garantiedaten eines Produkts (Hersteller-Defaults bereits
 * mit den Produkt-Overrides verrechnet, Bedingungs-URL bereits aufgelöst).
 */
final readonly class GaranData
{
    public function __construct(
        public int $durationYears,
        public string $conditionsUrl,
        public string $manufacturerName,
        public ?string $conditionsMediaId = null,
    ) {
    }
}
