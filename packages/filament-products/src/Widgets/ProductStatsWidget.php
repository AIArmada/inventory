<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Widgets;

use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Models\Category;
use AIArmada\Products\Models\Collection;
use AIArmada\Products\Models\Product;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ProductStatsWidget extends BaseWidget
{
    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $totalProducts = Product::query()->forOwner()->count();
        $activeProducts = Product::query()->forOwner()->where('status', ProductStatus::Active)->count();
        $draftProducts = Product::query()->forOwner()->where('status', ProductStatus::Draft)->count();
        $totalCategories = Category::query()->forOwner()->count();
        $totalCollections = Collection::query()->forOwner()->where('is_visible', true)->count();

        // Calculate weekly trend
        $lastWeekProducts = Product::query()->forOwner()->where('created_at', '>=', now()->subWeek())->count();
        $previousWeekProducts = Product::query()->forOwner()
            ->whereBetween('created_at', [now()->subWeeks(2), now()->subWeek()])
            ->count();

        $trend = $previousWeekProducts > 0
            ? round((($lastWeekProducts - $previousWeekProducts) / $previousWeekProducts) * 100)
            : ($lastWeekProducts > 0 ? 100 : 0);

        $trendDescription = $trend >= 0 ? "{$trend}% increase" : abs($trend) . '% decrease';
        $trendIcon = $trend >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down';
        $trendColor = $trend >= 0 ? 'success' : 'danger';

        return [
            Stat::make('Total Products', number_format($totalProducts))
                ->description($trendDescription . ' from last week')
                ->descriptionIcon($trendIcon)
                ->color($trendColor)
                ->chart([$previousWeekProducts, $lastWeekProducts]),

            Stat::make('Active Products', number_format($activeProducts))
                ->description(round(($activeProducts / max($totalProducts, 1)) * 100) . '% of total')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Draft Products', number_format($draftProducts))
                ->description('Awaiting publish')
                ->descriptionIcon('heroicon-m-pencil')
                ->color('warning'),

            Stat::make('Categories', number_format($totalCategories))
                ->description("{$totalCollections} collections")
                ->descriptionIcon('heroicon-m-folder')
                ->color('info'),
        ];
    }
}
