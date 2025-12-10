<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Widgets;

use AIArmada\FilamentShipping\Resources\ReturnAuthorizationResource;
use AIArmada\FilamentShipping\Resources\ShipmentResource;
use AIArmada\Shipping\Enums\ShipmentStatus;
use AIArmada\Shipping\Models\ReturnAuthorization;
use AIArmada\Shipping\Models\Shipment;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PendingActionsWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '30s';

    protected static ?int $sort = 4;

    protected function getStats(): array
    {
        return [
            Stat::make('Pending Shipments', $this->getPendingShipmentsCount())
                ->description('Ready to ship')
                ->icon('heroicon-o-inbox-arrow-down')
                ->color('warning')
                ->url(ShipmentResource::getUrl('index', [
                    'tableFilters[status][value]' => ShipmentStatus::Pending->value,
                ])),

            Stat::make('Exceptions', $this->getExceptionShipmentsCount())
                ->description('Need attention')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('danger')
                ->url(ShipmentResource::getUrl('index', [
                    'tableFilters[status][value]' => ShipmentStatus::Exception->value,
                ])),

            Stat::make('Pending Returns', $this->getPendingReturnsCount())
                ->description('Awaiting approval')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('info')
                ->url(ReturnAuthorizationResource::getUrl('index', [
                    'tableFilters[status][value]' => 'pending',
                ])),

            Stat::make('Approved Returns', $this->getApprovedReturnsCount())
                ->description('Awaiting shipment')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->url(ReturnAuthorizationResource::getUrl('index', [
                    'tableFilters[status][value]' => 'approved',
                ])),
        ];
    }

    protected function getPendingShipmentsCount(): int
    {
        return Shipment::query()
            ->whereIn('status', [ShipmentStatus::Draft, ShipmentStatus::Pending])
            ->count();
    }

    protected function getExceptionShipmentsCount(): int
    {
        return Shipment::query()
            ->whereIn('status', [ShipmentStatus::Exception, ShipmentStatus::DeliveryFailed])
            ->count();
    }

    protected function getPendingReturnsCount(): int
    {
        return ReturnAuthorization::query()
            ->where('status', 'pending')
            ->count();
    }

    protected function getApprovedReturnsCount(): int
    {
        return ReturnAuthorization::query()
            ->where('status', 'approved')
            ->count();
    }
}
