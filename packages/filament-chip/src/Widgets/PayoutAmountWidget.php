<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Widgets;

use AIArmada\Chip\Models\SendInstruction;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

final class PayoutAmountWidget extends ChartWidget
{
    protected ?string $heading = 'Payout Volume';

    protected ?string $description = 'Daily payout amounts over the last 30 days';

    protected static ?int $sort = 11;

    protected ?string $maxHeight = '300px';

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $data = $this->getPayoutData();

        return [
            'datasets' => [
                [
                    'label' => 'Payouts (MYR)',
                    'data' => $data['amounts'],
                    'backgroundColor' => 'rgba(16, 185, 129, 0.2)',
                    'borderColor' => 'rgb(16, 185, 129)',
                    'borderWidth' => 2,
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $data['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    /**
     * @return array{labels: array<string>, amounts: array<float>}
     */
    private function getPayoutData(): array
    {
        $start = Carbon::now()->subDays(29)->startOfDay();
        $end = Carbon::now()->endOfDay();

        $daily = tap(SendInstruction::query(), function ($query): void {
            if (method_exists($query->getModel(), 'scopeForOwner')) {
                $query->forOwner();
            }
        })
            ->whereIn('state', ['completed', 'processed'])
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('DATE(created_at) as day, SUM(amount) as total')
            ->groupBy('day')
            ->pluck('total', 'day')
            ->all();

        $labels = [];
        $amounts = [];

        for ($i = 29; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $labels[] = $date->format('M d');

            $key = $date->toDateString();
            $amounts[] = round((float) ($daily[$key] ?? 0), 2);
        }

        return [
            'labels' => $labels,
            'amounts' => $amounts,
        ];
    }
}
