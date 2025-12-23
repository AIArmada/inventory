<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip\Pages;

use AIArmada\FilamentCashierChip\Widgets\ActiveSubscribersWidget;
use AIArmada\FilamentCashierChip\Widgets\AttentionRequiredWidget;
use AIArmada\FilamentCashierChip\Widgets\ChurnRateWidget;
use AIArmada\FilamentCashierChip\Widgets\MRRWidget;
use AIArmada\FilamentCashierChip\Widgets\RevenueChartWidget;
use AIArmada\FilamentCashierChip\Widgets\SubscriptionDistributionWidget;
use AIArmada\FilamentCashierChip\Widgets\TrialConversionsWidget;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

final class BillingDashboard extends Page
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected string $view = 'filament-cashier-chip::pages.billing-dashboard';

    protected static ?string $slug = 'billing-dashboard';

    protected static ?int $navigationSort = 0;

    public static function getNavigationLabel(): string
    {
        return __('filament-cashier-chip::filament-cashier-chip.dashboard.title');
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-cashier-chip.navigation.group');
    }

    public function getTitle(): string
    {
        return __('filament-cashier-chip::filament-cashier-chip.dashboard.title');
    }

    public function getHeaderWidgets(): array
    {
        if (! config('filament-cashier-chip.features.dashboard_widgets', true)) {
            return [];
        }

        $widgets = [];

        if (config('filament-cashier-chip.features.dashboard.widgets.mrr', true)) {
            $widgets[] = MRRWidget::class;
        }

        if (config('filament-cashier-chip.features.dashboard.widgets.active_subscribers', true)) {
            $widgets[] = ActiveSubscribersWidget::class;
        }

        if (config('filament-cashier-chip.features.dashboard.widgets.churn_rate', true)) {
            $widgets[] = ChurnRateWidget::class;
        }

        if (config('filament-cashier-chip.features.dashboard.widgets.attention_required', true)) {
            $widgets[] = AttentionRequiredWidget::class;
        }

        return $widgets;
    }

    public function getFooterWidgets(): array
    {
        if (! config('filament-cashier-chip.features.dashboard_widgets', true)) {
            return [];
        }

        $widgets = [];

        if (config('filament-cashier-chip.features.dashboard.widgets.revenue_chart', true)) {
            $widgets[] = RevenueChartWidget::class;
        }

        if (config('filament-cashier-chip.features.dashboard.widgets.subscription_distribution', true)) {
            $widgets[] = SubscriptionDistributionWidget::class;
        }

        if (config('filament-cashier-chip.features.dashboard.widgets.trial_conversions', true)) {
            $widgets[] = TrialConversionsWidget::class;
        }

        return $widgets;
    }

    public function getHeaderWidgetsColumns(): int | array
    {
        return 4;
    }

    public function getFooterWidgetsColumns(): int | array
    {
        return [
            'default' => 1,
            'md' => 2,
            'lg' => 3,
        ];
    }
}
