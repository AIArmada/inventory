<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip\Resources\CustomerResource\Tables;

use AIArmada\FilamentCashierChip\Support\CashierChipOwnerScope;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class CustomerTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::SemiBold),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->icon('heroicon-o-envelope'),

                TextColumn::make('chip_id')
                    ->label('Chip ID')
                    ->copyable()
                    ->searchable()
                    ->placeholder('Not linked')
                    ->toggleable(),

                IconColumn::make('has_chip_id')
                    ->label('Linked')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->getStateUsing(fn (Model $record): bool => ! empty($record->chip_id)),

                TextColumn::make('pm_type')
                    ->label('Payment Method')
                    ->badge()
                    ->color('primary')
                    ->placeholder('None')
                    ->formatStateUsing(
                        fn (?string $state, Model $record): ?string => $state !== null
                        ? ucfirst($state) . ' •••• ' . ($record->pm_last_four ?? '****')
                        : null
                    ),

                TextColumn::make('subscriptions_count')
                    ->label('Subscriptions')
                    ->counts([
                        'subscriptions' => fn (Builder $query): Builder => CashierChipOwnerScope::apply($query),
                    ])
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'gray')
                    ->sortable(),

                IconColumn::make('on_trial')
                    ->label('Trial')
                    ->boolean()
                    ->trueIcon('heroicon-o-clock')
                    ->falseIcon('heroicon-o-x-mark')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->getStateUsing(fn (Model $record): bool => method_exists($record, 'onTrial') && $record->onTrial()),

                TextColumn::make('trial_ends_at')
                    ->label('Trial Ends')
                    ->dateTime(config('filament-cashier-chip.tables.date_format', 'Y-m-d H:i:s'))
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Joined')
                    ->dateTime(config('filament-cashier-chip.tables.date_format', 'Y-m-d H:i:s'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('has_chip_id')
                    ->label('Chip Linked')
                    ->placeholder('All')
                    ->trueLabel('Linked to Chip')
                    ->falseLabel('Not Linked')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('chip_id'),
                        false: fn (Builder $query): Builder => $query->whereNull('chip_id'),
                    ),

                TernaryFilter::make('has_payment_method')
                    ->label('Payment Method')
                    ->placeholder('All')
                    ->trueLabel('Has Payment Method')
                    ->falseLabel('No Payment Method')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('pm_type'),
                        false: fn (Builder $query): Builder => $query->whereNull('pm_type'),
                    ),

                Filter::make('has_subscriptions')
                    ->label('Has Subscriptions')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereHas(
                        'subscriptions',
                        fn (Builder $subscriptionsQuery): Builder => CashierChipOwnerScope::apply($subscriptionsQuery),
                    )),

                Filter::make('on_trial')
                    ->label('On Trial')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('trial_ends_at')
                        ->where('trial_ends_at', '>', now())),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->actions([
                ViewAction::make()
                    ->icon('heroicon-o-eye'),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100])
            ->poll(config('filament-cashier-chip.tables.polling_interval', '45s'));
    }
}
