<?php

declare(strict_types=1);

namespace AIArmada\FilamentPermissions\Resources\UserResource\RelationManagers;

use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Spatie\Permission\PermissionRegistrar;

class RolesRelationManager extends RelationManager
{
    protected static string $relationship = 'roles';

    protected static ?string $title = 'Roles';

    public function table(Table $table): Table
    {
        $guards = (array) config('filament-permissions.guards');

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('guard_name')->badge(),
            ])
            ->headerActions([
                \Filament\Actions\AttachAction::make()
                    ->preloadRecordSelect()
                    ->recordTitle(fn ($record) => $record->name)
                    ->recordSelectSearchColumns(['name'])
                    ->recordSelectOptionsQuery(fn ($query) => $query->whereIn('guard_name', $guards))
                    ->after(fn () => app(PermissionRegistrar::class)->forgetCachedPermissions()),
            ])
            ->recordActions([
                \Filament\Actions\DetachAction::make()
                    ->after(fn () => app(PermissionRegistrar::class)->forgetCachedPermissions()),
            ])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DetachBulkAction::make()
                        ->after(fn () => app(PermissionRegistrar::class)->forgetCachedPermissions()),
                ]),
            ]);
    }

    public function form(Schema $form): Schema
    {
        return $form->schema([
            Forms\Components\Select::make('roles')
                ->multiple()
                ->relationship('roles', 'name')
                ->preload(),
        ]);
    }
}
