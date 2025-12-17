<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Widgets;

use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Enums\ProductType;
use AIArmada\Products\Models\Product;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class LowStockAlertWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        // Count products by type for visibility into catalog composition
        $physicalProducts = Product::query()->forOwner()
            ->where('status', ProductStatus::Active)
            ->whereIn('type', [ProductType::Simple, ProductType::Configurable, ProductType::Bundle])
            ->where('requires_shipping', true)
            ->count();

        $digitalProducts = Product::query()->forOwner()
            ->where('status', ProductStatus::Active)
            ->where('type', ProductType::Digital)
            ->count();

        $subscriptionProducts = Product::query()->forOwner()
            ->where('status', ProductStatus::Active)
            ->where('type', ProductType::Subscription)
            ->count();

        $totalActive = Product::query()->forOwner()
            ->where('status', ProductStatus::Active)
            ->count();

        return [
            Stat::make('Physical Products', $physicalProducts)
                ->description('Require shipping')
                ->descriptionIcon('heroicon-o-truck')
                ->color('info'),

            Stat::make('Digital Products', $digitalProducts)
                ->description('Downloadable')
                ->descriptionIcon('heroicon-o-cloud-arrow-down')
                ->color('success'),

            Stat::make('Subscriptions', $subscriptionProducts)
                ->description('Recurring billing')
                ->descriptionIcon('heroicon-o-arrow-path')
                ->color('primary'),

            Stat::make('Total Active', $totalActive)
                ->description('Active in catalog')
                ->descriptionIcon('heroicon-o-check-circle')
                ->color('success'),
        ];
    }
}
