<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Resources\ReturnAuthorizationResource\RelationManagers;

use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('sku')
                    ->label('SKU')
                    ->maxLength(100),

                Forms\Components\TextInput::make('quantity')
                    ->numeric()
                    ->required()
                    ->default(1)
                    ->minValue(1),

                Forms\Components\TextInput::make('quantity_received')
                    ->numeric()
                    ->default(0)
                    ->minValue(0),

                Forms\Components\Select::make('condition')
                    ->options([
                        'unopened' => 'Unopened',
                        'opened' => 'Opened/Used',
                        'damaged' => 'Damaged',
                        'defective' => 'Defective',
                    ]),

                Forms\Components\Textarea::make('notes')
                    ->rows(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('quantity')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('quantity_received')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('condition')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'unopened' => 'success',
                        'opened' => 'info',
                        'damaged' => 'danger',
                        'defective' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('notes')
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->notes),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('condition')
                    ->options([
                        'unopened' => 'Unopened',
                        'opened' => 'Opened/Used',
                        'damaged' => 'Damaged',
                        'defective' => 'Defective',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
