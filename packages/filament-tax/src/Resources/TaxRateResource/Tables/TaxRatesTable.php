<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Resources\TaxRateResource\Tables;

use AIArmada\FilamentTax\Support\FilamentTaxAuthz;
use AIArmada\Tax\Support\TaxOwnerScope;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class TaxRatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('zone.name')
                    ->label('Zone')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),

                TextColumn::make('name')
                    ->label('Rate Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('tax_class')
                    ->label('Class')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'standard' => 'success',
                        'reduced' => 'warning',
                        'zero' => 'gray',
                        'exempt' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('rate')
                    ->label('Rate')
                    ->formatStateUsing(fn (int $state): string => number_format($state / 100, 2))
                    ->suffix('%')
                    ->sortable()
                    ->weight('bold'),

                IconColumn::make('is_compound')
                    ->label('Compound')
                    ->boolean()
                    ->toggleable(),

                IconColumn::make('is_shipping')
                    ->label('Shipping')
                    ->boolean()
                    ->toggleable(),

                TextColumn::make('priority')
                    ->label('Priority')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('priority', 'desc')
            ->filters([
                SelectFilter::make('zone_id')
                    ->label('Zone')
                    ->relationship(
                        'zone',
                        'name',
                        fn (Builder $query): Builder => TaxOwnerScope::applyToOwnedQuery($query),
                    ),

                SelectFilter::make('tax_class')
                    ->label('Class')
                    ->options([
                        'standard' => 'Standard',
                        'reduced' => 'Reduced',
                        'zero' => 'Zero Rate',
                        'exempt' => 'Exempt',
                    ]),

                TernaryFilter::make('is_active')
                    ->label('Active'),

                TernaryFilter::make('is_compound')
                    ->label('Compound'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                FilamentTaxAuthz::requirePermission(
                    BulkAction::make('activate')
                        ->label('Activate')
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->color('success')
                        ->action(fn ($records) => $records->each->update(['is_active' => true]))
                        ->deselectRecordsAfterCompletion(),
                    'tax.rates.update',
                ),
                FilamentTaxAuthz::requirePermission(
                    BulkAction::make('deactivate')
                        ->label('Deactivate')
                        ->icon(Heroicon::OutlinedXCircle)
                        ->color('danger')
                        ->action(fn ($records) => $records->each->update(['is_active' => false]))
                        ->deselectRecordsAfterCompletion(),
                    'tax.rates.update',
                ),
                FilamentTaxAuthz::requirePermission(
                    BulkAction::make('delete')
                        ->label('Delete Selected')
                        ->icon(Heroicon::OutlinedTrash)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->delete())
                        ->deselectRecordsAfterCompletion(),
                    'tax.rates.delete',
                ),
            ]);
    }
}
