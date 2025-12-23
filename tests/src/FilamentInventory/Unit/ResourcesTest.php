<?php

declare(strict_types=1);

use AIArmada\FilamentInventory\Resources\InventoryAllocationResource;
use AIArmada\FilamentInventory\Resources\InventoryBatchResource;
use AIArmada\FilamentInventory\Resources\InventoryLevelResource;
use AIArmada\FilamentInventory\Resources\InventoryLocationResource;
use AIArmada\FilamentInventory\Resources\InventoryMovementResource;
use AIArmada\FilamentInventory\Resources\InventorySerialResource;
use AIArmada\Commerce\Tests\FilamentInventory\Fixtures\TestOwner;
use AIArmada\Commerce\Tests\FilamentInventory\Fixtures\TestOwnerResolver;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryBatch;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventorySerial;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Contracts\TranslatableContentDriver;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Component as LivewireComponent;

if (! function_exists('filamentInventory_makeSchemaLivewire')) {
    function filamentInventory_makeSchemaLivewire(): LivewireComponent & HasSchemas
    {
        return new class extends LivewireComponent implements HasSchemas
        {
            public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
            {
                return null;
            }

            public function getOldSchemaState(string $statePath): mixed
            {
                return null;
            }

            public function getSchemaComponent(string $key, bool $withHidden = false, array $skipComponentsChildContainersWhileSearching = []): Filament\Schemas\Components\Component | Filament\Actions\Action | Filament\Actions\ActionGroup | null
            {
                return null;
            }

            public function getSchema(string $name): ?Schema
            {
                return null;
            }

            public function currentlyValidatingSchema(?Schema $schema): void {}

            public function getDefaultTestingSchemaName(): ?string
            {
                return null;
            }
        };
    }
}

beforeEach(function (): void {
    config()->set('inventory.owner.enabled', false);
});

describe('InventoryLocationResource', function (): void {
    it('builds schemas and table', function (): void {
        $schema = InventoryLocationResource::form(Schema::make(filamentInventory_makeSchemaLivewire()));
        expect($schema->getComponents())->not()->toBeEmpty();

        $infolist = InventoryLocationResource::infolist(Schema::make(filamentInventory_makeSchemaLivewire()));
        expect($infolist->getComponents())->not()->toBeEmpty();

        $livewire = Mockery::mock(HasTable::class);
        $table = InventoryLocationResource::table(Table::make($livewire));

        expect($table->getColumns())->not()->toBeEmpty();
        expect($table->getRecordActions())->not()->toBeEmpty();
    });

    it('defines CRUD pages', function (): void {
        $pages = InventoryLocationResource::getPages();

        expect($pages)->toHaveKey('index');
        expect($pages)->toHaveKey('create');
        expect($pages)->toHaveKey('view');
        expect($pages)->toHaveKey('edit');
    });
});

describe('InventoryLevelResource', function (): void {
    it('builds schemas and table', function (): void {
        $schema = InventoryLevelResource::form(Schema::make(filamentInventory_makeSchemaLivewire()));
        expect($schema->getComponents())->not()->toBeEmpty();

        $infolist = InventoryLevelResource::infolist(Schema::make(filamentInventory_makeSchemaLivewire()));
        expect($infolist->getComponents())->not()->toBeEmpty();

        $livewire = Mockery::mock(HasTable::class);
        $table = InventoryLevelResource::table(Table::make($livewire));

        expect($table->getColumns())->not()->toBeEmpty();
        expect($table->getRecordActions())->not()->toBeEmpty();
    });

    it('computes navigation badge from low-stock query', function (): void {
        $location = InventoryLocation::factory()->create();

        InventoryLevel::create([
            'inventoryable_type' => 'Product',
            'inventoryable_id' => 'sku-low',
            'location_id' => $location->id,
            'quantity_on_hand' => 5,
            'quantity_reserved' => 1,
            'reorder_point' => 10,
        ]);

        expect(InventoryLevelResource::getNavigationBadge())->toBe('1');
        expect(InventoryLevelResource::getNavigationBadgeColor())->toBe('warning');
    });
});

