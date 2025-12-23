<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\CustomerPortal\Pages;

use AIArmada\FilamentCashier\Support\GatewayDetector;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Throwable;

final class ManagePaymentMethods extends Page
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament-cashier::customer-portal.manage-payment-methods';

    public static function getNavigationLabel(): string
    {
        return __('filament-cashier::portal.payment_methods.title');
    }

    public function getTitle(): string
    {
        return __('filament-cashier::portal.payment_methods.title');
    }

    /**
     * @return array<string, Collection<int, mixed>>
     */
    public function getPaymentMethods(): array
    {
        $user = auth()->user();

        if ($user === null) {
            return [];
        }

        $methods = [];
        $detector = app(GatewayDetector::class);

        // Get Stripe payment methods
        if ($detector->isAvailable('stripe') && method_exists($user, 'paymentMethods')) {
            try {
                $stripeMethods = $user->paymentMethods();
                $defaultMethod = method_exists($user, 'defaultPaymentMethod') ? $user->defaultPaymentMethod() : null;
                if ($stripeMethods->isNotEmpty()) {
                    $methods['stripe'] = $stripeMethods->map(fn ($pm) => [
                        'id' => $pm->id,
                        'type' => $pm->card->brand ?? 'Card',
                        'last4' => $pm->card->last4 ?? '****',
                        'expiry' => ($pm->card->exp_month ?? '??') . '/' . ($pm->card->exp_year ?? '??'),
                        'is_default' => $defaultMethod !== null && $pm->id === $defaultMethod->id,
                    ]);
                }
            } catch (Throwable) {
                // Silently fail if API is not configured
            }
        }

        // Get CHIP payment methods
        if ($detector->isAvailable('chip') && method_exists($user, 'chipPaymentMethods')) {
            try {
                $chipMethods = $user->chipPaymentMethods();
                if ($chipMethods->isNotEmpty()) {
                    $methods['chip'] = $chipMethods->map(fn ($pm) => [
                        'id' => $pm->id,
                        'type' => $pm->type ?? 'Payment Method',
                        'last4' => $pm->last4 ?? '****',
                        'expiry' => $pm->expiry ?? 'N/A',
                        'is_default' => $pm->is_default ?? false,
                    ]);
                }
            } catch (Throwable) {
                // Silently fail if API is not configured
            }
        }

        return $methods;
    }

    public function setDefaultPaymentMethod(string $gateway, string $methodId): void
    {
        $user = auth()->user();

        if ($user === null) {
            return;
        }

        $detector = app(GatewayDetector::class);

        if (! $detector->isAvailable($gateway)) {
            Notification::make()
                ->title(__('filament-cashier::portal.payment_methods.error'))
                ->body(__('filament-cashier::portal.payment_methods.gateway_not_available'))
                ->danger()
                ->send();

            return;
        }

        try {
            if ($gateway === 'stripe' && method_exists($user, 'updateDefaultPaymentMethod')) {
                $user->updateDefaultPaymentMethod($methodId);

                Notification::make()
                    ->title(__('filament-cashier::portal.payment_methods.default_updated'))
                    ->success()
                    ->send();
            }

            if ($gateway === 'chip' && method_exists($user, 'updateDefaultChipPaymentMethod')) {
                $user->updateDefaultChipPaymentMethod($methodId);

                Notification::make()
                    ->title(__('filament-cashier::portal.payment_methods.default_updated'))
                    ->success()
                    ->send();
            }
        } catch (Throwable $e) {
            Notification::make()
                ->title(__('filament-cashier::portal.payment_methods.error'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function deletePaymentMethod(string $gateway, string $methodId): void
    {
        $user = auth()->user();

        if ($user === null) {
            return;
        }

        $detector = app(GatewayDetector::class);

        if (! $detector->isAvailable($gateway)) {
            Notification::make()
                ->title(__('filament-cashier::portal.payment_methods.error'))
                ->body(__('filament-cashier::portal.payment_methods.gateway_not_available'))
                ->danger()
                ->send();

            return;
        }

        try {
            if ($gateway === 'stripe' && method_exists($user, 'findPaymentMethod')) {
                $method = $user->findPaymentMethod($methodId);
                $method?->delete();

                Notification::make()
                    ->title(__('filament-cashier::portal.payment_methods.deleted'))
                    ->success()
                    ->send();
            }

            if ($gateway === 'chip' && method_exists($user, 'deleteChipPaymentMethod')) {
                $user->deleteChipPaymentMethod($methodId);

                Notification::make()
                    ->title(__('filament-cashier::portal.payment_methods.deleted'))
                    ->success()
                    ->send();
            }
        } catch (Throwable $e) {
            Notification::make()
                ->title(__('filament-cashier::portal.payment_methods.error'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
