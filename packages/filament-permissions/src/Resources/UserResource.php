<?php

declare(strict_types=1);

namespace AIArmada\FilamentPermissions\Resources;

use AIArmada\FilamentPermissions\Resources\UserResource\Pages;
use AIArmada\FilamentPermissions\Resources\UserResource\RelationManagers;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class UserResource extends Resource
{
    protected static ?string $model = null; // assigned dynamically

    public static function getModel(): string
    {
        return (string) config('filament-permissions.user_model', \App\Models\User::class);
    }

    public static function getNavigationGroup(): ?string
    {
        return config('filament-permissions.navigation.group');
    }

    public static function getNavigationIcon(): ?string
    {
        return config('filament-permissions.navigation.icons.users');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-permissions.navigation.sort');
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return $user?->can('user.viewAny') || $user?->hasRole(config('filament-permissions.super_admin_role'));
    }

    public static function form(Schema $form): Schema
    {
        if ($resource = static::getAppUserResource()) {
            // Get the application's form components
            $appForm = $resource::form($form);
            $components = $appForm->getComponents();

            // Add password field if not present
            $hasPassword = collect($components)->contains(function ($component) {
                return method_exists($component, 'getName') && $component->getName() === 'password';
            });

            if (! $hasPassword) {
                $components[] = Forms\Components\TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->rule('min:8')
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? \Illuminate\Support\Facades\Hash::make($state) : null)
                    ->dehydrated(fn (?string $state): bool => filled($state));
            }

            return $form->components($components);
        }

        return $form->schema([
            Section::make('User')->schema([
                Forms\Components\TextInput::make('name')->required(),
                Forms\Components\TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->rule('min:8')
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? \Illuminate\Support\Facades\Hash::make($state) : null)
                    ->dehydrated(fn (?string $state): bool => filled($state)),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        if ($resource = static::getAppUserResource()) {
            // Get the application's table configuration
            $appTable = $resource::table($table);
            $columns = $appTable->getColumns();

            // Check if roles column already exists
            $hasRolesColumn = collect($columns)->contains(function ($column) {
                return method_exists($column, 'getName') &&
                       (str_contains($column->getName(), 'roles') || $column->getName() === 'roles.name');
            });

            // Add roles column if not present
            if (! $hasRolesColumn) {
                $columns[] = TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->separator(',');
            }

            return $appTable->columns($columns);
        }

        return $table->columns([
            TextColumn::make('name')->searchable()->sortable(),
            TextColumn::make('email')->searchable()->sortable(),
            TextColumn::make('roles.name')
                ->label('Roles')
                ->badge()
                ->separator(','),
            TextColumn::make('updated_at')->since()->sortable()->toggleable(isToggledHiddenByDefault: true),
        ])->actions([
            \Filament\Actions\EditAction::make()->authorize(fn (Model $record) => auth()->user()?->can('user.update')),
        ]);
    }

    public static function getRelations(): array
    {
        $relations = [];

        if ($resource = static::getAppUserResource()) {
            $relations = $resource::getRelations();
        }

        // Always add package's relation managers
        $relations[] = RelationManagers\RolesRelationManager::class;
        $relations[] = RelationManagers\PermissionsRelationManager::class;

        return $relations;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    protected static function getAppUserResource(): ?string
    {
        $candidates = [
            'App\Filament\Resources\UserResource',
            'App\Filament\Resources\Users\UserResource',
        ];

        foreach ($candidates as $candidate) {
            if (class_exists($candidate) && is_subclass_of($candidate, Resource::class)) {
                return $candidate;
            }
        }

        return null;
    }
}
