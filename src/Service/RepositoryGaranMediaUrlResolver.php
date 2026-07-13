<?php

declare(strict_types=1);

namespace Hug\EuLabel\Service;

use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

/**
 * Default-Implementierung: lädt den Media-Eintrag und nutzt dessen vom
 * MediaUrlLoader befüllte öffentliche URL. Ergebnis pro Request gecacht
 * (Garantiebedingungs-PDFs wiederholen sich über Produkte hinweg).
 */
class RepositoryGaranMediaUrlResolver implements GaranMediaUrlResolver
{
    /** @var array<string, ?string> */
    private array $cache = [];

    /**
     * @param EntityRepository<MediaCollection> $mediaRepository
     */
    public function __construct(
        private readonly EntityRepository $mediaRepository,
    ) {
    }

    public function getUrl(string $mediaId, Context $context): ?string
    {
        if (\array_key_exists($mediaId, $this->cache)) {
            return $this->cache[$mediaId];
        }

        $media = $this->mediaRepository->search(new Criteria([$mediaId]), $context)->getEntities()->first();
        $url = $media?->getUrl();

        return $this->cache[$mediaId] = ($url !== null && $url !== '' ? $url : null);
    }
}
