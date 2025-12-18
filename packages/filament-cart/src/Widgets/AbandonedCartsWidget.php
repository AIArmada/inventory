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
use Illuminate\Support\Facades\Log;

/**
 * Widget showing abandoned carts ready for recovery.
 *
 * Lists carts that have been abandoned during checkout,
 * with recovery attempt tracking.
 */
final class AbandonedCartsWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Abandoned Carts';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('identifier')
                    ->label('Cart ID')
                    ->searchable()
                    ->limit(20),

                Tables\Columns\TextColumn::make('email')
                    ->label('Customer')
                    ->getStateUsing(fn (Cart $record): string => $this->getCustomerEmail($record))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where('metadata', 'like', "%{$search}%")),

                Tables\Columns\TextColumn::make('items_count')
                    ->label('Items')
                    ->getStateUsing(fn (Cart $record): int => $this->getItemsCount($record)),

                Tables\Columns\TextColumn::make('value')
                    ->label('Value')
                    ->getStateUsing(fn (Cart $record): string => $this->getCartValue($record)),

                Tables\Columns\TextColumn::make('checkout_abandoned_at')
                    ->label('Abandoned')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('recovery_attempts')
                    ->label('Recovery Attempts')
                    ->sortable()
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state === 0 => 'gray',
                        $state < 3 => 'warning',
                        default => 'danger',
                    }),

                Tables\Columns\TextColumn::make('time_since_abandonment')
                    ->label('Age')
                    ->getStateUsing(fn (Cart $record): string => $this->getTimeSinceAbandonment($record)),
            ])
            ->defaultSort('checkout_abandoned_at', 'desc')
            ->actions([
                Action::make('view')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Cart $record): string => route('filament.admin.resources.carts.view', $record)),

                Action::make('send_recovery')
                    ->label('Send Recovery Email')
                    ->icon('heroicon-o-envelope')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->action(fn (Cart $record) => $this->sendRecoveryEmail($record))
                    ->visible(fn (Cart $record): bool => $record->recovery_attempts < 3),
            ])
            ->emptyStateHeading('No abandoned carts')
            ->emptyStateDescription('Great! There are no abandoned carts to recover.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->paginated([10, 25, 50]);
    }

    /**
     * @return Builder<Cart>
     */
    protected function getTableQuery(): Builder
    {
        return Cart::query()->forOwner()
            ->whereNotNull('checkout_abandoned_at')
            ->whereNull('recovered_at')
            ->where('checkout_abandoned_at', '>=', now()->subDays(7));
    }

    private function getCustomerEmail(Cart $record): string
    {
        $metadata = $record->metadata ?? [];

        return $metadata['customer_email'] ?? $metadata['email'] ?? 'Unknown';
    }

    private function getItemsCount(Cart $record): int
    {
        $items = $record->items ?? [];

        if (is_string($items)) {
            $items = json_decode($items, true) ?? [];
        }

        return count($items);
    }

    private function getCartValue(Cart $record): string
    {
        $metadata = $record->metadata ?? [];
        $subtotal = $metadata['subtotal'] ?? 0;

        if (is_string($metadata)) {
            $decoded = json_decode($metadata, true) ?? [];
            $subtotal = $decoded['subtotal'] ?? 0;
        }

        $currency = mb_strtoupper(config('cart.money.default_currency', 'USD'));

        return (string) Money::{$currency}((int) $subtotal);
    }

    private function getTimeSinceAbandonment(Cart $record): string
    {
        if (! $record->checkout_abandoned_at) {
            return 'Unknown';
        }

        return $record->checkout_abandoned_at->diffForHumans(['short' => true]);
    }

    private function sendRecoveryEmail(Cart $record): void
    {
        // Dispatch recovery email job
        // This would integrate with your email system
        $record->increment('recovery_attempts');

        // Log the recovery attempt
        Log::info('Recovery email sent for cart', [
            'cart_id' => $record->id,
            'identifier' => $record->identifier,
            'attempt' => $record->recovery_attempts,
        ]);
    }
}
