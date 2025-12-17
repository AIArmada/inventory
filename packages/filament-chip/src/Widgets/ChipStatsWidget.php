<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Widgets;

use AIArmada\Chip\Models\Purchase;
use DateTimeInterface;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

final class ChipStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $todayRevenue = $this->getTodayRevenue();
        $weekRevenue = $this->getWeekRevenue();
        $monthRevenue = $this->getMonthRevenue();
        $successRate = $this->getSuccessRate();

        return [
            Stat::make('Today\'s Revenue', $this->formatCurrency($todayRevenue))
                ->description('Paid purchases today')
                ->descriptionIcon(Heroicon::Banknotes)
                ->color('success'),

            Stat::make('This Week', $this->formatCurrency($weekRevenue))
                ->description('Last 7 days')
                ->descriptionIcon(Heroicon::CalendarDays)
                ->color('primary'),

            Stat::make('This Month', $this->formatCurrency($monthRevenue))
                ->description('Current month')
                ->descriptionIcon(Heroicon::Calendar)
                ->color('info'),

            Stat::make('Success Rate', "{$successRate}%")
                ->description('Paid vs failed')
                ->descriptionIcon(Heroicon::ChartBar)
                ->color($successRate >= 90 ? 'success' : ($successRate >= 70 ? 'warning' : 'danger')),
        ];
    }

    protected function getColumns(): int
    {
        return 4;
    }

    private function getTodayRevenue(): int
    {
        return $this->getRevenueForPeriod(now()->startOfDay());
    }

    private function getWeekRevenue(): int
    {
        return $this->getRevenueForPeriod(now()->subDays(7));
    }

    private function getMonthRevenue(): int
    {
        return $this->getRevenueForPeriod(now()->startOfMonth());
    }

    private function getRevenueForPeriod(DateTimeInterface $since): int
    {
        $sinceTimestamp = $since->getTimestamp();
        $driver = DB::connection()->getDriverName();

        $query = tap(Purchase::query(), function ($query): void {
            if (method_exists($query->getModel(), 'scopeForOwner')) {
                $query->forOwner();
            }
        })
            ->where('status', 'paid')
            ->where('is_test', false)
            ->where('created_on', '>=', $sinceTimestamp);

        $purchases = $query->get();

        return $purchases->sum(function (Purchase $purchase): int {
            $total = $purchase->purchase['total'] ?? $purchase->purchase['amount'] ?? 0;

            if (is_array($total)) {
                return (int) ($total['amount'] ?? 0);
            }

            return (int) $total;
        });
    }

    private function getSuccessRate(): float
    {
        $successStatuses = ['paid', 'completed', 'captured'];
        $failedStatuses = ['failed', 'cancelled'];

        $successful = tap(Purchase::query(), function ($query): void {
            if (method_exists($query->getModel(), 'scopeForOwner')) {
                $query->forOwner();
            }
        })
            ->whereIn('status', $successStatuses)
            ->where('is_test', false)
            ->count();

        $failed = tap(Purchase::query(), function ($query): void {
            if (method_exists($query->getModel(), 'scopeForOwner')) {
                $query->forOwner();
            }
        })
            ->whereIn('status', $failedStatuses)
            ->where('is_test', false)
            ->count();

        $total = $successful + $failed;

        if ($total === 0) {
            return 100.0;
        }

        return round(($successful / $total) * 100, 1);
    }

    private function formatCurrency(int $amountInCents): string
    {
        $currency = config('filament-chip.default_currency', 'MYR');
        $amount = $amountInCents / 100;

        return mb_strtoupper($currency) . ' ' . number_format($amount, 2);
    }
}
