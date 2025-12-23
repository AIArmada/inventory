<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip\Resources\CustomerResource\Schemas;

use AIArmada\CashierChip\Subscription;
use AIArmada\FilamentCashierChip\Support\CashierChipOwnerScope;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

final class CustomerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Customer Details')
                ->icon(Heroicon::OutlinedUserCircle)
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextEntry::make('name')
                                ->label('Name')
                                ->weight(FontWeight::SemiBold)
                                ->icon(Heroicon::OutlinedUser),

                            TextEntry::make('email')
                                ->label('Email')
                                ->copyable()
                                ->icon(Heroicon::OutlinedEnvelope),

                            TextEntry::make('phone')
                                ->label('Phone')
                                ->icon(Heroicon::OutlinedPhone)
                                ->placeholder('—'),
                        ]),
                ]),

            Section::make('Billing Information')
                ->icon(Heroicon::OutlinedCreditCard)
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextEntry::make('chip_id')
                                ->label('Chip Customer ID')
                                ->copyable()
                                ->placeholder('Not linked to Chip')
                                ->icon(Heroicon::OutlinedIdentification),

                            TextEntry::make('pm_type')
                                ->label('Payment Method Type')
                                ->badge()
                                ->color('primary')
                                ->placeholder('No payment method')
                                ->formatStateUsing(fn (?string $state): ?string => $state !== null ? ucfirst($state) : null),

                            TextEntry::make('pm_last_four')
                                ->label('Card Last Four')
                                ->placeholder('—')
                                ->formatStateUsing(fn (?string $state): ?string => $state !== null ? '•••• ' . $state : null),
                        ]),
                ]),

            Section::make('Subscription Status')
                ->icon(Heroicon::OutlinedBolt)
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextEntry::make('subscriptions_count')
                                ->label('Active Subscriptions')
                                ->getStateUsing(function (Model $record): int {
                                    if (! method_exists($record, 'subscriptions')) {
                                        return 0;
                                    }

                                    /** @var \Illuminate\Database\Eloquent\Builder<Subscription> $query */
                                    $query = CashierChipOwnerScope::apply($record->subscriptions()->getQuery());

                                    return $query->active()->count();
                                })
                                ->badge()
                                ->color(fn (int $state): string => $state > 0 ? 'success' : 'gray'),

                            TextEntry::make('trial_ends_at')
                                ->label('Trial Ends')
                                ->dateTime(config('filament-cashier-chip.tables.date_format', 'Y-m-d H:i:s'))
                                ->placeholder('No trial')
                                ->color(fn (Model $record): ?string => method_exists($record, 'onGenericTrial') && $record->onGenericTrial() ? 'warning' : null),

                            TextEntry::make('on_trial_status')
                                ->label('Trial Status')
                                ->getStateUsing(function (Model $record): string {
                                    if (method_exists($record, 'onGenericTrial') && $record->onGenericTrial()) {
                                        return 'On Trial';
                                    }
                                    if (method_exists($record, 'hasExpiredGenericTrial') && $record->hasExpiredGenericTrial()) {
                                        return 'Trial Expired';
                                    }

                                    return 'No Trial';
                                })
                                ->badge()
                                ->color(fn (string $state): string => match ($state) {
                                    'On Trial' => 'warning',
                                    'Trial Expired' => 'danger',
                                    default => 'gray',
                                }),
                        ]),
                ]),

            Section::make('Account Information')
                ->icon(Heroicon::OutlinedClock)
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextEntry::make('created_at')
                                ->label('Joined')
                                ->dateTime(config('filament-cashier-chip.tables.date_format', 'Y-m-d H:i:s')),

                            TextEntry::make('updated_at')
                                ->label('Last Updated')
                                ->dateTime(config('filament-cashier-chip.tables.date_format', 'Y-m-d H:i:s')),
                        ]),
                ])
                ->collapsible()
                ->collapsed(),
        ]);
    }
}
