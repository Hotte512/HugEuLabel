<?php

declare(strict_types=1);

namespace Hug\EuLabel;

use Hug\EuLabel\Lifecycle\CustomFieldSetInstaller;
use Hug\EuLabel\Lifecycle\GpsrCustomFieldSetInstaller;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;

class HugEuLabel extends Plugin
{
    public function install(InstallContext $installContext): void
    {
        $this->createGaranCustomFieldSetInstaller()->install($installContext->getContext());
        $this->createGpsrCustomFieldSetInstaller()->ensureCustomFieldSet($installContext->getContext());
    }

    public function update(UpdateContext $updateContext): void
    {
        $this->createGaranCustomFieldSetInstaller()->install($updateContext->getContext());
        $this->createGpsrCustomFieldSetInstaller()->ensureCustomFieldSet($updateContext->getContext());
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        $this->createGaranCustomFieldSetInstaller()->uninstall(
            $uninstallContext->keepUserData(),
            $uninstallContext->getContext(),
        );
        $this->createGpsrCustomFieldSetInstaller()->uninstallCustomFieldSet(
            $uninstallContext->keepUserData(),
            $uninstallContext->getContext(),
        );
    }

    private function createGaranCustomFieldSetInstaller(): CustomFieldSetInstaller
    {
        \assert($this->container !== null);

        /** @var \Shopware\Core\Framework\DataAbstractionLayer\EntityRepository<\Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetCollection> $repository */
        $repository = $this->container->get('custom_field_set.repository');

        return new CustomFieldSetInstaller($repository);
    }

    /**
     * Bewusst manuell verdrahtet statt über services.xml: Während
     * install/uninstall sind die Plugin-Services nicht zuverlässig im
     * Container registriert, die Core-Repositories schon.
     */
    private function createGpsrCustomFieldSetInstaller(): GpsrCustomFieldSetInstaller
    {
        \assert($this->container !== null);

        /** @var \Shopware\Core\Framework\DataAbstractionLayer\EntityRepository<\Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetCollection> $customFieldSetRepository */
        $customFieldSetRepository = $this->container->get('custom_field_set.repository');

        /** @var \Shopware\Core\Framework\DataAbstractionLayer\EntityRepository<\Shopware\Core\System\CustomField\CustomFieldCollection> $customFieldRepository */
        $customFieldRepository = $this->container->get('custom_field.repository');

        return new GpsrCustomFieldSetInstaller($customFieldSetRepository, $customFieldRepository);
    }
}
