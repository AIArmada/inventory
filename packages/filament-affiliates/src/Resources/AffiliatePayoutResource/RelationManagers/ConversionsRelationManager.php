<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliatePayoutResource\RelationManagers;

use AIArmada\Affiliates\Models\AffiliateConversion;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class ConversionsRelationManager extends RelationManager
{
    protected static string $relationship = 'conversions';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('affiliate_code')
                    ->label('Affiliate')
                    ->badge()
                    ->color('primary'),
                TextColumn::make('order_reference')
                    ->label('Order')
                    ->icon(Heroicon::OutlinedReceiptPercent)
                    ->placeholder('—'),
                TextColumn::make('commission_minor')
                    ->label('Commission')
                    ->formatStateUsing(fn (AffiliateConversion $record): string => sprintf(
                        '%s %.2f',
                        $record->commission_currency,
                        $record->commission_minor / 100
                    ))
                    ->badge()
                    ->color('success'),
                TextColumn::make('status')
                    ->badge()
                    ->color('info'),
                TextColumn::make('occurred_at')
                    ->label('Occurred')
                    ->dateTime(),
            ])
            ->actions([])
            ->bulkActions([])
            ->emptyStateHeading('No conversions assigned');
    }
}
