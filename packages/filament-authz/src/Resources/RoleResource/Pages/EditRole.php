<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Resources\RoleResource\Pages;

use AIArmada\FilamentAuthz\Resources\RoleResource;
use AIArmada\FilamentAuthz\Support\Concerns\EnsuresLivewireErrorBag;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * @property Role $record
 */
class EditRole extends EditRecord
{
    use EnsuresLivewireErrorBag;

    /**
     * @var list<string>
     */
    protected array $permissionIds = [];

    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->permissionIds = array_map('strval', $data['permissions'] ?? []);
        unset($data['permissions']);

        return $data;
    }

    protected function afterSave(): void
    {
        if ($this->permissionIds !== []) {
            /** @var class-string<Permission> $permissionModel */
            $permissionModel = config('permission.models.permission', Permission::class);

            $permissions = $permissionModel::query()
                ->where('guard_name', $this->record->guard_name)
                ->whereIn('id', $this->permissionIds)
                ->get();

            $this->record->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
