<?php

declare(strict_types=1);

namespace Hug\EuLabel\Service;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Resolves the locale-specific EU warranty label files shipped in
 * src/Resources/public/labels/{locale}/.
 *
 * Additional languages are supported by adding a new locale folder with the
 * two label files — no code change required.
 */
class LabelProvider
{
    public const FORMAT_SVG = 'svg';

    public const FORMAT_PDF = 'pdf';

    public const FALLBACK_LOCALE = 'de-DE';

    private const FILE_BASENAME = 'gewaehrleistungslabel';

    private const SUPPORTED_FORMATS = [self::FORMAT_SVG, self::FORMAT_PDF];

    private readonly string $labelsBasePath;

    /**
     * @var array<string, string>
     */
    private array $localeCache = [];

    /**
     * @param EntityRepository<LanguageCollection> $languageRepository
     */
    public function __construct(
        private readonly EntityRepository $languageRepository,
        private readonly ?LoggerInterface $logger = null,
        ?string $labelsBasePath = null,
    ) {
        $this->labelsBasePath = $labelsBasePath ?? \dirname(__DIR__) . '/Resources/public/labels';
    }

    /**
     * Returns the label path relative to the plugin's public asset root,
     * e.g. "labels/de-DE/gewaehrleistungslabel.svg" (usable with
     * asset('bundles/hugeulabel/' ~ path)), or null if no file exists.
     */
    public function getLabelPath(string $format, Context|SalesChannelContext $context): ?string
    {
        $locale = $this->resolveExistingLocale($format, $context);

        if ($locale === null) {
            return null;
        }

        return 'labels/' . $locale . '/' . self::FILE_BASENAME . '.' . $format;
    }

    /**
     * Returns the absolute filesystem path to the label PDF (for mail
     * attachments), or null if no PDF exists.
     */
    public function getAbsolutePdfPath(Context|SalesChannelContext $context): ?string
    {
        $locale = $this->resolveExistingLocale(self::FORMAT_PDF, $context);

        if ($locale === null) {
            return null;
        }

        return $this->buildAbsolutePath($locale, self::FORMAT_PDF);
    }

    private function resolveExistingLocale(string $format, Context|SalesChannelContext $context): ?string
    {
        if (!\in_array($format, self::SUPPORTED_FORMATS, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Unsupported label format "%s". Supported formats: %s',
                $format,
                implode(', ', self::SUPPORTED_FORMATS),
            ));
        }

        $locale = $this->resolveLocale($context);

        foreach (array_unique([$locale, self::FALLBACK_LOCALE]) as $candidate) {
            if (is_file($this->buildAbsolutePath($candidate, $format))) {
                return $candidate;
            }
        }

        $this->logger?->warning('HugEuLabel: no warranty label file found.', [
            'format' => $format,
            'locale' => $locale,
            'labelsBasePath' => $this->labelsBasePath,
        ]);

        return null;
    }

    private function buildAbsolutePath(string $locale, string $format): string
    {
        return $this->labelsBasePath . '/' . $locale . '/' . self::FILE_BASENAME . '.' . $format;
    }

    private function resolveLocale(Context|SalesChannelContext $context): string
    {
        if ($context instanceof SalesChannelContext) {
            $context = $context->getContext();
        }

        $languageId = $context->getLanguageId();

        if (isset($this->localeCache[$languageId])) {
            return $this->localeCache[$languageId];
        }

        try {
            $criteria = new Criteria([$languageId]);
            $criteria->setTitle('hug-eu-label::resolve-locale');
            $criteria->addAssociation('locale');

            $language = $this->languageRepository->search($criteria, $context)->getEntities()->first();
            $code = $language?->getLocale()?->getCode();
        } catch (\Throwable $exception) {
            $this->logger?->warning('HugEuLabel: locale lookup failed, falling back to de-DE.', [
                'languageId' => $languageId,
                'exception' => $exception,
            ]);
            $code = null;
        }

        // Defensive: locale codes come from the database, but they end up in a
        // filesystem path — only accept the canonical xx-XX shape.
        if ($code === null || preg_match('/^[a-z]{2,3}-[A-Z]{2}$/', $code) !== 1) {
            $code = self::FALLBACK_LOCALE;
        }

        return $this->localeCache[$languageId] = $code;
    }
}
