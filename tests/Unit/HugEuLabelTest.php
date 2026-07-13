<?php

declare(strict_types=1);

namespace Hug\EuLabel\Tests\Unit;

use Hug\EuLabel\HugEuLabel;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin;

final class HugEuLabelTest extends TestCase
{
    public function testPluginClassExtendsShopwarePlugin(): void
    {
        self::assertTrue(is_subclass_of(HugEuLabel::class, Plugin::class));
    }
}
