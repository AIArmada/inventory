<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Pages;

use AIArmada\FilamentCart\Widgets\AbandonedCartsWidget;
use AIArmada\FilamentCart\Widgets\CartStatsOverviewWidget;
use AIArmada\FilamentCart\Widgets\CollaborativeCartsWidget;
use AIArmada\FilamentCart\Widgets\FraudDetectionWidget;
use AIArmada\FilamentCart\Widgets\RecoveryOptimizerWidget;
use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

/**
 * Cart analytics dashboard page.
 *
 * Provides an overview of cart activity, abandonment rates,
 * fraud detection, AI recovery, and collaborative carts.
 */
class CartDashboard extends Page
{
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-chart-bar';

    protected static string | UnitEnum | null $navigationGroup = 'Commerce';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament-cart::pages.cart-dashboard';

    protected static ?string $title = 'Cart Analytics';

    protected static ?string $slug = 'cart-dashboard';

    public static function getNavigationLabel(): string
    {
        return 'Cart Analytics';
    }

    public static function getNavigationBadge(): ?string
    {
        $count = self::getAbandonedCartCount() + self::getFraudAlertCount();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $fraudCount = self::getFraudAlertCount();
        $abandonedCount = self::getAbandonedCartCount();

        if ($fraudCount > 0) {
            return 'danger';
        }

        if ($abandonedCount >= 10) {
            return 'warning';
        }

        if ($abandonedCount >= 5) {
            return 'info';
        }

        return 'success';
    }

    protected function getHeaderWidgets(): array
    {
        $widgets = [];

        if (config('filament-cart.widgets.stats_overview', true)) {
            $widgets[] = CartStatsOverviewWidget::class;
        }

        return $widgets;
    }

    protected function getFooterWidgets(): array
    {
        $widgets = [];

        if (config('filament-cart.widgets.fraud_detection', true) && config('filament-cart.features.fraud_detection', true)) {
            $widgets[] = FraudDetectionWidget::class;
        }

        if (config('filament-cart.widgets.recovery_optimizer', true) && config('filament-cart.features.ai_recovery', true)) {
            $widgets[] = RecoveryOptimizerWidget::class;
        }

        if (config('filament-cart.widgets.abandoned_carts', true) && config('filament-cart.features.abandonment_tracking', true)) {
            $widgets[] = AbandonedCartsWidget::class;
        }

        if (config('filament-cart.widgets.collaborative_carts', true) && config('filament-cart.features.collaborative_carts', true)) {
            $widgets[] = CollaborativeCartsWidget::class;
        }

        return $widgets;
    }

    private static function getAbandonedCartCount(): int
    {
        if (! class_exists(\AIArmada\FilamentCart\Models\Cart::class)) {
            return 0;
        }

        return \AIArmada\FilamentCart\Models\Cart::query()
            ->whereNotNull('checkout_abandoned_at')
            ->whereNull('recovered_at')
            ->where('checkout_abandoned_at', '>=', now()->subDay())
            ->count();
    }

    private static function getFraudAlertCount(): int
    {
        if (! class_exists(\AIArmada\FilamentCart\Models\Cart::class)) {
            return 0;
        }

        return \AIArmada\FilamentCart\Models\Cart::query()
            ->whereIn('fraud_risk_level', ['high', 'medium'])
            ->where('updated_at', '>=', now()->subDay())
            ->count();
    }
}
