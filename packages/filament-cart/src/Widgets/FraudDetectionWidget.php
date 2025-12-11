<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Widgets;

use AIArmada\FilamentCart\Models\Cart;
use Akaunting\Money\Money;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Widget showing carts with fraud risk indicators.
 *
 * Displays carts flagged by the fraud detection engine
 * with their risk levels and recommended actions.
 */
final class FraudDetectionWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Fraud Risk Alerts';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('identifier')
                    ->label('Cart ID')
                    ->searchable()
                    ->limit(20),

                Tables\Columns\TextColumn::make('fraud_risk_level')
                    ->label('Risk Level')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'high' => 'danger',
                        'medium' => 'warning',
                        'low' => 'info',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('fraud_score')
                    ->label('Score')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->color(fn (?float $state): string => match (true) {
                        $state === null => 'gray',
                        $state >= 0.8 => 'danger',
                        $state >= 0.6 => 'warning',
                        default => 'success',
                    }),

                Tables\Columns\TextColumn::make('value')
                    ->label('Cart Value')
                    ->getStateUsing(fn (Cart $record): string => $this->getCartValue($record)),

                Tables\Columns\TextColumn::make('items_count')
                    ->label('Items')
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer')
                    ->label('Customer')
                    ->getStateUsing(fn (Cart $record): string => $this->getCustomerInfo($record)),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Activity')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('fraud_score', 'desc')
            ->actions([
                Action::make('view')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Cart $record): string => route('filament.admin.resources.carts.view', $record)),

                Action::make('block')
                    ->label('Block Cart')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Block Suspicious Cart')
                    ->modalDescription('This will prevent further checkout attempts for this cart. The customer will need to create a new cart.')
                    ->action(fn (Cart $record) => $this->blockCart($record))
                    ->visible(fn (Cart $record): bool => $record->fraud_risk_level === 'high'),

                Action::make('review')
                    ->label('Mark Reviewed')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->action(fn (Cart $record) => $this->markReviewed($record)),
            ])
            ->emptyStateHeading('No fraud alerts')
            ->emptyStateDescription('No carts with suspicious activity detected.')
            ->emptyStateIcon('heroicon-o-shield-check')
            ->paginated([10, 25, 50]);
    }

    /**
     * @return Builder<Cart>
     */
    protected function getTableQuery(): Builder
    {
        return Cart::query()
            ->whereNotNull('fraud_risk_level')
            ->whereIn('fraud_risk_level', ['high', 'medium'])
            ->where('updated_at', '>=', now()->subDays(7));
    }

    private function getCartValue(Cart $record): string
    {
        $currency = mb_strtoupper($record->currency ?: config('cart.money.default_currency', 'USD'));

        return (string) Money::{$currency}($record->subtotal);
    }

    private function getCustomerInfo(Cart $record): string
    {
        $metadata = $record->metadata ?? [];

        return $metadata['customer_email'] ?? $metadata['email'] ?? $record->identifier;
    }

    private function blockCart(Cart $record): void
    {
        $metadata = $record->metadata ?? [];
        $metadata['blocked'] = true;
        $metadata['blocked_at'] = now()->toISOString();
        $metadata['blocked_reason'] = 'fraud_detection';

        $record->update(['metadata' => $metadata]);

        \Illuminate\Support\Facades\Log::warning('Cart blocked due to fraud risk', [
            'cart_id' => $record->id,
            'identifier' => $record->identifier,
            'fraud_score' => $record->fraud_score,
            'fraud_risk_level' => $record->fraud_risk_level,
        ]);
    }

    private function markReviewed(Cart $record): void
    {
        $metadata = $record->metadata ?? [];
        $metadata['fraud_reviewed'] = true;
        $metadata['fraud_reviewed_at'] = now()->toISOString();

        $record->update([
            'metadata' => $metadata,
            'fraud_risk_level' => 'reviewed',
        ]);
    }
}
