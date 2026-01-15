<?php

declare(strict_types=1);

namespace AIArmada\FilamentPricing\Widgets;

use AIArmada\Pricing\Models\PriceList;
use AIArmada\Pricing\Support\PricingOwnerScope;
use AIArmada\Promotions\Models\Promotion;
use AIArmada\Promotions\Support\PromotionsOwnerScope;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PricingStatsWidget extends BaseWidget
{
    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $activePriceLists = PricingOwnerScope::applyToOwnedQuery(PriceList::query())
            ->active()
            ->count();

        $stats = [
            Stat::make('Active Price Lists', number_format($activePriceLists))
                ->description('Currently active')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('info'),
        ];

        if (class_exists(Promotion::class)) {
            $promotionQuery = Promotion::query();

            if (PromotionsOwnerScope::isEnabled()) {
                $promotionQuery = $promotionQuery->forOwner();
            }

            $activePromotions = (clone $promotionQuery)->active()->count();
            $totalPromotionUsage = $promotionQuery->sum('usage_count');

            $stats[] = Stat::make('Active Promotions', number_format($activePromotions))
                ->description('Running promotions')
                ->descriptionIcon('heroicon-m-gift')
                ->color('success');

            $stats[] = Stat::make('Promotion Uses', number_format((int) $totalPromotionUsage))
                ->description('Total redemptions')
                ->descriptionIcon('heroicon-m-receipt-percent')
                ->color('warning');
        }

        return $stats;
    }
}
