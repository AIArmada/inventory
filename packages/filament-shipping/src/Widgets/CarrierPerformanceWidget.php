<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Widgets;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Shipping\Enums\ShipmentStatus;
use AIArmada\Shipping\Models\Shipment;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

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

        $query = Shipment::query();

        if ((bool) config('shipping.features.owner.enabled', false)) {
            $owner = OwnerContext::resolve();
            if ($owner === null) {
                return [
                    'datasets' => [],
                    'labels' => [],
                ];
            }

            $query->forOwner($owner, includeGlobal: true);
        }

        $delivered = ShipmentStatus::Delivered->value;
        $inTransitStatuses = [
            ShipmentStatus::Shipped->value,
            ShipmentStatus::InTransit->value,
            ShipmentStatus::OutForDelivery->value,
        ];
        $exceptionStatuses = [
            ShipmentStatus::Exception->value,
            ShipmentStatus::DeliveryFailed->value,
        ];

        $inTransitPlaceholders = implode(', ', array_fill(0, count($inTransitStatuses), '?'));
        $exceptionPlaceholders = implode(', ', array_fill(0, count($exceptionStatuses), '?'));

        $rows = $query
            ->where('created_at', '>=', $startDate)
            ->whereNotNull('carrier_code')
            ->select('carrier_code')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS delivered_count', [$delivered])
            ->selectRaw(
                'SUM(CASE WHEN status IN (' . $inTransitPlaceholders . ') THEN 1 ELSE 0 END) AS in_transit_count',
                $inTransitStatuses
            )
            ->selectRaw(
                'SUM(CASE WHEN status IN (' . $exceptionPlaceholders . ') THEN 1 ELSE 0 END) AS exceptions_count',
                $exceptionStatuses
            )
            ->groupBy('carrier_code')
            ->orderBy('carrier_code')
            ->get();

        $labels = $rows
            ->pluck('carrier_code')
            ->map(fn (?string $code) => $code === null ? 'Unknown' : Str::ucfirst($code))
            ->all();

        $deliveredData = $rows->pluck('delivered_count')->map(fn ($v) => (int) $v)->all();
        $inTransitData = $rows->pluck('in_transit_count')->map(fn ($v) => (int) $v)->all();
        $exceptionsData = $rows->pluck('exceptions_count')->map(fn ($v) => (int) $v)->all();

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

    private function resolveOwner(): ?Model
    {
        if (! (bool) config('shipping.features.owner.enabled', false)) {
            return null;
        }

        return OwnerContext::resolve();
    }
}
