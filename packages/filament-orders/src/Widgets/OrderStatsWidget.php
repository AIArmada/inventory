<?php

declare(strict_types=1);

namespace AIArmada\FilamentOrders\Widgets;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\PendingPayment;
use AIArmada\Orders\States\Processing;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

class OrderStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return $user !== null && Gate::forUser($user)->allows('viewAny', Order::class);
    }

    protected function getStats(): array
    {
        $includeGlobal = (bool) config('orders.owner.include_global', false);
        $baseQuery = Order::query()->forOwner(includeGlobal: $includeGlobal);

        $owner = OwnerContext::resolve();
        $ownerKey = $owner ? ($owner->getMorphClass() . ':' . $owner->getKey()) : 'global';

        $now = Carbon::now()->toImmutable();
        $today = $now->startOfDay();
        $yesterday = $today->subDay();
        $thisMonth = $now->startOfMonth();
        $lastMonthStart = $thisMonth->subMonth();
        $lastMonthEnd = $thisMonth->subSecond();

        $cacheKey = sprintf(
            'filament-orders.stats.%s.%s.%s',
            $ownerKey,
            $includeGlobal ? 'with-global' : 'owner-only',
            $today->toDateString(),
        );

        /** @var array{todayOrders:int,yesterdayOrders:int,todayRevenue:int,yesterdayRevenue:int,pendingOrders:int,monthlyRevenue:int,lastMonthRevenue:int} $computed */
        $computed = Cache::remember($cacheKey, $now->addSeconds(15), function () use ($baseQuery, $today, $yesterday, $thisMonth, $lastMonthStart, $lastMonthEnd): array {
            $todayOrders = (clone $baseQuery)->whereDate('created_at', $today)->count();
            $yesterdayOrders = (clone $baseQuery)->whereDate('created_at', $yesterday)->count();

            $todayRevenue = (clone $baseQuery)
                ->whereDate('created_at', $today)
                ->whereNotNull('paid_at')
                ->sum('grand_total');

            $yesterdayRevenue = (clone $baseQuery)
                ->whereDate('created_at', $yesterday)
                ->whereNotNull('paid_at')
                ->sum('grand_total');

            $pendingOrders = (clone $baseQuery)
                ->whereState('status', [PendingPayment::class, Processing::class])
                ->count();

            $monthlyRevenue = (clone $baseQuery)
                ->where('created_at', '>=', $thisMonth)
                ->whereNotNull('paid_at')
                ->sum('grand_total');

            $lastMonthRevenue = (clone $baseQuery)
                ->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])
                ->whereNotNull('paid_at')
                ->sum('grand_total');

            return [
                'todayOrders' => $todayOrders,
                'yesterdayOrders' => $yesterdayOrders,
                'todayRevenue' => $todayRevenue,
                'yesterdayRevenue' => $yesterdayRevenue,
                'pendingOrders' => $pendingOrders,
                'monthlyRevenue' => $monthlyRevenue,
                'lastMonthRevenue' => $lastMonthRevenue,
            ];
        });

        $todayOrders = $computed['todayOrders'];
        $yesterdayOrders = $computed['yesterdayOrders'];
        $todayChange = $yesterdayOrders > 0
            ? round((($todayOrders - $yesterdayOrders) / $yesterdayOrders) * 100)
            : 0;

        $todayRevenue = $computed['todayRevenue'];
        $yesterdayRevenue = $computed['yesterdayRevenue'];
        $revenueChange = $yesterdayRevenue > 0
            ? round((($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100)
            : 0;

        $pendingOrders = $computed['pendingOrders'];

        $monthlyRevenue = $computed['monthlyRevenue'];
        $lastMonthRevenue = $computed['lastMonthRevenue'];
        $monthlyChange = $lastMonthRevenue > 0
            ? round((($monthlyRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100)
            : 0;

        $currency = (string) config('orders.currency.default', 'MYR');

        return [
            Stat::make('Today\'s Orders', number_format($todayOrders))
                ->description($todayChange >= 0 ? "{$todayChange}% increase" : abs($todayChange) . '% decrease')
                ->descriptionIcon($todayChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($todayChange >= 0 ? 'success' : 'danger')
                ->chart([7, 3, 4, 5, 6, $todayOrders]),

            Stat::make('Today\'s Revenue', $currency . ' ' . number_format($todayRevenue / 100, 2))
                ->description($revenueChange >= 0 ? "{$revenueChange}% increase" : abs($revenueChange) . '% decrease')
                ->descriptionIcon($revenueChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($revenueChange >= 0 ? 'success' : 'danger'),

            Stat::make('Pending Orders', number_format($pendingOrders))
                ->description('Awaiting action')
                ->descriptionIcon('heroicon-m-clock')
                ->color($pendingOrders > 10 ? 'warning' : 'gray'),

            Stat::make('Monthly Revenue', $currency . ' ' . number_format($monthlyRevenue / 100, 2))
                ->description($monthlyChange >= 0 ? "{$monthlyChange}% vs last month" : abs($monthlyChange) . '% vs last month')
                ->descriptionIcon($monthlyChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($monthlyChange >= 0 ? 'success' : 'danger'),
        ];
    }
}
