<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Widgets;

use AIArmada\FilamentCart\Pages\AnalyticsPage;
use AIArmada\FilamentCart\Services\CartAnalyticsService;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Livewire\Attributes\On;

/**
 * Recovery performance analysis widget.
 */
class RecoveryPerformanceWidget extends Widget
{
    protected string $view = 'filament-cart::widgets.recovery-performance';

    protected int | string | array $columnSpan = 'full';

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
        $metrics = $service->getRecoveryMetrics($from, $to);

        // Calculate strategy performance
        $strategies = [];
        foreach ($metrics->by_strategy as $strategy => $data) {
            $rate = $data['attempts'] > 0
                ? ($data['conversions'] / $data['attempts']) * 100
                : 0;

            $strategies[] = [
                'name' => $this->formatStrategyName($strategy),
                'attempts' => $data['attempts'],
                'conversions' => $data['conversions'],
                'revenue' => $data['revenue'],
                'rate' => round($rate, 1),
                'color' => $this->getStrategyColor($strategy),
            ];
        }

        // Sort by conversions
        usort($strategies, fn ($a, $b) => $b['conversions'] <=> $a['conversions']);

        // Calculate potential revenue
        $avgRecoveryValue = $metrics->successful_recoveries > 0
            ? $metrics->recovered_revenue_cents / $metrics->successful_recoveries
            : 0;
        $potentialRevenue = ($metrics->total_abandoned - $metrics->successful_recoveries) * $avgRecoveryValue;

        return [
            'metrics' => $metrics,
            'strategies' => $strategies,
            'potentialRevenue' => (int) $potentialRevenue,
            'summary' => [
                'total_abandoned' => $metrics->total_abandoned,
                'recovery_attempts' => $metrics->recovery_attempts,
                'successful_recoveries' => $metrics->successful_recoveries,
                'recovered_revenue' => $metrics->recovered_revenue_cents,
                'recovery_rate' => round($metrics->recovery_rate * 100, 1),
                'unreached_carts' => $metrics->total_abandoned - $metrics->recovery_attempts,
            ],
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

    private function formatStrategyName(string $strategy): string
    {
        return match ($strategy) {
            'email_reminder' => 'Email Reminder',
            'discount_offer' => 'Discount Offer',
            'free_shipping' => 'Free Shipping',
            'limited_time' => 'Limited Time Offer',
            'social_proof' => 'Social Proof',
            'retargeting' => 'Retargeting Ads',
            'sms' => 'SMS Reminder',
            'push' => 'Push Notification',
            default => ucfirst(str_replace('_', ' ', $strategy)),
        };
    }

    private function getStrategyColor(string $strategy): string
    {
        return match ($strategy) {
            'email_reminder' => 'blue',
            'discount_offer' => 'green',
            'free_shipping' => 'yellow',
            'limited_time' => 'red',
            'social_proof' => 'purple',
            'retargeting' => 'orange',
            'sms' => 'cyan',
            'push' => 'pink',
            default => 'gray',
        };
    }
}
