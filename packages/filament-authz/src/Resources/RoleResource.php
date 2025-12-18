<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Resources;

use AIArmada\FilamentAuthz\Resources\RoleResource\Pages;
use AIArmada\FilamentAuthz\Resources\RoleResource\RelationManagers;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleResource extends Resource
{
    protected static ?string $model = null;

    public static function getModel(): string
    {
        return config('permission.models.role', Role::class);
    }

    public static function getNavigationGroup(): ?string
    {
        return config('filament-authz.navigation.group');
    }

    public static function getNavigationIcon(): ?string
    {
        return config('filament-authz.navigation.icons.roles');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-authz.navigation.sort');
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = static::currentAuthorizable();

        if ($user === null) {
            return false;
        }

        $hasSuperRole = method_exists($user, 'hasRole') && $user->hasRole(config('filament-authz.super_admin_role'));

        return $user->can('role.viewAny') || $hasSuperRole;
    }

    public static function form(Schema $form): Schema
    {
        return $form->schema([
            Section::make('Role Details')->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->unique(ignoreRecord: true),
                Forms\Components\Select::make('guard_name')
                    ->options(array_combine(config('filament-authz.guards'), config('filament-authz.guards')))
                    ->default(config('filament-authz.guards.0'))
                    ->required()
                    ->reactive(),
            ])->columns(2),

            Section::make('Permissions')->schema([
                Forms\Components\CheckboxList::make('permissions')
                    ->label('Permissions')
                    ->searchable()
                    ->bulkToggleable()
                    ->columns([
                        'sm' => 2,
                        'lg' => 3,
                    ])
                    ->options(function (callable $get): array {
                        $guard = $get('guard_name');

                        return Permission::query()
                            ->when($guard, fn (Builder $query) => $query->where('guard_name', $guard))
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->toArray();
                    })
                    ->default(fn (?Role $record) => $record?->permissions()->pluck('id')->toArray())
                    ->afterStateHydrated(function (Forms\Components\CheckboxList $component, ?Role $record): void {
                        if ($record === null) {
                            return;
                        }

                        $component->state($record->permissions()->pluck('id')->toArray());
                    })
                    ->helperText('Toggle permissions. Switching guards filters available permissions.'),
            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable()->sortable(),
            TextColumn::make('guard_name')->badge()->sortable(),
            TextColumn::make('permissions_count')
                ->counts('permissions')
                ->badge()
                ->color('primary')
                ->label('Permissions')
                ->sortable(),
            TextColumn::make('created_at')->since()->sortable()->toggleable(isToggledHiddenByDefault: true),
        ])->filters([
            SelectFilter::make('guard_name')
                ->label('Guard')
                ->options(array_combine(config('filament-authz.guards'), config('filament-authz.guards')))
                ->placeholder('All guards'),
            Filter::make('guard_name = web')->query(fn (Builder $q) => $q->where('guard_name', 'web')),
        ])->actions([
            Actions\EditAction::make()->authorize(fn (Role $record) => static::currentAuthorizable()?->can('role.update') ?? false),
            Actions\DeleteAction::make()->authorize(fn (Role $record) => static::currentAuthorizable()?->can('role.delete') ?? false),
        ])->bulkActions([
            Actions\DeleteBulkAction::make()->authorize(fn () => static::currentAuthorizable()?->can('role.delete') ?? false),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PermissionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }

    protected static function currentAuthorizable(): ?Authorizable
    {
        $user = Auth::user();

        if (! $user instanceof Authorizable) {
            return null;
        }

        return $user;
    }
}
