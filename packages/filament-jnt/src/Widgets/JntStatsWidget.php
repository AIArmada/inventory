<?php

declare(strict_types=1);

namespace AIArmada\FilamentJnt\Widgets;

use AIArmada\Jnt\Enums\TrackingStatus;
use AIArmada\Jnt\Models\JntOrder;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class JntStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $totalOrders = JntOrder::count();
        $deliveredCount = JntOrder::whereNotNull('delivered_at')->count();
        $inTransitCount = JntOrder::whereNull('delivered_at')
            ->whereNotNull('tracking_number')
            ->where('has_problem', false)
            ->count();
        $problemCount = JntOrder::where('has_problem', true)->count();
        $pendingCount = JntOrder::whereNull('tracking_number')->count();
        $returningCount = JntOrder::whereIn('last_status_code', ['172', '173'])->count();

        $deliveryRate = $totalOrders > 0
            ? round(($deliveredCount / $totalOrders) * 100, 1)
            : 0;

        return [
            Stat::make('Total Orders', $totalOrders)
                ->description('All shipping orders')
                ->descriptionIcon(Heroicon::RectangleStack)
                ->color('primary'),

            Stat::make('Delivered', $deliveredCount)
                ->description($deliveryRate.'% delivery rate')
                ->descriptionIcon(Heroicon::CheckCircle)
                ->color('success'),

            Stat::make('In Transit', $inTransitCount)
                ->description('On the way')
                ->descriptionIcon(Heroicon::Truck)
                ->color('info'),

            Stat::make('Pending', $pendingCount)
                ->description('Awaiting pickup')
                ->descriptionIcon(Heroicon::Clock)
                ->color('warning'),

            Stat::make('Returns', $returningCount)
                ->description('Being returned')
                ->descriptionIcon(Heroicon::ArrowUturnLeft)
                ->color($returningCount > 0 ? 'purple' : 'gray'),

            Stat::make('Problems', $problemCount)
                ->description('Requires attention')
                ->descriptionIcon(Heroicon::ExclamationTriangle)
                ->color($problemCount > 0 ? 'danger' : 'success'),
        ];
    }

    protected function getColumns(): int
    {
        return 6;
    }
}
