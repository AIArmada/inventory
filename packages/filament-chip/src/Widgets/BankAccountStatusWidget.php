<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Widgets;

use AIArmada\Chip\Models\BankAccount;
use Filament\Widgets\ChartWidget;

final class BankAccountStatusWidget extends ChartWidget
{
    protected ?string $heading = 'Bank Account Status';

    protected ?string $description = 'Distribution of bank account statuses';

    protected static ?int $sort = 13;

    protected ?string $maxHeight = '250px';

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $statuses = $this->getStatusCounts();

        return [
            'datasets' => [
                [
                    'data' => array_values($statuses),
                    'backgroundColor' => [
                        'rgba(16, 185, 129, 0.8)',
                        'rgba(245, 158, 11, 0.8)',
                        'rgba(239, 68, 68, 0.8)',
                        'rgba(107, 114, 128, 0.8)',
                    ],
                    'borderColor' => [
                        'rgb(16, 185, 129)',
                        'rgb(245, 158, 11)',
                        'rgb(239, 68, 68)',
                        'rgb(107, 114, 128)',
                    ],
                    'borderWidth' => 1,
                ],
            ],
            'labels' => array_keys($statuses),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function getOptions(): ?array
    {
        return [
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                ],
            ],
        ];
    }

    /**
     * @return array<string, int>
     */
    private function getStatusCounts(): array
    {
        $active = $this->scopedQuery(BankAccount::query())->whereIn('status', ['active', 'approved'])->count();
        $pending = $this->scopedQuery(BankAccount::query())->whereIn('status', ['pending', 'verifying'])->count();
        $rejected = $this->scopedQuery(BankAccount::query())->whereIn('status', ['rejected', 'disabled'])->count();
        $other = $this->scopedQuery(BankAccount::query())
            ->where(function ($query): void {
                $query->whereNotIn('status', ['active', 'approved', 'pending', 'verifying', 'rejected', 'disabled'])
                    ->orWhereNull('status');
            })
            ->count();

        return [
            'Active' => $active,
            'Pending' => $pending,
            'Rejected' => $rejected,
            'Other' => $other,
        ];
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  \Illuminate\Database\Eloquent\Builder<TModel>  $query
     * @return \Illuminate\Database\Eloquent\Builder<TModel>
     */
    private function scopedQuery(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        if (method_exists($query->getModel(), 'scopeForOwner')) {
            return $query->forOwner(); // @phpstan-ignore method.notFound
        }

        return $query;
    }
}
