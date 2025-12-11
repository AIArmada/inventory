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
 * Widget showing carts ready for AI-powered recovery.
 *
 * Displays abandoned carts with recommended recovery strategies
 * based on the AI recovery optimizer.
 */
final class RecoveryOptimizerWidget extends BaseWidget
{
    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Smart Recovery Queue';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('identifier')
                    ->label('Cart ID')
                    ->searchable()
                    ->limit(20),

                Tables\Columns\TextColumn::make('customer')
                    ->label('Customer')
                    ->getStateUsing(fn (Cart $record): string => $this->getCustomerEmail($record)),

                Tables\Columns\TextColumn::make('value')
                    ->label('Cart Value')
                    ->getStateUsing(fn (Cart $record): string => $this->getCartValue($record))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('subtotal', $direction)),

                Tables\Columns\TextColumn::make('recommended_strategy')
                    ->label('Recommended Strategy')
                    ->getStateUsing(fn (Cart $record): string => $this->getRecommendedStrategy($record))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Discount Offer' => 'success',
                        'Free Shipping' => 'info',
                        'Reminder Email' => 'warning',
                        'Personal Outreach' => 'primary',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('priority')
                    ->label('Priority')
                    ->getStateUsing(fn (Cart $record): string => $this->getPriority($record))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'High' => 'danger',
                        'Medium' => 'warning',
                        'Low' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('time_since_abandonment')
                    ->label('Abandoned')
                    ->getStateUsing(fn (Cart $record): string => $this->getTimeSinceAbandonment($record)),

                Tables\Columns\TextColumn::make('recovery_attempts')
                    ->label('Attempts')
                    ->sortable()
                    ->alignCenter(),
            ])
            ->defaultSort('subtotal', 'desc')
            ->actions([
                Action::make('send_discount')
                    ->label('Discount 10%')
                    ->icon('heroicon-o-receipt-percent')
                    ->color('success')
                    ->action(fn (Cart $record) => $this->executeRecovery($record, 'discount', 10)),

                Action::make('send_free_shipping')
                    ->label('Free Ship')
                    ->icon('heroicon-o-truck')
                    ->color('info')
                    ->action(fn (Cart $record) => $this->executeRecovery($record, 'free_shipping')),

                Action::make('send_reminder')
                    ->label('Remind')
                    ->icon('heroicon-o-envelope')
                    ->color('warning')
                    ->action(fn (Cart $record) => $this->executeRecovery($record, 'reminder')),

                Action::make('view')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Cart $record): string => route('filament.admin.resources.carts.view', $record)),
            ])
            ->emptyStateHeading('No carts to recover')
            ->emptyStateDescription('All abandoned carts have been processed or recovered.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->paginated([10, 25, 50]);
    }

    /**
     * @return Builder<Cart>
     */
    protected function getTableQuery(): Builder
    {
        return Cart::query()
            ->whereNotNull('checkout_abandoned_at')
            ->whereNull('recovered_at')
            ->where('recovery_attempts', '<', 3)
            ->where('checkout_abandoned_at', '>=', now()->subDays(7))
            ->where('subtotal', '>', 0);
    }

    private function getCustomerEmail(Cart $record): string
    {
        $metadata = $record->metadata ?? [];

        return $metadata['customer_email'] ?? $metadata['email'] ?? 'Unknown';
    }

    private function getCartValue(Cart $record): string
    {
        $currency = mb_strtoupper($record->currency ?: config('cart.money.default_currency', 'USD'));

        return (string) Money::{$currency}($record->subtotal);
    }

    private function getRecommendedStrategy(Cart $record): string
    {
        $subtotal = $record->subtotal;
        $attempts = $record->recovery_attempts;

        // AI-based strategy recommendation logic
        if ($subtotal >= 10000) { // $100+
            return match ($attempts) {
                0 => 'Reminder Email',
                1 => 'Discount Offer',
                default => 'Personal Outreach',
            };
        }

        if ($subtotal >= 5000) { // $50-$99
            return match ($attempts) {
                0 => 'Reminder Email',
                default => 'Free Shipping',
            };
        }

        // Under $50
        return match ($attempts) {
            0 => 'Reminder Email',
            default => 'Discount Offer',
        };
    }

    private function getPriority(Cart $record): string
    {
        $subtotal = $record->subtotal;
        $hoursAbandoned = $record->checkout_abandoned_at
            ? now()->diffInHours($record->checkout_abandoned_at)
            : 0;

        // High value + recent = high priority
        if ($subtotal >= 10000 && $hoursAbandoned < 24) {
            return 'High';
        }

        if ($subtotal >= 5000 || $hoursAbandoned < 12) {
            return 'Medium';
        }

        return 'Low';
    }

    private function getTimeSinceAbandonment(Cart $record): string
    {
        if (! $record->checkout_abandoned_at) {
            return 'Unknown';
        }

        return $record->checkout_abandoned_at->diffForHumans(['short' => true]);
    }

    private function executeRecovery(Cart $record, string $strategy, ?int $discountPercent = null): void
    {
        $record->increment('recovery_attempts');

        $metadata = $record->metadata ?? [];
        $metadata['last_recovery_strategy'] = $strategy;
        $metadata['last_recovery_at'] = now()->toISOString();

        if ($discountPercent !== null) {
            $metadata['recovery_discount_percent'] = $discountPercent;
        }

        $record->update(['metadata' => $metadata]);

        \Illuminate\Support\Facades\Log::info('Recovery action executed', [
            'cart_id' => $record->id,
            'identifier' => $record->identifier,
            'strategy' => $strategy,
            'discount_percent' => $discountPercent,
            'attempt' => $record->recovery_attempts,
        ]);

        // Here you would dispatch the actual recovery job
        // For example:
        // dispatch(new \AIArmada\Cart\Jobs\ExecuteRecoveryIntervention($record->id, $strategy));
    }
}
