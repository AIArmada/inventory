<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Widgets;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Shipping\Enums\ShipmentStatus;
use AIArmada\Shipping\Models\ReturnAuthorization;
use AIArmada\Shipping\Models\Shipment;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;

class ShippingDashboardWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        return [
            Stat::make('Pending Shipments', $this->getPendingCount())
                ->description('Awaiting shipping')
                ->icon('heroicon-o-clock')
                ->color('warning'),

            Stat::make('In Transit', $this->getInTransitCount())
                ->description('Currently shipping')
                ->icon('heroicon-o-truck')
                ->color('info'),

            Stat::make('Delivered Today', $this->getDeliveredTodayCount())
                ->description('Successful deliveries')
                ->icon('heroicon-o-check-circle')
                ->color('success'),

            Stat::make('Exceptions', $this->getExceptionsCount())
                ->description('Need attention')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('danger'),

            Stat::make('Pending Returns', $this->getPendingReturnsCount())
                ->description('Awaiting approval')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning'),
        ];
    }

    protected function getPendingCount(): int
    {
        $query = Shipment::query();

        if ((bool) config('shipping.features.owner.enabled', false)) {
            $owner = OwnerContext::resolve();
            if ($owner === null) {
                return 0;
            }

            $query->forOwner($owner, includeGlobal: true);
        }

        return $query
            ->where('status', ShipmentStatus::Pending)
            ->count();
    }

    protected function getInTransitCount(): int
    {
        $query = Shipment::query();

        if ((bool) config('shipping.features.owner.enabled', false)) {
            $owner = OwnerContext::resolve();
            if ($owner === null) {
                return 0;
            }

            $query->forOwner($owner, includeGlobal: true);
        }

        return $query
            ->whereIn('status', [
                ShipmentStatus::Shipped,
                ShipmentStatus::InTransit,
                ShipmentStatus::OutForDelivery,
            ])
            ->count();
    }

    protected function getDeliveredTodayCount(): int
    {
        $query = Shipment::query();

        if ((bool) config('shipping.features.owner.enabled', false)) {
            $owner = OwnerContext::resolve();
            if ($owner === null) {
                return 0;
            }

            $query->forOwner($owner, includeGlobal: true);
        }

        return $query
            ->where('status', ShipmentStatus::Delivered)
            ->whereDate('delivered_at', today())
            ->count();
    }

    protected function getExceptionsCount(): int
    {
        $query = Shipment::query();

        if ((bool) config('shipping.features.owner.enabled', false)) {
            $owner = OwnerContext::resolve();
            if ($owner === null) {
                return 0;
            }

            $query->forOwner($owner, includeGlobal: true);
        }

        return $query
            ->whereIn('status', [
                ShipmentStatus::Exception,
                ShipmentStatus::DeliveryFailed,
            ])
            ->count();
    }

    protected function getPendingReturnsCount(): int
    {
        $query = ReturnAuthorization::query();

        if ((bool) config('shipping.features.owner.enabled', false)) {
            $owner = OwnerContext::resolve();
            if ($owner === null) {
                return 0;
            }

            $query->forOwner($owner, includeGlobal: true);
        }

        return $query
            ->where('status', 'pending')
            ->count();
    }

    private function resolveOwner(): ?Model
    {
        if (! (bool) config('shipping.features.owner.enabled', false)) {
            return null;
        }

        return OwnerContext::resolve();
    }
}
