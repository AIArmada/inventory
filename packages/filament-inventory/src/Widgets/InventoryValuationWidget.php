<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Widgets;

use AIArmada\Inventory\Enums\CostingMethod;
use AIArmada\Inventory\Services\ValuationService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class InventoryValuationWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 20;

    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        return config('filament-inventory.features.valuation_widget', true);
    }

    protected function getStats(): array
    {
        $valuationService = app(ValuationService::class);
        $method = CostingMethod::tryFrom(config('filament-inventory.defaults.costing_method', 'fifo'))
            ?? CostingMethod::Fifo;

        $valuation = $valuationService->getTotalValuation($method);

        $currency = config('inventory.defaults.currency', 'MYR');
        $totalValue = $valuation['total_value'] / 100;
        $avgCost = $valuation['total_quantity'] > 0
            ? ($valuation['total_value'] / $valuation['total_quantity']) / 100
            : 0;

        return [
            Stat::make('Total Inventory Value', $currency . ' ' . number_format($totalValue, 2))
                ->description("Using {$method->shortLabel()} method")
                ->icon('heroicon-o-banknotes')
                ->color('success'),

            Stat::make('Total Units', number_format($valuation['total_quantity']))
                ->description('Across all locations')
                ->icon('heroicon-o-cube')
                ->color('info'),

            Stat::make('Unique SKUs', number_format($valuation['sku_count']))
                ->description('Products tracked')
                ->icon('heroicon-o-tag')
                ->color('primary'),

            Stat::make('Avg Unit Cost', $currency . ' ' . number_format($avgCost, 2))
                ->description('Weighted average')
                ->icon('heroicon-o-calculator')
                ->color('warning'),
        ];
    }
}
