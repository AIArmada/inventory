<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Widgets;

use AIArmada\Chip\Models\Purchase;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class PaymentMethodsWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $methods = $this->getPaymentMethodBreakdown();

        $stats = [];

        foreach ($methods as $method => $data) {
            $stats[] = Stat::make($method, $data['count'])
                ->description($this->formatCurrency($data['amount']))
                ->descriptionIcon($this->getMethodIcon($method))
                ->color($this->getMethodColor($method));
        }

        if (count($stats) === 0) {
            $stats[] = Stat::make('No Payments', 0)
                ->description('No payment data available')
                ->descriptionIcon(Heroicon::CreditCard)
                ->color('gray');
        }

        return $stats;
    }

    protected function getColumns(): int
    {
        return min(count($this->getPaymentMethodBreakdown()), 4) ?: 1;
    }

    /**
     * @return array<string, array{count: int, amount: int}>
     */
    private function getPaymentMethodBreakdown(): array
    {
        $purchases = tap(Purchase::query(), function ($query): void {
            if (method_exists($query->getModel(), 'scopeForOwner')) {
                $query->forOwner();
            }
        })
            ->whereIn('status', ['paid', 'completed', 'captured'])
            ->where('is_test', false)
            ->get();

        $breakdown = [];

        foreach ($purchases as $purchase) {
            $method = $this->extractPaymentMethod($purchase);

            if (! isset($breakdown[$method])) {
                $breakdown[$method] = ['count' => 0, 'amount' => 0];
            }

            $breakdown[$method]['count']++;
            $breakdown[$method]['amount'] += $this->extractAmount($purchase);
        }

        arsort($breakdown);

        return array_slice($breakdown, 0, 4, true);
    }

    private function extractPaymentMethod(Purchase $purchase): string
    {
        $payment = $purchase->payment ?? [];
        $transactionData = $purchase->transaction_data ?? [];

        $method = $payment['payment_type'] ?? $transactionData['payment_method'] ?? 'unknown';

        return match ($method) {
            'fpx', 'FPX' => 'FPX',
            'card', 'credit_card', 'debit_card' => 'Card',
            'ewallet', 'e-wallet' => 'E-Wallet',
            'bnpl', 'buy_now_pay_later' => 'BNPL',
            'bank_transfer' => 'Bank Transfer',
            default => ucfirst((string) $method),
        };
    }

    private function extractAmount(Purchase $purchase): int
    {
        $total = $purchase->purchase['total'] ?? $purchase->purchase['amount'] ?? 0;

        if (is_array($total)) {
            return (int) ($total['amount'] ?? 0);
        }

        return (int) $total;
    }

    private function getMethodIcon(string $method): Heroicon
    {
        return match ($method) {
            'FPX' => Heroicon::BuildingLibrary,
            'Card' => Heroicon::CreditCard,
            'E-Wallet' => Heroicon::DevicePhoneMobile,
            'BNPL' => Heroicon::Clock,
            'Bank Transfer' => Heroicon::BuildingOffice,
            default => Heroicon::Banknotes,
        };
    }

    private function getMethodColor(string $method): string
    {
        return match ($method) {
            'FPX' => 'success',
            'Card' => 'primary',
            'E-Wallet' => 'info',
            'BNPL' => 'warning',
            'Bank Transfer' => 'gray',
            default => 'secondary',
        };
    }

    private function formatCurrency(int $amountInCents): string
    {
        $currency = config('filament-chip.default_currency', 'MYR');
        $amount = $amountInCents / 100;

        return mb_strtoupper($currency) . ' ' . number_format($amount, 2);
    }
}
