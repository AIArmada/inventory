<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Resources\RecurringScheduleResource\RelationManagers;

use AIArmada\Chip\Enums\ChargeStatus;
use AIArmada\Chip\Models\RecurringCharge;
use BackedEnum;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class ChargesRelationManager extends RelationManager
{
    protected static string $relationship = 'charges';

    protected static ?string $title = 'Charge History';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedBanknotes;

    public function table(Table $table): Table
    {
        return $table
            ->striped()
            ->columns([
                TextColumn::make('id')
                    ->label('Charge ID')
                    ->copyable()
                    ->limit(12)
                    ->tooltip(fn (RecurringCharge $record): string => $record->id),
                TextColumn::make('chip_purchase_id')
                    ->label('Purchase ID')
                    ->copyable()
                    ->limit(12)
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (RecurringCharge $record): string => match ($record->status) {
                        ChargeStatus::Success => 'success',
                        ChargeStatus::Failed => 'danger',
                        ChargeStatus::Pending => 'warning',
                    })
                    ->formatStateUsing(fn (RecurringCharge $record): string => $record->status->name),
                TextColumn::make('amount_minor')
                    ->label('Amount')
                    ->formatStateUsing(fn (int $state): string => number_format($state / 100, 2))
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('failure_reason')
                    ->label('Failure Reason')
                    ->limit(30)
                    ->placeholder('—')
                    ->color('danger'),
                TextColumn::make('attempted_at')
                    ->label('Attempted')
                    ->dateTime(config('filament-chip.tables.created_on_format', 'Y-m-d H:i:s'))
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime(config('filament-chip.tables.created_on_format', 'Y-m-d H:i:s'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('attempted_at', 'desc')
            ->paginated([10, 25, 50]);
    }
}
