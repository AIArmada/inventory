<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Jnt;

use AIArmada\Commerce\Tests\TestCase;

abstract class JntTestCase extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        $this->loadMigrationsFrom(__DIR__.'/../../../packages/jnt/database/migrations');
    }
}
