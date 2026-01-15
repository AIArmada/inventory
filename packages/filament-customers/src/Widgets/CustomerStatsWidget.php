<?php

declare(strict_types=1);

namespace AIArmada\FilamentCustomers\Widgets;

use AIArmada\Customers\Enums\CustomerStatus;
use AIArmada\Customers\Models\Customer;
use AIArmada\FilamentCustomers\Support\CustomersOwnerScope;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CustomerStatsWidget extends BaseWidget
{
    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $baseQuery = CustomersOwnerScope::applyToOwnedQuery(Customer::query());

        $now = CarbonImmutable::now();
        $startOfMonth = $now->startOfMonth();
        $thisWeekStart = $now->subWeek();
        $lastWeekStart = $now->subWeeks(2);
        $lastWeekEnd = $now->subWeek();

        $stats = (clone $baseQuery)
            ->toBase()
            ->selectRaw('COUNT(*) as total_customers')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as active_customers', [CustomerStatus::Active->value])
            ->selectRaw('SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as new_this_month', [$startOfMonth])
            ->selectRaw('SUM(CASE WHEN accepts_marketing = 1 THEN 1 ELSE 0 END) as accepts_marketing')
            ->selectRaw('SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as this_week', [$thisWeekStart])
            ->selectRaw('SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END) as last_week', [$lastWeekStart, $lastWeekEnd])
            ->first();

        $totalCustomers = (int) ($stats->total_customers ?? 0);
        $activeCustomers = (int) ($stats->active_customers ?? 0);
        $newThisMonth = (int) ($stats->new_this_month ?? 0);
        $acceptsMarketing = (int) ($stats->accepts_marketing ?? 0);
        $thisWeek = (int) ($stats->this_week ?? 0);
        $lastWeek = (int) ($stats->last_week ?? 0);

        $trend = $lastWeek > 0
            ? round((($thisWeek - $lastWeek) / $lastWeek) * 100)
            : ($thisWeek > 0 ? 100 : 0);

        $trendDescription = $trend >= 0 ? "{$trend}% increase" : abs($trend) . '% decrease';
        $trendIcon = $trend >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down';
        $trendColor = $trend >= 0 ? 'success' : 'danger';

        return [
            Stat::make('Total Customers', number_format($totalCustomers))
                ->description($trendDescription . ' vs last week')
                ->descriptionIcon($trendIcon)
                ->color($trendColor)
                ->chart([$lastWeek, $thisWeek]),

            Stat::make('New This Month', number_format($newThisMonth))
                ->description('Customers joined')
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('info'),

            Stat::make('Active Customers', number_format($activeCustomers))
                ->description('Currently active')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Marketing Opt-In', number_format($acceptsMarketing))
                ->description(round(($acceptsMarketing / max($totalCustomers, 1)) * 100) . '% opt-in rate')
                ->descriptionIcon('heroicon-m-envelope')
                ->color('warning'),
        ];
    }
}
