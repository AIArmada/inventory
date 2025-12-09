<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Widgets;

use Filament\Widgets\Widget;

final class AIInsightsWidget extends Widget
{
    protected string $view = 'filament-vouchers::widgets.ai-insights';

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $config = config('vouchers.ai', []);

        return [
            'enabled' => $config['enabled'] ?? true,
            'conversionThresholdHigh' => $config['conversion']['high_probability_threshold'] ?? 0.7,
            'conversionThresholdLow' => $config['conversion']['low_probability_threshold'] ?? 0.3,
            'abandonmentHighRisk' => $config['abandonment']['high_risk_threshold'] ?? 0.6,
            'abandonmentCritical' => $config['abandonment']['critical_risk_threshold'] ?? 0.8,
            'discountMinROI' => $config['discount']['min_roi'] ?? 1.0,
            'discountMaxPercent' => $config['discount']['max_discount_percent'] ?? 50,
            'matchingMinScore' => $config['matching']['min_match_score'] ?? 0.3,
        ];
    }
}
