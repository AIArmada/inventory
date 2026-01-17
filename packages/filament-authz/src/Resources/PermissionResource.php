<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Resources;

use AIArmada\FilamentAuthz\Models\Permission;
use AIArmada\FilamentAuthz\Resources\PermissionResource\Pages;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class PermissionResource extends Resource
{
    protected static ?string $model = null;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModel(): string
    {
        return config('permission.models.permission', Permission::class);
    }

    public static function canViewAny(): bool
    {
        return static::checkAbility('permission.viewAny');
    }

    public static function canCreate(): bool
    {
        return static::checkAbility('permission.create');
    }

    public static function canEdit(Model $record): bool
    {
        return static::checkAbility('permission.update');
    }

    public static function canDelete(Model $record): bool
    {
        return static::checkAbility('permission.delete');
    }

    protected static function checkAbility(string $ability): bool
    {
        $user = Auth::user();

        if ($user === null) {
            return false;
        }

        $superAdminRole = config('filament-authz.super_admin_role');

        if (method_exists($user, 'hasRole') && $user->hasRole($superAdminRole)) {
            return true;
        }

        return method_exists($user, 'can') && $user->can($ability);
    }

    public static function getNavigationGroup(): ?string
    {
        return config('filament-authz.navigation.group');
    }

    public static function getNavigationIcon(): ?string
    {
        return config('filament-authz.navigation.icons.permissions');
    }

    public static function getNavigationSort(): ?int
    {
        $sort = config('filament-authz.navigation.sort');

        return is_int($sort) ? $sort + 1 : null;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('filament-authz.navigation.register', true) && static::canViewAny();
    }

    public static function form(Schema $form): Schema
    {
        $guards = config('filament-authz.guards', ['web']);

        return $form->schema([
            Section::make('Permission Details')
                ->description('Create a permission name and guard.')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true)
                        ->placeholder('e.g. orders.viewAny')
                        ->helperText('Names should follow your permission naming convention.')
                        ->autocomplete(false),
                    Forms\Components\Select::make('guard_name')
                        ->options(array_combine($guards, $guards))
                        ->default($guards[0] ?? 'web')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->helperText('Guards map to auth drivers (web, api, etc.).'),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        $guards = config('filament-authz.guards', ['web']);

        return $table->columns([
            TextColumn::make('name')->searchable()->sortable()->copyable(),
            TextColumn::make('guard_name')->badge()->sortable(),
            TextColumn::make('created_at')->since()->sortable()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('updated_at')->since()->sortable()->toggleable(isToggledHiddenByDefault: true),
        ])->filters([
            SelectFilter::make('guard_name')
                ->label('Guard')
                ->options(array_combine($guards, $guards))
                ->placeholder('All guards')
                ->searchable(),
            Filter::make('assigned')
                ->label('Assigned to roles')
                ->query(fn (Builder $query): Builder => $query->has('roles'))
                ->indicator('Assigned'),
        ])->actions([
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ])->bulkActions([
            Actions\DeleteBulkAction::make(),
        ])->defaultSort('name')
            ->striped()
            ->persistSearchInSession()
            ->persistFiltersInSession()
            ->deferFilters()
            ->paginationPageOptions([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('No permissions yet')
            ->emptyStateDescription('Create permissions to grant access across the panel.')
            ->emptyStateIcon('heroicon-o-key');
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
