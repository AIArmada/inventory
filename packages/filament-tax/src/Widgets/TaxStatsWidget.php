<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Widgets;

use AIArmada\Tax\Models\TaxClass;
use AIArmada\Tax\Models\TaxExemption;
use AIArmada\Tax\Models\TaxRate;
use AIArmada\Tax\Models\TaxZone;
use AIArmada\Tax\Support\TaxOwnerScope;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TaxStatsWidget extends BaseWidget
{
    protected ?string $pollingInterval = '30s';

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $activeZones = TaxOwnerScope::applyToOwnedQuery(TaxZone::query())->active()->count();
        $activeRates = TaxOwnerScope::applyToOwnedQuery(TaxRate::query())->active()->count();
        $taxClasses = TaxOwnerScope::applyToOwnedQuery(TaxClass::query())->active()->count();
        $activeExemptions = TaxOwnerScope::applyToOwnedQuery(TaxExemption::query())->active()->count();

        return [
            Stat::make('Tax Zones', number_format($activeZones))
                ->description('Active zones')
                ->descriptionIcon('heroicon-m-globe-alt')
                ->color('info'),

            Stat::make('Tax Rates', number_format($activeRates))
                ->description('Configured rates')
                ->descriptionIcon('heroicon-m-receipt-percent')
                ->color('success'),

            Stat::make('Tax Classes', number_format($taxClasses))
                ->description('Product categories')
                ->descriptionIcon('heroicon-m-tag')
                ->color('warning'),

            Stat::make('Active Exemptions', number_format($activeExemptions))
                ->description('Approved & valid')
                ->descriptionIcon('heroicon-m-shield-check')
                ->color('gray'),
        ];
    }
}
