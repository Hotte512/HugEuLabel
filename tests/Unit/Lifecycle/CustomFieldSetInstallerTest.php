<?php

declare(strict_types=1);

namespace Hug\EuLabel\Tests\Unit\Lifecycle;

use Hug\EuLabel\Lifecycle\CustomFieldSetInstaller;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetCollection;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

final class CustomFieldSetInstallerTest extends TestCase
{
    private Context $context;

    protected function setUp(): void
    {
        $this->context = new Context(new SystemSource());
    }

    public function testUninstallKeepsCustomFieldsWhenUserDataIsKept(): void
    {
        // Queue leer: bei keepUserData darf gar nicht erst gesucht/gelöscht werden.
        /** @var StaticEntityRepository<CustomFieldSetCollection> $setRepository */
        $setRepository = new StaticEntityRepository([]);

        (new CustomFieldSetInstaller($setRepository))->uninstall(true, $this->context);

        self::assertSame([], $setRepository->deletes);
    }

    public function testUninstallDeletesBothGaranSetsWhenUserDataIsDiscarded(): void
    {
        $manufacturerSet = $this->createSet(CustomFieldSetInstaller::SET_MANUFACTURER);
        $productSet = $this->createSet(CustomFieldSetInstaller::SET_PRODUCT);

        /** @var StaticEntityRepository<CustomFieldSetCollection> $setRepository */
        $setRepository = new StaticEntityRepository([
            new CustomFieldSetCollection([$manufacturerSet, $productSet]),
        ]);

        (new CustomFieldSetInstaller($setRepository))->uninstall(false, $this->context);

        self::assertSame([
            [
                ['id' => $manufacturerSet->getId()],
                ['id' => $productSet->getId()],
            ],
        ], $setRepository->deletes);
    }

    public function testUninstallDoesNothingWhenNoSetsExist(): void
    {
        /** @var StaticEntityRepository<CustomFieldSetCollection> $setRepository */
        $setRepository = new StaticEntityRepository([new CustomFieldSetCollection([])]);

        (new CustomFieldSetInstaller($setRepository))->uninstall(false, $this->context);

        self::assertSame([], $setRepository->deletes);
    }

    private function createSet(string $name): CustomFieldSetEntity
    {
        $set = new CustomFieldSetEntity();
        $set->setId(Uuid::randomHex());
        $set->setUniqueIdentifier($set->getId());
        $set->setName($name);

        return $set;
    }
}
