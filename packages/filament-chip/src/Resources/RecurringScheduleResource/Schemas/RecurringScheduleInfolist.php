<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Resources\RecurringScheduleResource\Schemas;

use AIArmada\Chip\Enums\RecurringStatus;
use AIArmada\Chip\Models\RecurringSchedule;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;

final class RecurringScheduleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Schedule Overview')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextEntry::make('id')
                                ->label('Schedule ID')
                                ->icon(Heroicon::OutlinedArrowPath)
                                ->copyable()
                                ->weight(FontWeight::SemiBold),
                            TextEntry::make('chip_client_id')
                                ->label('Client ID')
                                ->icon(Heroicon::OutlinedUser)
                                ->copyable(),
                            TextEntry::make('status')
                                ->label('Status')
                                ->badge()
                                ->color(fn (RecurringSchedule $record): string => $record->status->color())
                                ->formatStateUsing(fn (RecurringSchedule $record): string => $record->status->label()),
                        ]),
                    Grid::make(3)
                        ->schema([
                            TextEntry::make('recurring_token_id')
                                ->label('Token ID')
                                ->icon(Heroicon::OutlinedKey)
                                ->copyable()
                                ->weight(FontWeight::Medium),
                            TextEntry::make('amount_minor')
                                ->label('Amount')
                                ->formatStateUsing(fn (RecurringSchedule $record): string => $record->getAmountFormatted())
                                ->icon(Heroicon::OutlinedBanknotes)
                                ->weight(FontWeight::SemiBold),
                            TextEntry::make('interval')
                                ->label('Billing Interval')
                                ->formatStateUsing(fn (RecurringSchedule $record): string => $record->interval_count > 1
                                    ? "Every {$record->interval_count} {$record->interval->label()}s"
                                    : $record->interval->label()
                                )
                                ->badge()
                                ->color('info'),
                        ]),
                ]),

            Section::make('Billing Schedule')
                ->schema([
                    Grid::make(4)
                        ->schema([
                            TextEntry::make('next_charge_at')
                                ->label('Next Charge')
                                ->dateTime(config('filament-chip.tables.created_on_format', 'Y-m-d H:i:s'))
                                ->icon(Heroicon::OutlinedCalendar)
                                ->color(fn (RecurringSchedule $record): string => $record->isDue() ? 'warning' : 'gray')
                                ->placeholder('Not scheduled'),
                            TextEntry::make('last_charged_at')
                                ->label('Last Charged')
                                ->dateTime(config('filament-chip.tables.created_on_format', 'Y-m-d H:i:s'))
                                ->icon(Heroicon::OutlinedClock)
                                ->placeholder('Never'),
                            TextEntry::make('cancelled_at')
                                ->label('Cancelled At')
                                ->dateTime(config('filament-chip.tables.created_on_format', 'Y-m-d H:i:s'))
                                ->icon(Heroicon::OutlinedXCircle)
                                ->color('danger')
                                ->visible(fn (RecurringSchedule $record): bool => $record->cancelled_at !== null)
                                ->placeholder('—'),
                            TextEntry::make('created_at')
                                ->label('Created')
                                ->dateTime(config('filament-chip.tables.created_on_format', 'Y-m-d H:i:s'))
                                ->icon(Heroicon::OutlinedClock),
                        ]),
                ]),

            Section::make('Failure Tracking')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextEntry::make('failure_count')
                                ->label('Failure Count')
                                ->badge()
                                ->color(fn (int $state): string => match (true) {
                                    $state === 0 => 'success',
                                    $state < 3 => 'warning',
                                    default => 'danger',
                                }),
                            TextEntry::make('max_failures')
                                ->label('Max Failures Allowed')
                                ->badge()
                                ->color('gray'),
                            TextEntry::make('charges_count')
                                ->label('Total Charges')
                                ->state(fn (RecurringSchedule $record): int => $record->charges()->count())
                                ->badge()
                                ->color('info'),
                        ]),
                ]),

            Section::make('Subscriber')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextEntry::make('subscriber_type')
                                ->label('Subscriber Type')
                                ->placeholder('—'),
                            TextEntry::make('subscriber_id')
                                ->label('Subscriber ID')
                                ->copyable()
                                ->placeholder('—'),
                        ]),
                ])
                ->visible(fn (RecurringSchedule $record): bool => $record->subscriber_type !== null),

            Section::make('Metadata')
                ->schema([
                    TextEntry::make('metadata')
                        ->label('')
                        ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
                        ->columnSpanFull(),
                ])
                ->collapsible()
                ->collapsed()
                ->visible(fn (RecurringSchedule $record): bool => filled($record->metadata)),
        ]);
    }
}
