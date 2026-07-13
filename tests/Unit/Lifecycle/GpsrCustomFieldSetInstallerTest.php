<?php

declare(strict_types=1);

namespace Hug\EuLabel\Tests\Unit\Lifecycle;

use Hug\EuLabel\Lifecycle\GpsrCustomFieldSetInstaller;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetCollection;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetEntity;
use Shopware\Core\System\CustomField\CustomFieldCollection;
use Shopware\Core\System\CustomField\CustomFieldEntity;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

final class GpsrCustomFieldSetInstallerTest extends TestCase
{
    private Context $context;

    protected function setUp(): void
    {
        $this->context = new Context(new SystemSource());
    }

    public function testInstallCreatesSetWithBothFieldsWhenMissing(): void
    {
        /** @var StaticEntityRepository<CustomFieldSetCollection> $setRepository */
        $setRepository = new StaticEntityRepository([[]]);
        /** @var StaticEntityRepository<CustomFieldCollection> $fieldRepository */
        $fieldRepository = new StaticEntityRepository([]);

        $installer = new GpsrCustomFieldSetInstaller($setRepository, $fieldRepository);
        $installer->ensureCustomFieldSet($this->context);

        self::assertCount(1, $setRepository->creates);

        /** @var array{name: string, config: array<string, mixed>, customFields: list<array{name: string, type: string, config: array{componentName: string, customFieldType: string, label: array<string, string>}}>, relations: list<array{entityName: string}>} $payload */
        $payload = $setRepository->creates[0][0];

        self::assertSame(GpsrCustomFieldSetInstaller::SET_NAME, $payload['name']);
        self::assertSame(
            [['entityName' => 'product_manufacturer']],
            $payload['relations'],
        );

        $fieldNames = array_column($payload['customFields'], 'name');
        self::assertSame(
            [
                GpsrCustomFieldSetInstaller::FIELD_MANUFACTURER_INFO,
                GpsrCustomFieldSetInstaller::FIELD_RESPONSIBLE_PERSON,
            ],
            $fieldNames,
        );

        foreach ($payload['customFields'] as $field) {
            self::assertSame(CustomFieldTypes::TEXT, $field['type']);
            self::assertSame('sw-textarea-field', $field['config']['componentName']);
            self::assertSame('textArea', $field['config']['customFieldType']);
            self::assertArrayHasKey('de-DE', $field['config']['label']);
            self::assertArrayHasKey('en-GB', $field['config']['label']);
        }

        // Kein Nachziehen einzelner Felder nötig, wenn das Set frisch angelegt wird.
        self::assertSame([], $fieldRepository->creates);
    }

    public function testInstallIsIdempotentWhenSetAndFieldsExist(): void
    {
        $existingSet = $this->createSetEntity([
            GpsrCustomFieldSetInstaller::FIELD_MANUFACTURER_INFO,
            GpsrCustomFieldSetInstaller::FIELD_RESPONSIBLE_PERSON,
        ]);

        /** @var StaticEntityRepository<CustomFieldSetCollection> $setRepository */
        $setRepository = new StaticEntityRepository([[$existingSet]]);
        /** @var StaticEntityRepository<CustomFieldCollection> $fieldRepository */
        $fieldRepository = new StaticEntityRepository([]);

        $installer = new GpsrCustomFieldSetInstaller($setRepository, $fieldRepository);
        $installer->ensureCustomFieldSet($this->context);

        self::assertSame([], $setRepository->creates);
        self::assertSame([], $setRepository->upserts);
        self::assertSame([], $fieldRepository->creates);
    }

    public function testUpdateAddsMissingFieldToExistingSet(): void
    {
        $existingSet = $this->createSetEntity([
            GpsrCustomFieldSetInstaller::FIELD_MANUFACTURER_INFO,
        ]);

        /** @var StaticEntityRepository<CustomFieldSetCollection> $setRepository */
        $setRepository = new StaticEntityRepository([[$existingSet]]);
        /** @var StaticEntityRepository<CustomFieldCollection> $fieldRepository */
        $fieldRepository = new StaticEntityRepository([]);

        $installer = new GpsrCustomFieldSetInstaller($setRepository, $fieldRepository);
        $installer->ensureCustomFieldSet($this->context);

        self::assertSame([], $setRepository->creates);
        self::assertCount(1, $fieldRepository->creates);

        /** @var array{name: string, customFieldSetId: string} $created */
        $created = $fieldRepository->creates[0][0];
        self::assertSame(GpsrCustomFieldSetInstaller::FIELD_RESPONSIBLE_PERSON, $created['name']);
        self::assertSame($existingSet->getId(), $created['customFieldSetId']);
    }

    public function testUninstallKeepsSetWhenUserDataIsKept(): void
    {
        // Leere Such-Queues: jeder Repository-Zugriff würde hier fehlschlagen —
        // beweist, dass bei keepUserData nichts angefasst wird.
        /** @var StaticEntityRepository<CustomFieldSetCollection> $setRepository */
        $setRepository = new StaticEntityRepository([]);
        /** @var StaticEntityRepository<CustomFieldCollection> $fieldRepository */
        $fieldRepository = new StaticEntityRepository([]);

        $installer = new GpsrCustomFieldSetInstaller($setRepository, $fieldRepository);
        $installer->uninstallCustomFieldSet(true, $this->context);

        self::assertSame([], $setRepository->deletes);
    }

    public function testUninstallDeletesSetWhenUserDataIsDiscarded(): void
    {
        $existingSet = $this->createSetEntity([
            GpsrCustomFieldSetInstaller::FIELD_MANUFACTURER_INFO,
            GpsrCustomFieldSetInstaller::FIELD_RESPONSIBLE_PERSON,
        ]);

        /** @var StaticEntityRepository<CustomFieldSetCollection> $setRepository */
        $setRepository = new StaticEntityRepository([[$existingSet]]);
        /** @var StaticEntityRepository<CustomFieldCollection> $fieldRepository */
        $fieldRepository = new StaticEntityRepository([]);

        $installer = new GpsrCustomFieldSetInstaller($setRepository, $fieldRepository);
        $installer->uninstallCustomFieldSet(false, $this->context);

        self::assertSame([[['id' => $existingSet->getId()]]], $setRepository->deletes);
    }

    public function testUninstallDoesNothingWhenSetIsMissing(): void
    {
        /** @var StaticEntityRepository<CustomFieldSetCollection> $setRepository */
        $setRepository = new StaticEntityRepository([[]]);
        /** @var StaticEntityRepository<CustomFieldCollection> $fieldRepository */
        $fieldRepository = new StaticEntityRepository([]);

        $installer = new GpsrCustomFieldSetInstaller($setRepository, $fieldRepository);
        $installer->uninstallCustomFieldSet(false, $this->context);

        self::assertSame([], $setRepository->deletes);
    }

    /**
     * @param list<string> $fieldNames
     */
    private function createSetEntity(array $fieldNames): CustomFieldSetEntity
    {
        $set = new CustomFieldSetEntity();
        $set->setId(Uuid::randomHex());
        $set->setUniqueIdentifier($set->getId());
        $set->setName(GpsrCustomFieldSetInstaller::SET_NAME);

        $fields = new CustomFieldCollection();
        foreach ($fieldNames as $fieldName) {
            $field = new CustomFieldEntity();
            $field->setId(Uuid::randomHex());
            $field->setUniqueIdentifier($field->getId());
            $field->setName($fieldName);
            $fields->add($field);
        }

        $set->setCustomFields($fields);

        return $set;
    }
}
