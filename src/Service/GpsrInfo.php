<?php

declare(strict_types=1);

namespace Hug\EuLabel\Service;

/**
 * Aufbereitete GPSR-Pflichtangaben (Art. 19 Verordnung (EU) 2023/988) für die
 * Storefront-Ausgabe. Alle Texte sind rohe Nutzereingaben — das Template
 * escapet sie (|nl2br) und erhält dabei die Zeilenumbrüche.
 */
final class GpsrInfo
{
    public function __construct(
        public readonly string $text,
        public readonly ?string $responsiblePerson,
        public readonly bool $isFallback,
    ) {
    }
}
