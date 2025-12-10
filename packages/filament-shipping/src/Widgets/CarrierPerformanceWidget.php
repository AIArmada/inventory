<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Widgets;

use AIArmada\Shipping\Enums\ShipmentStatus;
use AIArmada\Shipping\Models\Shipment;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class CarrierPerformanceWidget extends ChartWidget
{
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    protected ?string $pollingInterval = '60s';

    public function getHeading(): string
    {
        return 'Carrier Performance';
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $startDate = Carbon::now()->subDays(30);

        $carriers = Shipment::query()
            ->select('carrier_code')
            ->where('created_at', '>=', $startDate)
            ->distinct()
            ->pluck('carrier_code')
            ->toArray();

        $labels = array_map(fn ($code) => ucfirst($code), $carriers);

        $deliveredData = [];
        $inTransitData = [];
        $exceptionsData = [];

        foreach ($carriers as $carrier) {
            $deliveredData[] = Shipment::query()
                ->where('carrier_code', $carrier)
                ->where('status', ShipmentStatus::Delivered)
                ->where('created_at', '>=', $startDate)
                ->count();

            $inTransitData[] = Shipment::query()
                ->where('carrier_code', $carrier)
                ->whereIn('status', [
                    ShipmentStatus::Shipped,
                    ShipmentStatus::InTransit,
                    ShipmentStatus::OutForDelivery,
                ])
                ->where('created_at', '>=', $startDate)
                ->count();

            $exceptionsData[] = Shipment::query()
                ->where('carrier_code', $carrier)
                ->whereIn('status', [
                    ShipmentStatus::Exception,
                    ShipmentStatus::DeliveryFailed,
                ])
                ->where('created_at', '>=', $startDate)
                ->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Delivered',
                    'data' => $deliveredData,
                    'backgroundColor' => 'rgba(34, 197, 94, 0.8)',
                ],
                [
                    'label' => 'In Transit',
                    'data' => $inTransitData,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.8)',
                ],
                [
                    'label' => 'Exceptions',
                    'data' => $exceptionsData,
                    'backgroundColor' => 'rgba(239, 68, 68, 0.8)',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
            ],
            'scales' => [
                'x' => [
                    'stacked' => true,
                ],
                'y' => [
                    'stacked' => true,
                ],
            ],
        ];
    }
}
