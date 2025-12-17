<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Widgets;

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;

final class PerformanceOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        /** @var Model|null $owner */
        $owner = (bool) config('affiliates.owner.enabled', false) && app()->bound(OwnerResolverInterface::class)
            ? app(OwnerResolverInterface::class)->resolve()
            : null;

        $now = now();
        $startOfMonth = $now->copy()->startOfMonth();
        $lastMonth = $now->copy()->subMonth();

        // This month stats
        $thisMonthConversions = AffiliateConversion::query()
            ->when(
                (bool) config('affiliates.owner.enabled', false),
                fn ($query) => $query->forOwner($owner),
            )
            ->where('occurred_at', '>=', $startOfMonth)
            ->count();

        $thisMonthRevenue = AffiliateConversion::query()
            ->when(
                (bool) config('affiliates.owner.enabled', false),
                fn ($query) => $query->forOwner($owner),
            )
            ->where('occurred_at', '>=', $startOfMonth)
            ->sum('total_minor');

        $thisMonthCommission = AffiliateConversion::query()
            ->when(
                (bool) config('affiliates.owner.enabled', false),
                fn ($query) => $query->forOwner($owner),
            )
            ->where('occurred_at', '>=', $startOfMonth)
            ->sum('commission_minor');

        // Last month for comparison
        $lastMonthConversions = AffiliateConversion::query()
            ->when(
                (bool) config('affiliates.owner.enabled', false),
                fn ($query) => $query->forOwner($owner),
            )
            ->whereBetween('occurred_at', [$lastMonth->startOfMonth(), $lastMonth->endOfMonth()])
            ->count();

        $lastMonthRevenue = AffiliateConversion::query()
            ->when(
                (bool) config('affiliates.owner.enabled', false),
                fn ($query) => $query->forOwner($owner),
            )
            ->whereBetween('occurred_at', [$lastMonth->startOfMonth(), $lastMonth->endOfMonth()])
            ->sum('total_minor');

        // Active affiliates
        $activeAffiliates = Affiliate::query()
            ->when(
                (bool) config('affiliates.owner.enabled', false),
                fn ($query) => $query->forOwner($owner),
            )
            ->where('status', AffiliateStatus::Active)
            ->count();

        $newAffiliates = Affiliate::query()
            ->when(
                (bool) config('affiliates.owner.enabled', false),
                fn ($query) => $query->forOwner($owner),
            )
            ->where('created_at', '>=', $startOfMonth)
            ->count();

        return [
            Stat::make('Conversions This Month', Number::format($thisMonthConversions))
                ->description($this->getChangeDescription($thisMonthConversions, $lastMonthConversions))
                ->descriptionIcon($thisMonthConversions >= $lastMonthConversions ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($thisMonthConversions >= $lastMonthConversions ? 'success' : 'danger'),

            Stat::make('Revenue This Month', $this->formatMoney($thisMonthRevenue))
                ->description($this->getChangeDescription($thisMonthRevenue, $lastMonthRevenue))
                ->descriptionIcon($thisMonthRevenue >= $lastMonthRevenue ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($thisMonthRevenue >= $lastMonthRevenue ? 'success' : 'danger'),

            Stat::make('Commission Earned', $this->formatMoney($thisMonthCommission))
                ->description('This month'),

            Stat::make('Active Affiliates', Number::format($activeAffiliates))
                ->description("{$newAffiliates} joined this month")
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('primary'),
        ];
    }

    private function getChangeDescription(int | float $current, int | float $previous): string
    {
        if ($previous === 0) {
            return $current > 0 ? '+100%' : '0%';
        }

        $change = (($current - $previous) / $previous) * 100;
        $sign = $change >= 0 ? '+' : '';

        return $sign . Number::format($change, precision: 1) . '% from last month';
    }

    private function formatMoney(int $amountMinor): string
    {
        return Number::currency($amountMinor / 100, config('affiliates.currency.default', 'USD'));
    }
}
