<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Widgets;

use AIArmada\Docs\Enums\DocStatus;
use AIArmada\Docs\Models\Doc;
use Filament\Widgets\ChartWidget;

final class StatusBreakdownWidget extends ChartWidget
{
    protected ?string $heading = 'Document Status Breakdown';

    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $statuses = [
            DocStatus::DRAFT,
            DocStatus::PENDING,
            DocStatus::SENT,
            DocStatus::PAID,
            DocStatus::PARTIALLY_PAID,
            DocStatus::OVERDUE,
            DocStatus::CANCELLED,
            DocStatus::REFUNDED,
        ];

        $labels = [];
        $data = [];
        $colors = [];

        foreach ($statuses as $status) {
            $count = Doc::where('status', $status)->count();
            if ($count > 0) {
                $labels[] = $status->label();
                $data[] = $count;
                $colors[] = $this->getColorHex($status->color());
            }
        }

        return [
            'datasets' => [
                [
                    'label' => 'Documents',
                    'data' => $data,
                    'backgroundColor' => $colors,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'position' => 'right',
                ],
            ],
        ];
    }

    private function getColorHex(string $color): string
    {
        return match ($color) {
            'gray' => '#6b7280',
            'warning' => '#f59e0b',
            'info' => '#3b82f6',
            'success' => '#10b981',
            'danger' => '#ef4444',
            'primary' => '#8b5cf6',
            default => '#6b7280',
        };
    }
}
