<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Widgets;

use AIArmada\FilamentCart\Pages\AnalyticsPage;
use AIArmada\FilamentCart\Services\CartAnalyticsService;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Livewire\Attributes\On;

/**
 * Conversion funnel visualization widget.
 */
class ConversionFunnelWidget extends Widget
{
    protected string $view = 'filament-cart::widgets.conversion-funnel';

    protected int | string | array $columnSpan = 1;

    #[On('date-range-updated')]
    public function refresh(): void
    {
        // Widget will refresh on event
    }

    public function getData(): array
    {
        $page = $this->getPageInstance();
        $from = $page?->getDateFrom() ?? Carbon::now()->subDays(30);
        $to = $page?->getDateTo() ?? Carbon::now();

        $service = app(CartAnalyticsService::class);
        $funnel = $service->getConversionFunnel($from, $to);

        $max = max($funnel->carts_created, 1);

        return [
            'funnel' => $funnel,
            'stages' => [
                [
                    'name' => 'Carts Created',
                    'value' => $funnel->carts_created,
                    'percent' => 100,
                    'width' => 100,
                    'color' => 'bg-gray-400',
                ],
                [
                    'name' => 'Items Added',
                    'value' => $funnel->items_added,
                    'percent' => $funnel->carts_created > 0
                        ? round(($funnel->items_added / $funnel->carts_created) * 100, 1)
                        : 0,
                    'width' => ($funnel->items_added / $max) * 100,
                    'color' => 'bg-blue-400',
                    'dropoff' => $funnel->carts_created - $funnel->items_added,
                ],
                [
                    'name' => 'Checkout Started',
                    'value' => $funnel->checkout_started,
                    'percent' => $funnel->carts_created > 0
                        ? round(($funnel->checkout_started / $funnel->carts_created) * 100, 1)
                        : 0,
                    'width' => ($funnel->checkout_started / $max) * 100,
                    'color' => 'bg-yellow-400',
                    'dropoff' => $funnel->items_added - $funnel->checkout_started,
                ],
                [
                    'name' => 'Checkout Completed',
                    'value' => $funnel->checkout_completed,
                    'percent' => $funnel->carts_created > 0
                        ? round(($funnel->checkout_completed / $funnel->carts_created) * 100, 1)
                        : 0,
                    'width' => ($funnel->checkout_completed / $max) * 100,
                    'color' => 'bg-green-400',
                    'dropoff' => $funnel->checkout_started - $funnel->checkout_completed,
                ],
            ],
            'overallDropOff' => round($funnel->getOverallDropOffRate() * 100, 1),
        ];
    }

    private function getPageInstance(): ?AnalyticsPage
    {
        $livewire = $this->getLivewire();

        if ($livewire instanceof AnalyticsPage) {
            return $livewire;
        }

        return null;
    }
}
