<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Inventory;

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Inventory\InventoryServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

abstract class InventoryTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        OwnerContext::clearOverride();
    }

    protected function getPackageProviders($app): array
    {
        $providers = parent::getPackageProviders($app);
        $providers[] = InventoryServiceProvider::class;

        return $providers;
    }

    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        $this->loadMigrationsFrom(__DIR__ . '/../../../packages/inventory/database/migrations');
    }

    protected function setUpDatabase(): void
    {
        parent::setUpDatabase();

        Schema::dropIfExists('inventory_test_products');
        Schema::create('inventory_test_products', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->timestamps();
        });
    }
}
