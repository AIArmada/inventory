<?php

declare(strict_types=1);

namespace AIArmada\FilamentJnt\Widgets;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Jnt\Models\JntOrder;
use Carbon\CarbonImmutable;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

final class JntStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $stats = Cache::remember(
            $this->statsCacheKey(),
            CarbonImmutable::now()->addSeconds(30),
            fn (): array => $this->calculateOrderStats()
        );

        $totalOrders = (int) ($stats['total'] ?? 0);
        $deliveredCount = (int) ($stats['delivered'] ?? 0);
        $inTransitCount = (int) ($stats['in_transit'] ?? 0);
        $problemCount = (int) ($stats['problems'] ?? 0);
        $pendingCount = (int) ($stats['pending'] ?? 0);
        $returningCount = (int) ($stats['returns'] ?? 0);

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
        /** @var Builder<JntOrder> $query */
        $query = JntOrder::query();

        if (! (bool) config('jnt.owner.enabled', false)) {
            return $query;
        }

        $owner = OwnerContext::resolve();
        $includeGlobal = (bool) config('jnt.owner.include_global', false);

        return $query->forOwner($owner, $includeGlobal);
    }

    private function statsCacheKey(): string
    {
        $owner = (bool) config('jnt.owner.enabled', false) ? OwnerContext::resolve() : null;
        $ownerKey = $owner instanceof Model
            ? $owner->getMorphClass() . ':' . (string) $owner->getKey()
            : 'none';

        $includeGlobal = (bool) config('jnt.owner.include_global', false);

        return 'filament-jnt:widget:stats:' . $ownerKey . ':' . ($includeGlobal ? '1' : '0');
    }

    /**
     * @return array{total:int, delivered:int, in_transit:int, problems:int, pending:int, returns:int}
     */
    private function calculateOrderStats(): array
    {
        $query = $this->ordersQuery();

        /** @var JntOrder|null $row */
        $row = (clone $query)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN delivered_at IS NOT NULL THEN 1 ELSE 0 END) as delivered')
            ->selectRaw('SUM(CASE WHEN delivered_at IS NULL AND tracking_number IS NOT NULL AND has_problem = 0 THEN 1 ELSE 0 END) as in_transit')
            ->selectRaw('SUM(CASE WHEN has_problem = 1 THEN 1 ELSE 0 END) as problems')
            ->selectRaw('SUM(CASE WHEN tracking_number IS NULL THEN 1 ELSE 0 END) as pending')
            ->selectRaw("SUM(CASE WHEN last_status_code IN ('172','173') THEN 1 ELSE 0 END) as returns")
            ->first();

        return [
            'total' => (int) ($row?->getAttribute('total') ?? 0),
            'delivered' => (int) ($row?->getAttribute('delivered') ?? 0),
            'in_transit' => (int) ($row?->getAttribute('in_transit') ?? 0),
            'problems' => (int) ($row?->getAttribute('problems') ?? 0),
            'pending' => (int) ($row?->getAttribute('pending') ?? 0),
            'returns' => (int) ($row?->getAttribute('returns') ?? 0),
        ];
    }
}
