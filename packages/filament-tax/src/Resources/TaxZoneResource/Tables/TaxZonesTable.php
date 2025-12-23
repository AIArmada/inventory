<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Resources\TaxZoneResource\Tables;

use AIArmada\FilamentTax\Support\FilamentTaxAuthz;
use AIArmada\Tax\Models\TaxZone;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

final class TaxZonesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Zone')
                    ->searchable()
                    ->sortable()
                    ->description(fn (TaxZone $record): string => $record->code),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge(),

                TextColumn::make('countries')
                    ->label('Countries')
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : $state),

                TextColumn::make('rates_count')
                    ->label('Rates')
                    ->alignEnd(),

                TextColumn::make('priority')
                    ->label('Priority')
                    ->numeric()
                    ->sortable(),

                IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->defaultSort('priority', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'country' => 'Country',
                        'state' => 'State',
                        'postcode' => 'Postcode',
                    ]),

                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                FilamentTaxAuthz::requirePermission(
                    BulkAction::make('delete')
                        ->label('Delete Selected')
                        ->icon(Heroicon::OutlinedTrash)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->delete())
                        ->deselectRecordsAfterCompletion(),
                    'tax.zones.delete',
                ),
            ]);
    }
}
