<?php

declare(strict_types=1);

namespace Hug\EuLabel\Service;

use Shopware\Core\Framework\Context;

/**
 * Löst die öffentliche URL eines Media-Eintrags auf (Garantiebedingungs-PDF).
 * Als schmales Interface geschnitten, damit der GaranDataResolver ohne
 * Media-Infrastruktur unit-testbar bleibt.
 */
interface GaranMediaUrlResolver
{
    public function getUrl(string $mediaId, Context $context): ?string;
}
