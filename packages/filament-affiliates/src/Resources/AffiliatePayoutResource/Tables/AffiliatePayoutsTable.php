<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliatePayoutResource\Tables;

use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\FilamentAffiliates\Resources\AffiliatePayoutResource;
use AIArmada\FilamentAffiliates\Services\PayoutExportService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class AffiliatePayoutsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')
                    ->label('Reference')
                    ->copyable()
                    ->icon(Heroicon::OutlinedIdentification)
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'queued' => 'info',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('total_minor')
                    ->label('Total')
                    ->formatStateUsing(fn (AffiliatePayout $record): string => sprintf(
                        '%s %.2f',
                        $record->currency,
                        $record->total_minor / 100
                    ))
                    ->badge()
                    ->color('primary')
                    ->sortable(),
                TextColumn::make('conversion_count')
                    ->label('Conversions')
                    ->badge()
                    ->color('info'),
                TextColumn::make('paid_at')
                    ->label('Paid At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'draft' => 'Draft',
                    'pending' => 'Pending',
                    'queued' => 'Queued',
                    'paid' => 'Paid',
                    'failed' => 'Failed',
                ]),
            ])
            ->actions([
                Action::make('view')
                    ->label('View')
                    ->icon(Heroicon::OutlinedEye)
                    ->url(fn (AffiliatePayout $record): string => AffiliatePayoutResource::getUrl('view', ['record' => $record])),
                Action::make('mark_paid')
                    ->label('Mark Paid')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->requiresConfirmation()
                    ->authorize(fn (): bool => Filament::auth()->user()?->can('affiliates.payout.update') ?? true)
                    ->visible(fn (AffiliatePayout $record): bool => $record->status !== 'paid')
                    ->action(function (AffiliatePayout $record): void {
                        app(\AIArmada\Affiliates\Services\AffiliatePayoutService::class)->updateStatus($record, 'paid');
                    }),
                Action::make('queue')
                    ->label('Queue')
                    ->icon(Heroicon::OutlinedClock)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->authorize(fn (): bool => Filament::auth()->user()?->can('affiliates.payout.update') ?? true)
                    ->visible(fn (AffiliatePayout $record): bool => $record->status !== 'queued')
                    ->action(function (AffiliatePayout $record): void {
                        app(\AIArmada\Affiliates\Services\AffiliatePayoutService::class)->updateStatus($record, 'queued');
                    }),
                Action::make('fail')
                    ->label('Mark Failed')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->authorize(fn (): bool => Filament::auth()->user()?->can('affiliates.payout.update') ?? true)
                    ->visible(fn (AffiliatePayout $record): bool => $record->status !== 'failed')
                    ->action(function (AffiliatePayout $record): void {
                        app(\AIArmada\Affiliates\Services\AffiliatePayoutService::class)->updateStatus($record, 'failed');
                    }),
                Action::make('export')
                    ->label('Export CSV')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('primary')
                    ->authorize(fn (): bool => Filament::auth()->user()?->can('affiliates.payout.export') ?? true)
                    ->action(fn (AffiliatePayout $record) => app(PayoutExportService::class)->download($record)),
            ])
            ->bulkActions([]);
    }
}
