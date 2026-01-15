<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip\Pages;

use AIArmada\FilamentCashierChip\Concerns\InteractsWithBillable;
use BackedEnum;
use Exception;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class PaymentMethods extends Page
{
    use InteractsWithBillable;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?int $navigationSort = 20;

    protected string $view = 'filament-cashier-chip::pages.payment-methods';

    protected static ?string $slug = 'billing/payment-methods';

    public static function getNavigationLabel(): string
    {
        return __('filament-cashier-chip::filament-cashier-chip.payment_methods.title');
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-cashier-chip.navigation.group');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('filament-cashier-chip.billing.features.payment_methods', true);
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        return [
            'billable' => $this->getBillable(),
            'paymentMethods' => $this->getPaymentMethods(),
            'defaultPaymentMethod' => $this->getDefaultPaymentMethod(),
        ];
    }

    public function getAddPaymentMethodUrl(): string
    {
        $billable = $this->getBillable();

        if (! $billable || ! method_exists($billable, 'setupPaymentMethodUrl')) {
            return '#';
        }

        $panelId = $this->getBillingPanelId();
        $successUrl = config('filament-cashier-chip.billing.redirects.after_payment_method_added')
            ?? route("filament.{$panelId}.pages.payment-methods");

        return $billable->setupPaymentMethodUrl([
            'success_url' => $successUrl,
            'cancel_url' => route("filament.{$panelId}.pages.payment-methods"),
        ]);
    }

    public function setAsDefault(string $paymentMethodId): void
    {
        $billable = $this->getBillable();

        if (! $billable) {
            Notification::make()
                ->title(__('Unable to update payment method'))
                ->danger()
                ->send();

            return;
        }

        try {
            $billable->updateDefaultPaymentMethod($paymentMethodId);

            Notification::make()
                ->title(__('Default payment method updated'))
                ->success()
                ->send();
        } catch (Exception $e) {
            Notification::make()
                ->title(__('Failed to update default payment method'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function deletePaymentMethod(string $paymentMethodId): void
    {
        $billable = $this->getBillable();

        if (! $billable) {
            Notification::make()
                ->title(__('Unable to delete payment method'))
                ->danger()
                ->send();

            return;
        }

        try {
            $billable->deletePaymentMethod($paymentMethodId);

            Notification::make()
                ->title(__('Payment method deleted'))
                ->success()
                ->send();
        } catch (Exception $e) {
            Notification::make()
                ->title(__('Failed to delete payment method'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function formatCardBrand(string $brand): string
    {
        $brands = [
            'visa' => 'Visa',
            'mastercard' => 'Mastercard',
            'amex' => 'American Express',
            'discover' => 'Discover',
            'jcb' => 'JCB',
            'diners' => 'Diners Club',
            'unionpay' => 'UnionPay',
        ];

        return $brands[mb_strtolower($brand)] ?? ucfirst($brand);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('add_payment_method')
                ->label(__('Add Payment Method'))
                ->icon(Heroicon::OutlinedPlus)
                ->color('primary')
                ->url(fn () => $this->getAddPaymentMethodUrl())
                ->openUrlInNewTab(false),
        ];
    }
}
