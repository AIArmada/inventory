<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Widgets;

use AIArmada\Chip\Enums\RecurringStatus;
use AIArmada\Chip\Models\RecurringCharge;
use AIArmada\Chip\Models\RecurringSchedule;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class RecurringStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $totalSchedules = RecurringSchedule::count();
        $activeSchedules = RecurringSchedule::where('status', RecurringStatus::Active)->count();
        $pausedSchedules = RecurringSchedule::where('status', RecurringStatus::Paused)->count();
        $dueSchedules = RecurringSchedule::where('status', RecurringStatus::Active)
            ->whereNotNull('next_charge_at')
            ->where('next_charge_at', '<=', now())
            ->count();

        $successfulCharges = RecurringCharge::where('status', 'success')->count();
        $failedCharges = RecurringCharge::where('status', 'failed')->count();

        $successRate = ($successfulCharges + $failedCharges) > 0
            ? round(($successfulCharges / ($successfulCharges + $failedCharges)) * 100, 1)
            : 0;

        return [
            Stat::make('Active Schedules', $activeSchedules)
                ->description("{$totalSchedules} total schedules")
                ->descriptionIcon(Heroicon::ArrowPath)
                ->color('success'),

            Stat::make('Due Now', $dueSchedules)
                ->description('Awaiting processing')
                ->descriptionIcon(Heroicon::Clock)
                ->color($dueSchedules > 0 ? 'warning' : 'success'),

            Stat::make('Paused', $pausedSchedules)
                ->description('Temporarily paused')
                ->descriptionIcon(Heroicon::Pause)
                ->color('gray'),

            Stat::make('Success Rate', "{$successRate}%")
                ->description("{$successfulCharges} successful / {$failedCharges} failed")
                ->descriptionIcon(Heroicon::ChartBar)
                ->color($successRate >= 95 ? 'success' : ($successRate >= 80 ? 'warning' : 'danger')),
        ];
    }

    protected function getColumns(): int
    {
        return 4;
    }
}
