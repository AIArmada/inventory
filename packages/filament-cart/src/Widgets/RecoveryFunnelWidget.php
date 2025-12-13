<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Widgets;

use AIArmada\FilamentCart\Models\RecoveryCampaign;
use Filament\Widgets\Widget;

/**
 * Recovery funnel visualization widget.
 */
class RecoveryFunnelWidget extends Widget
{
    protected string $view = 'filament-cart::widgets.recovery-funnel';

    protected int | string | array $columnSpan = 1;

    public function getData(): array
    {
        // Get totals from campaigns
        $totals = RecoveryCampaign::query()
            ->selectRaw('
                SUM(total_targeted) as targeted,
                SUM(total_sent) as sent,
                SUM(total_opened) as opened,
                SUM(total_clicked) as clicked,
                SUM(total_recovered) as recovered
            ')
            ->first();

        $targeted = $totals?->targeted ?? 0;
        $sent = $totals?->sent ?? 0;
        $opened = $totals?->opened ?? 0;
        $clicked = $totals?->clicked ?? 0;
        $recovered = $totals?->recovered ?? 0;

        $max = max($targeted, 1);

        return [
            'stages' => [
                [
                    'name' => 'Carts Targeted',
                    'value' => $targeted,
                    'percent' => 100,
                    'width' => 100,
                    'color' => 'bg-gray-400',
                ],
                [
                    'name' => 'Messages Sent',
                    'value' => $sent,
                    'percent' => $targeted > 0 ? round(($sent / $targeted) * 100, 1) : 0,
                    'width' => ($sent / $max) * 100,
                    'color' => 'bg-blue-400',
                    'dropoff' => $targeted - $sent,
                ],
                [
                    'name' => 'Opened',
                    'value' => $opened,
                    'percent' => $targeted > 0 ? round(($opened / $targeted) * 100, 1) : 0,
                    'width' => ($opened / $max) * 100,
                    'color' => 'bg-cyan-400',
                    'dropoff' => $sent - $opened,
                ],
                [
                    'name' => 'Clicked',
                    'value' => $clicked,
                    'percent' => $targeted > 0 ? round(($clicked / $targeted) * 100, 1) : 0,
                    'width' => ($clicked / $max) * 100,
                    'color' => 'bg-yellow-400',
                    'dropoff' => $opened - $clicked,
                ],
                [
                    'name' => 'Recovered',
                    'value' => $recovered,
                    'percent' => $targeted > 0 ? round(($recovered / $targeted) * 100, 1) : 0,
                    'width' => ($recovered / $max) * 100,
                    'color' => 'bg-green-400',
                    'dropoff' => $clicked - $recovered,
                ],
            ],
            'overallConversion' => $targeted > 0 ? round(($recovered / $targeted) * 100, 1) : 0,
        ];
    }
}
