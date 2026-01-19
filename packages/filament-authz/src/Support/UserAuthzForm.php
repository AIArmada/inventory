<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Support;

use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\PermissionRegistrar;

final class UserAuthzForm
{
    /**
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function components(): array
    {
        $components = [];

        if (config('filament-authz.user_resource.form.roles', true)) {
            $components[] = static::rolesComponent();
        }

        if (config('filament-authz.user_resource.form.permissions', true)) {
            $components[] = static::permissionsComponent();
        }

        if ($components === []) {
            return [];
        }

        return [
            Section::make('Access Control')
                ->description('Assign roles to manage user permissions. Direct permission assignment is available for special cases.')
                ->schema($components)
                ->columns(2),
        ];
    }

    protected static function rolesComponent(): Select
    {
        return Select::make('roles')
            ->label('Roles')
            ->relationship('roles', 'name', modifyQueryUsing: fn (Builder $query): Builder => static::applyRoleScope($query))
            ->multiple()
            ->searchable()
            ->preload()
            ->helperText('Select roles to grant permissions to this user.')
            ->saveRelationshipsUsing(function (Model $record, array $state): void {
                if (! method_exists($record, 'roles')) {
                    return;
                }

                $relation = $record->roles();
                $teamPayload = static::getTeamPivotPayload();

                if ($teamPayload !== []) {
                    $relation->syncWithPivotValues($state, $teamPayload);
                } else {
                    $relation->sync($state);
                }

                // Clear permission cache so changes take effect immediately
                app(PermissionRegistrar::class)->forgetCachedPermissions();
            });
    }

    protected static function permissionsComponent(): Select
    {
        return Select::make('permissions')
            ->label('Direct Permissions')
            ->relationship('permissions', 'name', modifyQueryUsing: fn (Builder $query): Builder => static::applyPermissionScope($query))
            ->multiple()
            ->searchable()
            ->preload()
            ->helperText('Assign specific permissions directly. Use roles for standard access control.')
            ->saveRelationshipsUsing(function (Model $record, array $state): void {
                if (! method_exists($record, 'permissions')) {
                    return;
                }

                $relation = $record->permissions();
                $teamPayload = static::getTeamPivotPayload();

                if ($teamPayload !== []) {
                    $relation->syncWithPivotValues($state, $teamPayload);
                } else {
                    $relation->sync($state);
                }

                // Clear permission cache so changes take effect immediately
                app(PermissionRegistrar::class)->forgetCachedPermissions();
            });
    }

    protected static function applyRoleScope(Builder $query): Builder
    {
        $guards = (array) config('filament-authz.guards', ['web']);
        $guard = $guards[0] ?? 'web';

        $table = $query->getModel()->getTable();
        $query->where("{$table}.guard_name", $guard);

        $registrar = app(PermissionRegistrar::class);

        if (! $registrar->teams || ! config('filament-authz.scoped_to_tenant', true)) {
            return $query;
        }

        $teamsKey = $registrar->teamsKey;
        $teamId = getPermissionsTeamId();

        // Roles can be global (team_id IS NULL) or team-specific.
        // When listing available roles, include both global roles (assignable to any team)
        // and roles belonging to the current team.
        return $query->where(function (Builder $q) use ($table, $teamsKey, $teamId): void {
            $q->whereNull("{$table}.{$teamsKey}");

            if ($teamId !== null) {
                $q->orWhere("{$table}.{$teamsKey}", $teamId);
            }
        });
    }

    protected static function applyPermissionScope(Builder $query): Builder
    {
        $guards = (array) config('filament-authz.guards', ['web']);
        $guard = $guards[0] ?? 'web';

        $table = $query->getModel()->getTable();
        $query->where("{$table}.guard_name", $guard);

        // Permissions are typically global, so we do not scope by team_id
        // unless explicitly configured/customized to do so.
        // For standard setup, we return query as is.

        return $query;
    }

    /**
     * @return array<string, int|string|null>
     */
    protected static function getTeamPivotPayload(): array
    {
        $registrar = app(PermissionRegistrar::class);

        if (! $registrar->teams || ! config('filament-authz.scoped_to_tenant', true)) {
            return [];
        }

        return [$registrar->teamsKey => getPermissionsTeamId()];
    }
}
