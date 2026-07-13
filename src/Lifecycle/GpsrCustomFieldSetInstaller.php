<?php

declare(strict_types=1);

namespace Hug\EuLabel\Lifecycle;

use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetCollection;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetEntity;
use Shopware\Core\System\CustomField\CustomFieldCollection;
use Shopware\Core\System\CustomField\CustomFieldTypes;

/**
 * Legt das Custom Field Set "hug_gpsr" am Hersteller (product_manufacturer)
 * an — dort pflegt der Shop-Betreiber die GPSR-Pflichtangaben nach
 * Art. 19 Verordnung (EU) 2023/988.
 *
 * Wird von HugEuLabel::install()/update() aufgerufen (idempotent; ein Update
 * zieht fehlende Felder nach) und von uninstall() — dort werden die Felder
 * nur gelöscht, wenn der Nutzer keepUserData abgewählt hat.
 */
class GpsrCustomFieldSetInstaller
{
    public const SET_NAME = 'hug_gpsr';

    public const FIELD_MANUFACTURER_INFO = 'hug_gpsr_manufacturer_info';

    public const FIELD_RESPONSIBLE_PERSON = 'hug_gpsr_responsible_person';

    /**
     * @param EntityRepository<CustomFieldSetCollection> $customFieldSetRepository
     * @param EntityRepository<CustomFieldCollection> $customFieldRepository
     */
    public function __construct(
        private readonly EntityRepository $customFieldSetRepository,
        private readonly EntityRepository $customFieldRepository,
    ) {
    }

    public function ensureCustomFieldSet(Context $context): void
    {
        $existing = $this->findSet($context);

        if ($existing === null) {
            $this->customFieldSetRepository->create([[
                'name' => self::SET_NAME,
                'config' => [
                    'label' => [
                        'de-DE' => 'GPSR (Produktsicherheitsverordnung)',
                        'en-GB' => 'GPSR (General Product Safety Regulation)',
                    ],
                ],
                'customFields' => array_values($this->getFieldDefinitions()),
                'relations' => [
                    ['entityName' => ProductManufacturerDefinition::ENTITY_NAME],
                ],
            ]], $context);

            return;
        }

        $existingNames = [];
        foreach ($existing->getCustomFields() ?? [] as $field) {
            $existingNames[] = $field->getName();
        }

        $missing = [];
        foreach ($this->getFieldDefinitions() as $name => $definition) {
            if (!\in_array($name, $existingNames, true)) {
                $missing[] = $definition + ['customFieldSetId' => $existing->getId()];
            }
        }

        if ($missing !== []) {
            $this->customFieldRepository->create($missing, $context);
        }
    }

    public function uninstallCustomFieldSet(bool $keepUserData, Context $context): void
    {
        if ($keepUserData) {
            return;
        }

        $existing = $this->findSet($context);

        if ($existing === null) {
            return;
        }

        $this->customFieldSetRepository->delete([['id' => $existing->getId()]], $context);
    }

    private function findSet(Context $context): ?CustomFieldSetEntity
    {
        $criteria = new Criteria();
        $criteria->setTitle('hug-eu-label::gpsr-custom-field-set');
        $criteria->addFilter(new EqualsFilter('name', self::SET_NAME));
        $criteria->addAssociation('customFields');

        return $this->customFieldSetRepository->search($criteria, $context)->getEntities()->first();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getFieldDefinitions(): array
    {
        return [
            self::FIELD_MANUFACTURER_INFO => [
                'name' => self::FIELD_MANUFACTURER_INFO,
                'type' => CustomFieldTypes::TEXT,
                'config' => [
                    'componentName' => 'sw-textarea-field',
                    'customFieldType' => 'textArea',
                    'customFieldPosition' => 1,
                    'label' => [
                        'de-DE' => 'GPSR-Herstellerangaben (Name, Anschrift, E-Mail)',
                        'en-GB' => 'GPSR manufacturer info',
                    ],
                    'helpText' => [
                        'de-DE' => 'Pflichtangaben nach Art. 19 GPSR: Name/Handelsname, Postanschrift und elektronische Adresse des Herstellers. Zeilenumbrüche bleiben in der Storefront erhalten.',
                        'en-GB' => 'Mandatory information under Art. 19 GPSR: name/trade name, postal address and electronic address of the manufacturer. Line breaks are preserved in the storefront.',
                    ],
                ],
            ],
            self::FIELD_RESPONSIBLE_PERSON => [
                'name' => self::FIELD_RESPONSIBLE_PERSON,
                'type' => CustomFieldTypes::TEXT,
                'config' => [
                    'componentName' => 'sw-textarea-field',
                    'customFieldType' => 'textArea',
                    'customFieldPosition' => 2,
                    'label' => [
                        'de-DE' => 'EU-Verantwortliche Person (nur bei Nicht-EU-Hersteller)',
                        'en-GB' => 'EU responsible person (non-EU manufacturers only)',
                    ],
                    'helpText' => [
                        'de-DE' => 'Nur ausfüllen, wenn der Hersteller außerhalb der EU sitzt: Name, Postanschrift und elektronische Adresse der verantwortlichen Person in der EU (Art. 16 GPSR). Wird nur zusammen mit den GPSR-Herstellerangaben angezeigt.',
                        'en-GB' => 'Fill in only for manufacturers outside the EU: name, postal address and electronic address of the responsible person in the EU (Art. 16 GPSR). Only displayed together with the GPSR manufacturer info.',
                    ],
                ],
            ],
        ];
    }
}
