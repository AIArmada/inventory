<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Actions;

use AIArmada\FilamentCart\Models\Cart;
use AIArmada\FilamentCart\Services\CartInstanceManager;
use AIArmada\FilamentVouchers\Support\OwnerScopedQueries;
use AIArmada\Vouchers\Exceptions\VoucherException;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ApplyVoucherToCartAction
{
    public static function make(): Action
    {
        return Action::make('apply_voucher')
            ->label('Apply Voucher')
            ->icon(Heroicon::OutlinedTicket)
            ->color('success')
            ->modalHeading('Apply Voucher to Cart')
            ->modalDescription('Enter a voucher code to apply a discount to this cart.')
            ->modalSubmitActionLabel('Apply Voucher')
            ->form([
                TextInput::make('voucher_code')
                    ->label('Voucher Code')
                    ->placeholder('e.g., SUMMER2024')
                    ->required()
                    ->maxLength(255)
                    ->autocomplete(false)
                    ->live(onBlur: true)
                    ->helperText('Enter the voucher code you want to apply to this cart.'),
            ])
            ->action(function (array $data, Cart $record): void {
                $code = $data['voucher_code'] ?? '';

                if (OwnerScopedQueries::isEnabled()) {
                    $isVisible = OwnerScopedQueries::scopeVoucherLike(Cart::query())
                        ->whereKey($record->getKey())
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
                        $record->instance,
                        $record->identifier
                    );

                    // Apply voucher using the Cart's voucher methods (added via CartManagerWithVouchers proxy)
                    /** @phpstan-ignore-next-line - applyVoucher method is added dynamically via proxy */
                    $cartInstance->applyVoucher($code);

                    Notification::make()
                        ->success()
                        ->title('Voucher Applied Successfully')
                        ->body("The voucher '{$code}' has been applied to the cart.")
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->send();

                } catch (VoucherException $exception) {
                    Notification::make()
                        ->danger()
                        ->title('Voucher Application Failed')
                        ->body($exception->getMessage())
                        ->icon(Heroicon::OutlinedXCircle)
                        ->persistent()
                        ->send();

                    Log::warning('Failed to apply voucher to cart', [
                        'code' => $code,
                        'cart_identifier' => $record->identifier,
                        'error' => $exception->getMessage(),
                    ]);
                } catch (Throwable $exception) {
                    Notification::make()
                        ->danger()
                        ->title('Unexpected Error')
                        ->body('An unexpected error occurred while applying the voucher.')
                        ->icon(Heroicon::OutlinedXCircle)
                        ->persistent()
                        ->send();

                    Log::error('Unexpected error applying voucher to cart', [
                        'code' => $code,
                        'cart_identifier' => $record->identifier,
                        'error' => $exception->getMessage(),
                        'trace' => $exception->getTraceAsString(),
                    ]);
                }
            });
    }
}
