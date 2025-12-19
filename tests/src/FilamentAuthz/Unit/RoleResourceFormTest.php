<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Resources\RoleResource;
use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Contracts\TranslatableContentDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component as LivewireComponent;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

afterEach(function (): void {
    Mockery::close();
});

function filamentAuthz_makeRoleResourceSchema(): Schema
{
    $livewire = new class extends LivewireComponent implements HasSchemas
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

    return Schema::make($livewire);
}

function filamentAuthz_reflectProtectedProperty(object $object, string $property): mixed
{
    $reflection = new ReflectionClass($object);

    while (! $reflection->hasProperty($property)) {
        $reflection = $reflection->getParentClass();

        if (! $reflection) {
            throw new RuntimeException("Property [{$property}] not found.");
        }
    }

    $prop = $reflection->getProperty($property);
    $prop->setAccessible(true);

    return $prop->getValue($object);
}

test('role resource form permission options are guard-filtered', function (): void {
    config()->set('filament-authz.guards', ['web', 'api']);

    $webOrdersView = Permission::create(['name' => 'orders.view', 'guard_name' => 'web']);
    $webOrdersUpdate = Permission::create(['name' => 'orders.update', 'guard_name' => 'web']);
    $apiProductsView = Permission::create(['name' => 'products.view', 'guard_name' => 'api']);

    $schema = filamentAuthz_makeRoleResourceSchema();
    $schema = RoleResource::form($schema);

    $fields = $schema->getFlatFields(withHidden: true);

    /** @var CheckboxList $permissions */
    $permissions = collect($fields)
        ->first(fn ($field): bool => ($field instanceof CheckboxList) && ($field->getName() === 'permissions'));

    expect($permissions)->toBeInstanceOf(CheckboxList::class);

    /** @var Closure $options */
    $options = filamentAuthz_reflectProtectedProperty($permissions, 'options');

    $optionsForWeb = $options(fn (string $key): mixed => $key === 'guard_name' ? 'web' : null);

    expect($optionsForWeb)->toHaveKey((string) $webOrdersView->id);
    expect($optionsForWeb)->toHaveKey((string) $webOrdersUpdate->id);
    expect($optionsForWeb)->not()->toHaveKey((string) $apiProductsView->id);
});

test('role resource permissions checkbox list hydrates state from record', function (): void {
    $permission = Permission::create(['name' => 'orders.view', 'guard_name' => 'web']);

    $role = Role::create(['name' => 'Manager', 'guard_name' => 'web']);
    $role->givePermissionTo($permission);

    $user = User::create([
        'name' => 'Hydration User',
        'email' => 'hydration-user@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    $schema = filamentAuthz_makeRoleResourceSchema()->record($role);
    $schema = RoleResource::form($schema);

    // Ensure containers are assigned so hooks can evaluate.
    $schema->getComponents(withActions: false, withHidden: true);

    $fields = $schema->getFlatFields(withHidden: true);

    /** @var CheckboxList $permissions */
    $permissions = collect($fields)
        ->first(fn ($field): bool => ($field instanceof CheckboxList) && ($field->getName() === 'permissions'));

    expect($permissions)->toBeInstanceOf(CheckboxList::class);

    /** @var Closure|null $afterStateHydrated */
    $afterStateHydrated = filamentAuthz_reflectProtectedProperty($permissions, 'afterStateHydrated');

    expect($afterStateHydrated)->not()->toBeNull();

    $afterStateHydrated($permissions, $role);

    expect($permissions->getState())->toContain($permission->id);
});
