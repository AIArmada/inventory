<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Widgets;

use AIArmada\FilamentVouchers\Support\OwnerScopedQueries;
use AIArmada\Vouchers\Fraud\Enums\FraudRiskLevel;
use AIArmada\Vouchers\Fraud\Models\VoucherFraudSignal;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

final class FraudStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $signals = $this->signals();

        $total = (clone $signals)->count();
        $unreviewed = (clone $signals)->where('reviewed', false)->count();
        $highRisk = (clone $signals)->whereIn('risk_level', [
            FraudRiskLevel::High->value,
            FraudRiskLevel::Critical->value,
        ])->count();
        $blocked = (clone $signals)->where('was_blocked', true)->count();

        $todayCount = (clone $signals)->whereDate('created_at', today())->count();
        $yesterdayCount = (clone $signals)->whereDate('created_at', today()->subDay())->count();

        $trend = $yesterdayCount > 0
            ? round((($todayCount - $yesterdayCount) / $yesterdayCount) * 100, 1)
            : 0;

        return [
            Stat::make('Total Signals', (string) $total)
                ->description('All detected signals'),

            Stat::make('Needs Review', (string) $unreviewed)
                ->description('Pending review')
                ->color($unreviewed > 0 ? 'warning' : 'success'),

            Stat::make('High/Critical Risk', (string) $highRisk)
                ->description('Urgent attention')
                ->color($highRisk > 0 ? 'danger' : 'success'),

            Stat::make('Blocked', (string) $blocked)
                ->description('Redemptions blocked')
                ->color('info'),

            Stat::make('Today', (string) $todayCount)
                ->description(($trend >= 0 ? '+' : '') . $trend . '% vs yesterday')
                ->color($trend > 20 ? 'danger' : 'success'),
        ];
    }

    /**
     * @return Builder<VoucherFraudSignal>
     */
    private function signals(): Builder
    {
        /** @var Builder<VoucherFraudSignal> $query */
        $query = VoucherFraudSignal::query();

        if (! OwnerScopedQueries::isEnabled()) {
            return $query;
        }

        return $query->where(function (Builder $builder): void {
            $builder->whereIn('voucher_id', OwnerScopedQueries::voucherIds())
                ->orWhereIn('voucher_code', OwnerScopedQueries::voucherCodes());
        });
    }
}
