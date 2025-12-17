<?php

declare(strict_types=1);

namespace AIArmada\FilamentOrders\Widgets;

use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\PendingPayment;
use AIArmada\Orders\States\Processing;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OrderStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $today = now()->startOfDay();
        $thisMonth = now()->startOfMonth();

        $baseQuery = Order::query()->forOwner();

        // Today's orders
        $todayOrders = (clone $baseQuery)->whereDate('created_at', $today)->count();
        $yesterdayOrders = (clone $baseQuery)->whereDate('created_at', $today->copy()->subDay())->count();
        $todayChange = $yesterdayOrders > 0
            ? round((($todayOrders - $yesterdayOrders) / $yesterdayOrders) * 100)
            : 0;

        // Today's revenue
        $todayRevenue = (clone $baseQuery)->whereDate('created_at', $today)
            ->whereNotNull('paid_at')
            ->sum('grand_total');
        $yesterdayRevenue = (clone $baseQuery)->whereDate('created_at', $today->copy()->subDay())
            ->whereNotNull('paid_at')
            ->sum('grand_total');
        $revenueChange = $yesterdayRevenue > 0
            ? round((($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100)
            : 0;

        // Pending orders
        $pendingOrders = (clone $baseQuery)->whereState('status', [PendingPayment::class, Processing::class])->count();

        // Monthly revenue
        $monthlyRevenue = (clone $baseQuery)->where('created_at', '>=', $thisMonth)
            ->whereNotNull('paid_at')
            ->sum('grand_total');
        $lastMonthRevenue = (clone $baseQuery)->whereBetween('created_at', [
            $thisMonth->copy()->subMonth(),
            $thisMonth->copy()->subSecond(),
        ])
            ->whereNotNull('paid_at')
            ->sum('grand_total');
        $monthlyChange = $lastMonthRevenue > 0
            ? round((($monthlyRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100)
            : 0;

        return [
            Stat::make('Today\'s Orders', number_format($todayOrders))
                ->description($todayChange >= 0 ? "{$todayChange}% increase" : abs($todayChange) . '% decrease')
                ->descriptionIcon($todayChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($todayChange >= 0 ? 'success' : 'danger')
                ->chart([7, 3, 4, 5, 6, $todayOrders]),

            Stat::make('Today\'s Revenue', 'RM ' . number_format($todayRevenue / 100, 2))
                ->description($revenueChange >= 0 ? "{$revenueChange}% increase" : abs($revenueChange) . '% decrease')
                ->descriptionIcon($revenueChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($revenueChange >= 0 ? 'success' : 'danger'),

            Stat::make('Pending Orders', number_format($pendingOrders))
                ->description('Awaiting action')
                ->descriptionIcon('heroicon-m-clock')
                ->color($pendingOrders > 10 ? 'warning' : 'gray'),

            Stat::make('Monthly Revenue', 'RM ' . number_format($monthlyRevenue / 100, 2))
                ->description($monthlyChange >= 0 ? "{$monthlyChange}% vs last month" : abs($monthlyChange) . '% vs last month')
                ->descriptionIcon($monthlyChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($monthlyChange >= 0 ? 'success' : 'danger'),
        ];
    }
}
