<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentShipping\Resources\ReturnAuthorizationResource\RelationManagers\ItemsRelationManager;
use AIArmada\FilamentShipping\Resources\ShipmentResource\RelationManagers\EventsRelationManager;
use AIArmada\FilamentShipping\Resources\ShipmentResource\RelationManagers\ItemsRelationManager as ShipmentItemsRelationManager;
use AIArmada\FilamentShipping\Resources\ShippingZoneResource\RelationManagers\RatesRelationManager;
use AIArmada\FilamentAuthz\Models\Permission;
use AIArmada\Shipping\Models\ReturnAuthorization;
use AIArmada\Shipping\Models\ReturnAuthorizationItem;
use AIArmada\Shipping\Models\ShippingRate;
use AIArmada\Shipping\Models\ShippingZone;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Contracts\TranslatableContentDriver;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Component as LivewireComponent;
use AIArmada\Commerce\Tests\Fixtures\Models\User;

if (! function_exists('filamentShipping_makeSchemaLivewire')) {
    function filamentShipping_makeSchemaLivewire(): LivewireComponent & HasSchemas
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

uses(TestCase::class);

// ============================================
// Relation Managers Tests
// ============================================

describe('RatesRelationManager', function (): void {
    it('can be instantiated', function (): void {
        $manager = new RatesRelationManager;

        expect($manager)->toBeInstanceOf(RatesRelationManager::class);
    });

    it('has correct relationship name', function (): void {
        $reflection = new ReflectionProperty(RatesRelationManager::class, 'relationship');
        $reflection->setAccessible(true);

        expect($reflection->getValue(null))->toBe('rates');
    });

    it('has correct record title attribute', function (): void {
        $reflection = new ReflectionProperty(RatesRelationManager::class, 'recordTitleAttribute');
        $reflection->setAccessible(true);

        expect($reflection->getValue(null))->toBe('name');
    });

    it('builds rates relation manager form schema', function (): void {
        $manager = new RatesRelationManager;

        $schema = $manager->form(Schema::make(filamentShipping_makeSchemaLivewire()));

        expect($schema->getComponents())->not()->toBeEmpty();
    });

    it('builds rates relation manager table definition', function (): void {
        $manager = new RatesRelationManager;

        $livewire = Mockery::mock(HasTable::class);
        $table = $manager->table(Table::make($livewire));

        expect($table->getColumns())->not()->toBeEmpty();
        expect($table->getRecordActions())->not()->toBeEmpty();
    });
});

describe('ReturnAuthorizationItemsRelationManager', function (): void {
    it('can be instantiated', function (): void {
        $manager = new ItemsRelationManager;

        expect($manager)->toBeInstanceOf(ItemsRelationManager::class);
    });

    it('has correct relationship name', function (): void {
        $reflection = new ReflectionProperty(ItemsRelationManager::class, 'relationship');
        $reflection->setAccessible(true);

        expect($reflection->getValue(null))->toBe('items');
    });

    it('has correct record title attribute', function (): void {
        $reflection = new ReflectionProperty(ItemsRelationManager::class, 'recordTitleAttribute');
        $reflection->setAccessible(true);

        expect($reflection->getValue(null))->toBe('name');
    });

    it('builds items relation manager table definition', function (): void {
        $manager = new ItemsRelationManager;

        $livewire = Mockery::mock(HasTable::class);
        $table = $manager->table(Table::make($livewire));

        expect($table->getColumns())->not()->toBeEmpty();
    });
});

describe('ShipmentResource relation managers', function (): void {
    it('builds shipment items relation manager table definition', function (): void {
        $manager = new ShipmentItemsRelationManager;

        $livewire = Mockery::mock(HasTable::class);
        $table = $manager->table(Table::make($livewire));

        expect($table->getColumns())->not()->toBeEmpty();
        expect($table->getRecordActions())->not()->toBeEmpty();
    });

    it('builds shipment events relation manager table definition', function (): void {
        $manager = new EventsRelationManager;

        $livewire = Mockery::mock(HasTable::class);
        $table = $manager->table(Table::make($livewire));

        expect($table->getColumns())->not()->toBeEmpty();
    });
});

describe('Relation manager write action authorization', function (): void {
    it('hides RatesRelationManager write actions without manageRates permission', function (): void {
        Permission::firstOrCreate(['name' => 'shipping.zones.manage-rates', 'guard_name' => 'web']);

        $user = User::query()->create([
            'name' => 'No Permissions',
            'email' => 'no-perms@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($user);

        $zone = ShippingZone::query()->create([
            'name' => 'Zone A',
            'code' => 'ZONE-A',
            'type' => 'country',
            'countries' => ['MY'],
        ]);

        $rate = ShippingRate::query()->create([
            'zone_id' => $zone->getKey(),
            'method_code' => 'standard',
            'name' => 'Standard',
            'calculation_type' => 'flat',
            'base_rate' => 500,
        ]);

        $manager = new RatesRelationManager;
        $manager->ownerRecord = $zone;

        $livewire = Mockery::mock(HasTable::class);
        $table = $manager->table(Table::make($livewire));

        $create = $table->getAction('create');
        $edit = $table->getAction('edit');
        $delete = $table->getAction('delete');
        $deleteBulk = $table->getBulkAction('delete');

        expect($create)->not()->toBeNull();
        expect($edit)->not()->toBeNull();
        expect($delete)->not()->toBeNull();
        expect($deleteBulk)->not()->toBeNull();

        expect($create->isAuthorized())->toBeFalse();
        expect($edit->record($rate)->isAuthorized())->toBeFalse();
        expect($delete->record($rate)->isAuthorized())->toBeFalse();
        expect($deleteBulk->isAuthorized())->toBeFalse();
    });

    it('shows RatesRelationManager write actions with manageRates permission', function (): void {
        Permission::firstOrCreate(['name' => 'shipping.zones.manage-rates', 'guard_name' => 'web']);

        $user = User::query()->create([
            'name' => 'Rates Manager',
            'email' => 'rates-manager@example.com',
            'password' => bcrypt('password'),
        ]);

        $user->givePermissionTo('shipping.zones.manage-rates');

        $this->actingAs($user);

        $zone = ShippingZone::query()->create([
            'name' => 'Zone B',
            'code' => 'ZONE-B',
            'type' => 'country',
            'countries' => ['MY'],
        ]);

        $rate = ShippingRate::query()->create([
            'zone_id' => $zone->getKey(),
            'method_code' => 'standard',
            'name' => 'Standard',
            'calculation_type' => 'flat',
            'base_rate' => 500,
        ]);

        $manager = new RatesRelationManager;
        $manager->ownerRecord = $zone;

        $livewire = Mockery::mock(HasTable::class);
        $table = $manager->table(Table::make($livewire));

        $create = $table->getAction('create');
        $edit = $table->getAction('edit');
        $delete = $table->getAction('delete');
        $deleteBulk = $table->getBulkAction('delete');

        expect($create)->not()->toBeNull();
        expect($edit)->not()->toBeNull();
        expect($delete)->not()->toBeNull();
        expect($deleteBulk)->not()->toBeNull();

        expect($create->isAuthorized())->toBeTrue();
        expect($edit->record($rate)->isAuthorized())->toBeTrue();
        expect($delete->record($rate)->isAuthorized())->toBeTrue();
        expect($deleteBulk->isAuthorized())->toBeTrue();
    });

    it('hides ReturnAuthorization items write actions without update permission', function (): void {
        Permission::firstOrCreate(['name' => 'shipping.returns.update', 'guard_name' => 'web']);

        $user = User::query()->create([
            'name' => 'No Returns Permissions',
            'email' => 'no-returns-perms@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($user);

        $rma = ReturnAuthorization::query()->create([
            'type' => 'return',
            'reason' => 'damaged',
        ]);

        $item = ReturnAuthorizationItem::query()->create([
            'return_authorization_id' => $rma->getKey(),
            'name' => 'Widget',
        ]);

        $manager = new ItemsRelationManager;
        $manager->ownerRecord = $rma;

        $livewire = Mockery::mock(HasTable::class);
        $table = $manager->table(Table::make($livewire));

        $create = $table->getAction('create');
        $edit = $table->getAction('edit');
        $delete = $table->getAction('delete');
        $deleteBulk = $table->getBulkAction('delete');

        expect($create)->not()->toBeNull();
        expect($edit)->not()->toBeNull();
        expect($delete)->not()->toBeNull();
        expect($deleteBulk)->not()->toBeNull();

        expect($create->isAuthorized())->toBeFalse();
        expect($edit->record($item)->isAuthorized())->toBeFalse();
        expect($delete->record($item)->isAuthorized())->toBeFalse();
        expect($deleteBulk->isAuthorized())->toBeFalse();
    });

    it('shows ReturnAuthorization items write actions with update permission', function (): void {
        Permission::firstOrCreate(['name' => 'shipping.returns.update', 'guard_name' => 'web']);

        $user = User::query()->create([
            'name' => 'Returns Manager',
            'email' => 'returns-manager@example.com',
            'password' => bcrypt('password'),
        ]);

        $user->givePermissionTo('shipping.returns.update');

        $this->actingAs($user);

        $rma = ReturnAuthorization::query()->create([
            'type' => 'return',
            'reason' => 'damaged',
        ]);

        $item = ReturnAuthorizationItem::query()->create([
            'return_authorization_id' => $rma->getKey(),
            'name' => 'Widget',
        ]);

        $manager = new ItemsRelationManager;
        $manager->ownerRecord = $rma;

        $livewire = Mockery::mock(HasTable::class);
        $table = $manager->table(Table::make($livewire));

        $create = $table->getAction('create');
        $edit = $table->getAction('edit');
        $delete = $table->getAction('delete');
        $deleteBulk = $table->getBulkAction('delete');

        expect($create)->not()->toBeNull();
        expect($edit)->not()->toBeNull();
        expect($delete)->not()->toBeNull();
        expect($deleteBulk)->not()->toBeNull();

        expect($create->isAuthorized())->toBeTrue();
        expect($edit->record($item)->isAuthorized())->toBeTrue();
        expect($delete->record($item)->isAuthorized())->toBeTrue();
        expect($deleteBulk->isAuthorized())->toBeTrue();
    });
});
