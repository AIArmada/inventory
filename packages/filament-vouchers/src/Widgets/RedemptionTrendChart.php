<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Widgets;

use AIArmada\Vouchers\Models\VoucherUsage;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Collection;

final class RedemptionTrendChart extends ChartWidget
{
    public ?string $filter = '30';

    protected ?string $heading = 'Redemption Trend';

    protected ?string $description = 'Daily voucher redemptions over the last 30 days';

    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    protected function getFilters(): ?array
    {
        return [
            '7' => 'Last 7 days',
            '14' => 'Last 14 days',
            '30' => 'Last 30 days',
            '90' => 'Last 90 days',
        ];
    }

    protected function getData(): array
    {
        $days = (int) $this->filter;
        $data = $this->getRedemptionData($days);

        return [
            'datasets' => [
                [
                    'label' => 'Redemptions',
                    'data' => $data->pluck('count')->toArray(),
                    'backgroundColor' => 'rgba(59, 130, 246, 0.5)',
                    'borderColor' => 'rgb(59, 130, 246)',
                    'borderWidth' => 2,
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $data->pluck('date')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'precision' => 0,
                    ],
                ],
            ],
        ];
    }

    /**
     * @return Collection<int, array{date: string, count: int}>
     */
    private function getRedemptionData(int $days): Collection
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        /** @var Collection<string, object{date: string, count: int}> $redemptions */
        $redemptions = VoucherUsage::query()
            ->selectRaw('DATE(used_at) as date, COUNT(*) as count')
            ->where('used_at', '>=', $startDate)
            ->where('used_at', '<=', $endDate)
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $result = collect();

        for ($date = $startDate->copy(); $date <= $endDate; $date->addDay()) {
            $dateStr = $date->format('Y-m-d');
            $dateData = $redemptions->get($dateStr);
            $result->push([
                'date' => $date->format('M j'),
                'count' => $dateData !== null ? (int) data_get($dateData, 'count', 0) : 0,
            ]);
        }

        return $result;
    }
}
