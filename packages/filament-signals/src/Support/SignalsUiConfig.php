<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Support;

final class SignalsUiConfig
{
    public static function outcomesLabel(): string
    {
        return (string) config('filament-signals.resources.labels.outcomes', 'Outcomes');
    }

    public static function monetaryValueLabel(): string
    {
        return (string) config('filament-signals.resources.labels.monetary_value', 'Monetary Value');
    }

    public static function averageOutcomeRateLabel(): string
    {
        return 'Average Outcome Rate';
    }
}