describe('InventoryMovementResource', function (): void {
    it('builds schemas and table and defines pages', function (): void {
        $infolist = InventoryMovementResource::infolist(Schema::make(filamentInventory_makeSchemaLivewire()));
        expect($infolist->getComponents())->not()->toBeEmpty();

        $livewire = Mockery::mock(HasTable::class);
        $table = InventoryMovementResource::table(Table::make($livewire));

        expect($table->getColumns())->not()->toBeEmpty();
        expect($table->getRecordActions())->not()->toBeEmpty();

        $pages = InventoryMovementResource::getPages();
        expect($pages)->toHaveKey('index');
        expect($pages)->toHaveKey('view');
    });
});

describe('InventoryAllocationResource', function (): void {
    it('builds schemas and table and defines pages', function (): void {
        $infolist = InventoryAllocationResource::infolist(Schema::make(filamentInventory_makeSchemaLivewire()));
        expect($infolist->getComponents())->not()->toBeEmpty();

        $livewire = Mockery::mock(HasTable::class);
        $table = InventoryAllocationResource::table(Table::make($livewire));

        expect($table->getColumns())->not()->toBeEmpty();
        expect($table->getRecordActions())->not()->toBeEmpty();

        $pages = InventoryAllocationResource::getPages();
        expect($pages)->toHaveKey('index');
        expect($pages)->toHaveKey('view');
    });
});

describe('InventoryBatchResource', function (): void {
    it('builds schemas and table and defines pages', function (): void {
        $schema = InventoryBatchResource::form(Schema::make(filamentInventory_makeSchemaLivewire()));
        expect($schema->getComponents())->not()->toBeEmpty();

        $livewire = Mockery::mock(HasTable::class);
        $table = InventoryBatchResource::table(Table::make($livewire));

        expect($table->getColumns())->not()->toBeEmpty();
        expect($table->getRecordActions())->not()->toBeEmpty();

        $pages = InventoryBatchResource::getPages();
        expect($pages)->toHaveKey('index');
        expect($pages)->toHaveKey('view');
    });
});

describe('InventorySerialResource', function (): void {
    it('builds schemas and table and defines pages', function (): void {
        $schema = InventorySerialResource::form(Schema::make(filamentInventory_makeSchemaLivewire()));
        expect($schema->getComponents())->not()->toBeEmpty();

        $livewire = Mockery::mock(HasTable::class);
        $table = InventorySerialResource::table(Table::make($livewire));

        expect($table->getColumns())->not()->toBeEmpty();
        expect($table->getRecordActions())->not()->toBeEmpty();

        $pages = InventorySerialResource::getPages();
        expect($pages)->toHaveKey('index');
        expect($pages)->toHaveKey('view');
    });

    it('does not eager-load a cross-tenant batch', function (): void {
        SchemaFacade::dropIfExists('filament_inventory_test_owners');

        SchemaFacade::create('filament_inventory_test_owners', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->timestamps();
        });

        $ownerA = TestOwner::create(['name' => 'Owner A']);
        $ownerB = TestOwner::create(['name' => 'Owner B']);

        config()->set('inventory.owner.enabled', false);

        $locationA = InventoryLocation::factory()->create([
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => $ownerA->getKey(),
        ]);

        $locationB = InventoryLocation::factory()->create([
            'owner_type' => $ownerB->getMorphClass(),
            'owner_id' => $ownerB->getKey(),
        ]);

        $batchB = InventoryBatch::factory()->create([
            'location_id' => $locationB->id,
        ]);

        $serialA = InventorySerial::factory()->create([
            'location_id' => $locationA->id,
            'batch_id' => $batchB->id,
        ]);

        config()->set('inventory.owner.enabled', true);
        config()->set('inventory.owner.include_global', false);

        app()->bind(
            AIArmada\CommerceSupport\Contracts\OwnerResolverInterface::class,
            fn () => new TestOwnerResolver($ownerA),
        );

        /** @var InventorySerial $serial */
        $serial = InventorySerialResource::getEloquentQuery()->whereKey($serialA->id)->firstOrFail();

        expect($serial->location_id)->toBe($locationA->id);
        expect($serial->batch)->toBeNull();
    });
});
