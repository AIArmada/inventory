<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Widgets;

use AIArmada\Tax\Models\TaxClass;
use AIArmada\Tax\Models\TaxExemption;
use AIArmada\Tax\Models\TaxRate;
use AIArmada\Tax\Models\TaxZone;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

final class TaxStatsWidget extends BaseWidget
{
    protected ?string $pollingInterval = '30s';

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $stats = $this->getAggregatedStats();

        return [
            Stat::make('Tax Zones', number_format($stats['zones']))
                ->description('Active zones')
                ->descriptionIcon('heroicon-m-globe-alt')
                ->color('info'),

            Stat::make('Tax Rates', number_format($stats['rates']))
                ->description('Configured rates')
                ->descriptionIcon('heroicon-m-receipt-percent')
                ->color('success'),

            Stat::make('Tax Classes', number_format($stats['classes']))
                ->description('Product categories')
                ->descriptionIcon('heroicon-m-tag')
                ->color('warning'),

            Stat::make('Active Exemptions', number_format($stats['exemptions']))
                ->description('Approved & valid')
                ->descriptionIcon('heroicon-m-shield-check')
                ->color('gray'),
        ];
    }

    /**
     * Fetch all stats in a single optimized query.
     *
     * @return array{zones: int, rates: int, classes: int, exemptions: int}
     */
    private function getAggregatedStats(): array
    {
        $zoneTable = (new TaxZone)->getTable();
        $rateTable = (new TaxRate)->getTable();
        $classTable = (new TaxClass)->getTable();
        $exemptionTable = (new TaxExemption)->getTable();

        $now = now();

        $result = DB::selectOne("
            SELECT
                (SELECT COUNT(*) FROM {$zoneTable} WHERE is_active = 1) as zones,
                (SELECT COUNT(*) FROM {$rateTable} WHERE is_active = 1) as rates,
                (SELECT COUNT(*) FROM {$classTable} WHERE is_active = 1) as classes,
                (SELECT COUNT(*) FROM {$exemptionTable}
                    WHERE status = 'approved'
                    AND (expires_at IS NULL OR expires_at >= ?)
                    AND (starts_at IS NULL OR starts_at <= ?)
                ) as exemptions
        ", [$now, $now]);

        return [
            'zones' => (int) ($result->zones ?? 0),
            'rates' => (int) ($result->rates ?? 0),
            'classes' => (int) ($result->classes ?? 0),
            'exemptions' => (int) ($result->exemptions ?? 0),
        ];
    }
}
