<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Resources\RecurringScheduleResource\Tables;

use AIArmada\Chip\Enums\RecurringInterval;
use AIArmada\Chip\Enums\RecurringStatus;
use AIArmada\Chip\Models\RecurringSchedule;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class RecurringScheduleTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->striped()
            ->columns([
                TextColumn::make('id')
                    ->label('Schedule ID')
                    ->icon('heroicon-o-arrow-path')
                    ->iconColor('primary')
                    ->copyable()
                    ->searchable()
                    ->limit(12)
                    ->tooltip(fn (RecurringSchedule $record): string => $record->id),
                TextColumn::make('chip_client_id')
                    ->label('Client ID')
                    ->icon('heroicon-o-user')
                    ->copyable()
                    ->searchable()
                    ->limit(12)
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (RecurringSchedule $record): string => $record->status->color())
                    ->formatStateUsing(fn (RecurringSchedule $record): string => $record->status->label())
                    ->sortable(),
                TextColumn::make('amount_minor')
                    ->label('Amount')
                    ->formatStateUsing(fn (RecurringSchedule $record): string => $record->getAmountFormatted())
                    ->weight(FontWeight::SemiBold)
                    ->sortable(),
                TextColumn::make('interval')
                    ->label('Interval')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn (RecurringSchedule $record): string => $record->interval_count > 1
                        ? "Every {$record->interval_count} {$record->interval->label()}s"
                        : $record->interval->label()
                    )
                    ->sortable(),
                TextColumn::make('next_charge_at')
                    ->label('Next Charge')
                    ->dateTime(config('filament-chip.tables.created_on_format', 'Y-m-d H:i:s'))
                    ->sortable()
                    ->color(fn (RecurringSchedule $record): string => $record->isDue() ? 'warning' : 'gray')
                    ->placeholder('—'),
                TextColumn::make('last_charged_at')
                    ->label('Last Charged')
                    ->dateTime(config('filament-chip.tables.created_on_format', 'Y-m-d H:i:s'))
                    ->sortable()
                    ->toggleable()
                    ->placeholder('Never'),
                TextColumn::make('failure_count')
                    ->label('Failures')
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state === 0 => 'success',
                        $state < 3 => 'warning',
                        default => 'danger',
                    })
                    ->sortable(),
                TextColumn::make('charges_count')
                    ->label('Charges')
                    ->counts('charges')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime(config('filament-chip.tables.created_on_format', 'Y-m-d H:i:s'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(RecurringStatus::class),
                SelectFilter::make('interval')
                    ->label('Interval')
                    ->options(RecurringInterval::class),
            ], layout: FiltersLayout::AboveContent)
            ->actions([
                ViewAction::make()
                    ->icon('heroicon-o-eye'),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100])
            ->poll(config('filament-chip.polling_interval', '45s'));
    }
}
