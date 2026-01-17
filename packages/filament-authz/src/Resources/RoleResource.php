<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Resources;

use AIArmada\FilamentAuthz\Models\Role;
use AIArmada\FilamentAuthz\Resources\RoleResource\Concerns\HasAuthzFormComponents;
use AIArmada\FilamentAuthz\Resources\RoleResource\Pages;
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

class RoleResource extends Resource
{
    use HasAuthzFormComponents;

    protected static ?string $model = null;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModel(): string
    {
        return config('permission.models.role', Role::class);
    }

    public static function canViewAny(): bool
    {
        return static::checkAbility('role.viewAny');
    }

    public static function canCreate(): bool
    {
        return static::checkAbility('role.create');
    }

    public static function canEdit(Model $record): bool
    {
        return static::checkAbility('role.update');
    }

    public static function canDelete(Model $record): bool
    {
        return static::checkAbility('role.delete');
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
        return config('filament-authz.navigation.icons.roles');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-authz.navigation.sort');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('filament-authz.navigation.register', true) && static::canViewAny();
    }

    public static function getSlug(?\Filament\Panel $panel = null): string
    {
        return (string) config('filament-authz.role_resource.slug', 'authz/roles');
    }

    public static function form(Schema $form): Schema
    {
        $guards = config('filament-authz.guards', ['web']);

        return $form->schema([
            Section::make('Role Details')
                ->description('Define the role name and the guard it applies to.')
                ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->placeholder('e.g. sales_manager')
                    ->helperText('Use a unique, readable name per guard.')
                    ->autocomplete(false),
                Forms\Components\Select::make('guard_name')
                    ->options(array_combine($guards, $guards))
                    ->default($guards[0] ?? 'web')
                    ->required()
                    ->live()
                    ->searchable()
                    ->preload()
                    ->helperText('Guards map to auth drivers (web, api, etc.).'),
            ])->columns(2),

            static::getAuthzFormComponents()
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        $guards = config('filament-authz.guards', ['web']);

        return $table->columns([
            TextColumn::make('name')->searchable()->sortable()->copyable(),
            TextColumn::make('guard_name')->badge()->sortable(),
            TextColumn::make('permissions_count')
                ->counts('permissions')
                ->badge()
                ->color('primary')
                ->label('Permissions')
                ->sortable(),
            TextColumn::make('created_at')->since()->sortable()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('updated_at')->since()->sortable()->toggleable(isToggledHiddenByDefault: true),
        ])->filters([
            SelectFilter::make('guard_name')
                ->label('Guard')
                ->options(array_combine($guards, $guards))
                ->placeholder('All guards')
                ->searchable(),
            Filter::make('has_permissions')
                ->label('Has permissions')
                ->query(fn (Builder $query): Builder => $query->has('permissions'))
                ->indicator('Has permissions'),
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
            ->emptyStateHeading('No roles yet')
            ->emptyStateDescription('Create roles to control access to resources and pages.')
            ->emptyStateIcon('heroicon-o-shield-check');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}
