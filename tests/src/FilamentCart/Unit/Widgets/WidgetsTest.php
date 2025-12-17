<?php

declare(strict_types=1);

use AIArmada\FilamentCart\Widgets\AbandonedCartsWidget;
use AIArmada\FilamentCart\Widgets\AbandonmentAnalysisWidget;
use AIArmada\FilamentCart\Widgets\AnalyticsStatsWidget;
use AIArmada\FilamentCart\Widgets\CampaignPerformanceWidget;
use AIArmada\FilamentCart\Widgets\CartStatsOverviewWidget;
use AIArmada\FilamentCart\Widgets\CartStatsWidget;
use AIArmada\FilamentCart\Widgets\CollaborativeCartsWidget;
use AIArmada\FilamentCart\Widgets\ConversionFunnelWidget;
use AIArmada\FilamentCart\Widgets\FraudDetectionWidget;
use AIArmada\FilamentCart\Widgets\LiveStatsWidget;
use AIArmada\FilamentCart\Widgets\PendingAlertsWidget;
use AIArmada\FilamentCart\Widgets\RecentActivityWidget;
use AIArmada\FilamentCart\Widgets\RecoveryFunnelWidget;
use AIArmada\FilamentCart\Widgets\RecoveryOptimizerWidget;
use AIArmada\FilamentCart\Widgets\RecoveryPerformanceWidget;
use AIArmada\FilamentCart\Widgets\StrategyComparisonWidget;
use AIArmada\FilamentCart\Widgets\ValueTrendChartWidget;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\Widget;

describe('Widgets Instantiation', function (): void {
    it('can instantiate AbandonedCartsWidget', function (): void {
        $widget = new AbandonedCartsWidget();
        expect($widget)->toBeInstanceOf(Widget::class);
    });

    it('can instantiate AbandonmentAnalysisWidget', function (): void {
        $widget = new AbandonmentAnalysisWidget();
        expect($widget)->toBeInstanceOf(Widget::class);
    });

    it('can instantiate AnalyticsStatsWidget', function (): void {
        $widget = new AnalyticsStatsWidget();
        expect($widget)->toBeInstanceOf(StatsOverviewWidget::class);
    });

    it('can instantiate CampaignPerformanceWidget', function (): void {
        $widget = new CampaignPerformanceWidget();
        expect($widget)->toBeInstanceOf(Widget::class);
    });

    it('can instantiate CartStatsOverviewWidget', function (): void {
        $widget = new CartStatsOverviewWidget();
        expect($widget)->toBeInstanceOf(StatsOverviewWidget::class);
    });

    it('can instantiate CartStatsWidget', function (): void {
        $widget = new CartStatsWidget();
        expect($widget)->toBeInstanceOf(StatsOverviewWidget::class);
    });

    it('can instantiate CollaborativeCartsWidget', function (): void {
        $widget = new CollaborativeCartsWidget();
        expect($widget)->toBeInstanceOf(Widget::class);
    });

    it('can instantiate ConversionFunnelWidget', function (): void {
        $widget = new ConversionFunnelWidget();
        expect($widget)->toBeInstanceOf(Widget::class);
    });

    it('can instantiate FraudDetectionWidget', function (): void {
        $widget = new FraudDetectionWidget();
        expect($widget)->toBeInstanceOf(Widget::class);
    });

    it('can instantiate LiveStatsWidget', function (): void {
        $widget = new LiveStatsWidget();
        expect($widget)->toBeInstanceOf(StatsOverviewWidget::class);
    });

    it('can instantiate PendingAlertsWidget', function (): void {
        $widget = new PendingAlertsWidget();
        expect($widget)->toBeInstanceOf(Widget::class);
    });

    it('can instantiate RecentActivityWidget', function (): void {
        $widget = new RecentActivityWidget();
        expect($widget)->toBeInstanceOf(Widget::class);
    });

    it('can instantiate RecoveryFunnelWidget', function (): void {
        $widget = new RecoveryFunnelWidget();
        expect($widget)->toBeInstanceOf(Widget::class);
    });

    it('can instantiate RecoveryOptimizerWidget', function (): void {
        $widget = new RecoveryOptimizerWidget();
        expect($widget)->toBeInstanceOf(Widget::class);
    });

    it('can instantiate RecoveryPerformanceWidget', function (): void {
        $widget = new RecoveryPerformanceWidget();
        expect($widget)->toBeInstanceOf(Widget::class);
    });

    it('can instantiate StrategyComparisonWidget', function (): void {
        $widget = new StrategyComparisonWidget();
        expect($widget)->toBeInstanceOf(Widget::class);
    });

    it('can instantiate ValueTrendChartWidget', function (): void {
        $widget = new ValueTrendChartWidget();
        expect($widget)->toBeInstanceOf(Widget::class);
    });
});
