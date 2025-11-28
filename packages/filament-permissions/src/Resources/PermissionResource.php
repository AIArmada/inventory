<?php

declare(strict_types=1);

namespace AIArmada\FilamentPermissions\Resources;

use AIArmada\FilamentPermissions\Resources\PermissionResource\Pages;
use AIArmada\FilamentPermissions\Resources\PermissionResource\RelationManagers;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Permission;

class PermissionResource extends Resource
{
    protected static ?string $model = null;

    public static function getModel(): string
    {
        return config('permission.models.permission', Permission::class);
    }

    public static function getNavigationGroup(): ?string
    {
        return config('filament-permissions.navigation.group');
    }

    public static function getNavigationIcon(): ?string
    {
        return config('filament-permissions.navigation.icons.permissions');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-permissions.navigation.sort');
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return $user?->can('permission.viewAny') || $user?->hasRole(config('filament-permissions.super_admin_role'));
    }

    public static function form(Schema $form): Schema
    {
        return $form->schema([
            Section::make('Permission Details')->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->unique(ignoreRecord: true),
                Forms\Components\Select::make('guard_name')
                    ->options(array_combine(config('filament-permissions.guards'), config('filament-permissions.guards')))
                    ->default(config('filament-permissions.guards.0'))
                    ->required(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable()->sortable(),
            TextColumn::make('guard_name')->badge()->sortable(),
            TextColumn::make('created_at')->since()->sortable()->toggleable(isToggledHiddenByDefault: true),
        ])->filters([
            Filter::make('guard_name = web')->query(fn (Builder $q) => $q->where('guard_name', 'web')),
        ])->actions([
            Actions\EditAction::make()->authorize(fn (Permission $record) => auth()->user()?->can('permission.update')),
            Actions\DeleteAction::make()->authorize(fn (Permission $record) => auth()->user()?->can('permission.delete')),
        ])->bulkActions([
            Actions\DeleteBulkAction::make()->authorize(fn () => auth()->user()?->can('permission.delete')),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\RolesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPermissions::route('/'),
            'create' => Pages\CreatePermission::route('/create'),
            'edit' => Pages\EditPermission::route('/{record}/edit'),
        ];
    }
}
