<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Widgets;

use AIArmada\FilamentCart\Models\Cart;
use AIArmada\FilamentCart\Services\CartInstanceManager;
use AIArmada\FilamentVouchers\Support\OwnerScopedQueries;
use Akaunting\Money\Money;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Lazy;
use Throwable;

/**
 * Widget showing applied vouchers as dismissible badges
 */
#[Lazy]
final class AppliedVoucherBadgesWidget extends Widget
{
    public ?Model $record = null;

    /** @phpstan-ignore-next-line */
    protected string $view = 'filament-vouchers::widgets.applied-voucher-badges';

    protected int | string | array $columnSpan = 'full';

    /**
     * Get applied vouchers for the cart
     *
     * @return array<int, array{code: string, type: string, value: int, currency: string, description: string, status: string, discount_text: string}>
     */
    public function getAppliedVouchers(): array
    {
        if (! $this->record instanceof Cart) {
            return [];
        }

        if (OwnerScopedQueries::isEnabled()) {
            $isVisible = OwnerScopedQueries::scopeVoucherLike(Cart::query())
                ->whereKey($this->record->getKey())
                ->exists();

            if (! $isVisible) {
                return [];
            }
        }

        try {
            $cartInstance = app(CartInstanceManager::class)->resolve(
                $this->record->instance,
                $this->record->identifier
            );

            /** @phpstan-ignore-next-line - getAppliedVouchers method added via proxy */
            $vouchers = $cartInstance->getAppliedVouchers();

            /** @var Collection<int, mixed> $voucherCollection */
            $voucherCollection = collect($vouchers);

            return $voucherCollection->map(function ($voucher) {
                $type = $voucher->type->value ?? 'fixed';
                $value = $voucher->value ?? 0;
                $currency = $voucher->currency ?? 'MYR';

                // Determine discount text based on type
                $discountText = match ($type) {
                    'percentage' => number_format($value / 100, 2) . ' %',
                    'fixed' => Money::{$currency}($value)->format(),
                    'free_shipping' => 'Free Shipping',
                    default => 'Discount',
                };

                // Determine status (active, expiring soon, etc.)
                $status = $this->determineVoucherStatus($voucher);

                return [
                    'code' => $voucher->code ?? 'UNKNOWN',
                    'type' => $type,
                    'value' => $value,
                    'currency' => $currency,
                    'description' => $voucher->description ?? '',
                    'status' => $status,
                    'discount_text' => $discountText,
                ];
            })->toArray();
        } catch (Throwable $exception) {
            Log::warning('Failed to load applied vouchers for badge widget', [
                'cart_id' => $this->record->id,
                'error' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get action to remove a voucher
     */
    public function removeVoucherAction(string $voucherCode): Action
    {
        return Action::make('remove_' . $voucherCode)
            ->label('Remove')
            ->icon(Heroicon::OutlinedXMark)
            ->color('danger')
            ->size('xs')
            ->requiresConfirmation()
            ->modalHeading('Remove Voucher')
            ->modalDescription("Remove voucher '{$voucherCode}' from this cart?")
            ->action(function () use ($voucherCode): void {
                if (! $this->record instanceof Cart) {
                    return;
                }

                if (OwnerScopedQueries::isEnabled()) {
                    $isVisible = OwnerScopedQueries::scopeVoucherLike(Cart::query())
                        ->whereKey($this->record->getKey())
                        ->exists();

                    if (! $isVisible) {
                        Notification::make()
                            ->warning()
                            ->title('Not Authorized')
                            ->body('You cannot modify this cart.')
                            ->icon(Heroicon::OutlinedExclamationCircle)
                            ->send();

                        return;
                    }
                }

                try {
                    $cartInstance = app(CartInstanceManager::class)->resolve(
                        $this->record->instance,
                        $this->record->identifier
                    );

                    /** @phpstan-ignore-next-line - removeVoucher method added via proxy */
                    $cartInstance->removeVoucher($voucherCode);

                    Notification::make()
                        ->success()
                        ->title('Voucher Removed')
                        ->body("Voucher '{$voucherCode}' has been removed.")
                        ->send();

                    // Refresh the page to update cart totals
                    $this->dispatch('$refresh');

                } catch (Throwable $exception) {
                    Notification::make()
                        ->danger()
                        ->title('Error')
                        ->body('Failed to remove voucher. Please try again.')
                        ->send();

                    Log::error('Failed to remove voucher from badge widget', [
                        'code' => $voucherCode,
                        'cart_id' => $this->record->id,
                        'error' => $exception->getMessage(),
                    ]);
                }
            });
    }

    /**
     * Determine voucher status for badge color
     */
    protected function determineVoucherStatus(mixed $voucher): string
    {
        // Check if voucher is expired
        if (property_exists($voucher, 'end_date') && $voucher->end_date) {
            if ($voucher->end_date->isPast()) {
                return 'expired';
            }

            // Expiring within 7 days
            if ($voucher->end_date->diffInDays() <= 7) {
                return 'expiring_soon';
            }
        }

        // Check usage limit
        if (property_exists($voucher, 'usage_limit') && $voucher->usage_limit) {
            $remaining = property_exists($voucher, 'getRemainingUses')
                ? $voucher->getRemainingUses()
                : null;

            if ($remaining !== null && $remaining <= 0) {
                return 'limit_reached';
            }

            if ($remaining !== null && $remaining <= 5) {
                return 'low_uses';
            }
        }

        return 'active';
    }
}
