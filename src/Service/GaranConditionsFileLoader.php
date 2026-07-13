<?php

declare(strict_types=1);

namespace Hug\EuLabel\Service;

use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

/**
 * Lädt den Datei-Inhalt eines Garantiebedingungs-PDFs aus dem Media-Bestand
 * (für den Mail-Anhang). Liefert null bei jedem Fehler — der Mailversand
 * darf daran nie scheitern.
 */
class GaranConditionsFileLoader
{
    /**
     * @param EntityRepository<MediaCollection> $mediaRepository
     */
    public function __construct(
        private readonly EntityRepository $mediaRepository,
        private readonly FilesystemOperator $publicFilesystem,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function load(string $mediaId, Context $context): ?string
    {
        try {
            $media = $this->mediaRepository->search(new Criteria([$mediaId]), $context)->getEntities()->first();
            $path = $media?->getPath();

            if ($path === null || $path === '') {
                return null;
            }

            return $this->publicFilesystem->read($path);
        } catch (\Throwable $exception) {
            $this->logger?->warning('HugEuLabel: GARAN conditions media could not be read.', [
                'mediaId' => $mediaId,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}
