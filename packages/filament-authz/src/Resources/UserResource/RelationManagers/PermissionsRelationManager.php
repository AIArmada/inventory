<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Resources\UserResource\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Spatie\Permission\PermissionRegistrar;

class PermissionsRelationManager extends RelationManager
{
    protected static string $relationship = 'permissions';

    protected static ?string $title = 'Direct Permissions';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('guard_name')->badge(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(fn ($query) => $query->where('guard_name', config('filament-authz.default_guard')))
                    ->after(fn () => app(PermissionRegistrar::class)->forgetCachedPermissions()),
            ])
            ->recordActions([
                DetachAction::make()
                    ->after(fn () => app(PermissionRegistrar::class)->forgetCachedPermissions()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make()
                        ->after(fn () => app(PermissionRegistrar::class)->forgetCachedPermissions()),
                ]),
            ]);
    }

    public function form(Schema $form): Schema
    {
        return $form->schema([
            Forms\Components\Select::make('permissions')
                ->multiple()
                ->relationship('permissions', 'name')
                ->preload(),
        ]);
    }
}
