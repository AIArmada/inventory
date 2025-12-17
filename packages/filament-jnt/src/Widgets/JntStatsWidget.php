<?php

declare(strict_types=1);

namespace AIArmada\FilamentJnt\Widgets;

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Jnt\Models\JntOrder;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class JntStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $query = $this->ordersQuery();

        $totalOrders = (clone $query)->count();
        $deliveredCount = (clone $query)->whereNotNull('delivered_at')->count();
        $inTransitCount = (clone $query)->whereNull('delivered_at')
            ->whereNotNull('tracking_number')
            ->where('has_problem', false)
            ->count();
        $problemCount = (clone $query)->where('has_problem', true)->count();
        $pendingCount = (clone $query)->whereNull('tracking_number')->count();
        $returningCount = (clone $query)->whereIn('last_status_code', ['172', '173'])->count();

        $deliveryRate = $totalOrders > 0
            ? round(($deliveredCount / $totalOrders) * 100, 1)
            : 0;

        return [
            Stat::make('Total Orders', $totalOrders)
                ->description('All shipping orders')
                ->descriptionIcon(Heroicon::RectangleStack)
                ->color('primary'),

            Stat::make('Delivered', $deliveredCount)
                ->description($deliveryRate . '% delivery rate')
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

    /**
     * @return Builder<JntOrder>
     */
    private function ordersQuery(): Builder
    {
        $owner = $this->resolveOwner();
        $includeGlobal = (bool) config('jnt.owner.include_global', true);

        /** @var Builder<JntOrder> $query */
        $query = JntOrder::query()->forOwner($owner, $includeGlobal);

        return $query;
    }

    private function resolveOwner(): ?Model
    {
        if (! app()->bound(OwnerResolverInterface::class)) {
            return null;
        }

        return app(OwnerResolverInterface::class)->resolve();
    }
}
