<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use AIArmada\FilamentAffiliates\Widgets\AffiliateStatsWidget;
use AIArmada\FilamentCart\Widgets\AbandonedCartsWidget;
use AIArmada\FilamentCart\Widgets\CartStatsOverviewWidget;
use AIArmada\FilamentChip\Widgets\ChipStatsWidget;
use AIArmada\FilamentChip\Widgets\RecentTransactionsWidget;
use AIArmada\FilamentInventory\Widgets\InventoryStatsWidget;
use AIArmada\FilamentInventory\Widgets\LowInventoryAlertsWidget;
use AIArmada\FilamentJnt\Widgets\JntStatsWidget;
use AIArmada\FilamentVouchers\Widgets\VoucherStatsWidget;
use App\Filament\Widgets\LatestOrders;
use App\Filament\Widgets\RevenueChart;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\TopAffiliates;
use BackedEnum;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;

/**
 * 🎭 COMMERCE DEMO DASHBOARD
 *
 * The ultimate command center showcasing ALL AIArmada Commerce packages:
 * - Cart & Checkout
 * - Vouchers & Gift Cards
 * - Inventory (multi-location)
 * - Affiliates & Commissions
 * - CHIP Payments
 * - J&T Shipping
 * - Cashier Subscriptions
 */
final class Dashboard extends BaseDashboard
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::Home;

    protected static ?int $navigationSort = -2;

    protected int | string | array $columns = [
        'default' => 1,
        'sm' => 2,
        'md' => 3,
        'lg' => 4,
        'xl' => 6,
        '2xl' => 8,
    ];

    public function getTitle(): string
    {
        return '🎭 Commerce Command Center';
    }

    public function getHeading(): string
    {
        return '🎭 Commerce Command Center';
    }

    public function getSubheading(): ?string
    {
        return 'Real-time overview of your entire commerce ecosystem — Cart • Vouchers • Inventory • Affiliates • Payments • Shipping';
    }

    public function getWidgets(): array
    {
        return [
            // ============================================
            // ROW 1: CORE BUSINESS METRICS
            // ============================================
            StatsOverview::class,

            // ============================================
            // ROW 2: REVENUE & ORDERS
            // ============================================
            RevenueChart::class,
            LatestOrders::class,

            // ============================================
            // ROW 3: INVENTORY & STOCK ALERTS
            // ============================================
            InventoryStatsWidget::class,
            LowInventoryAlertsWidget::class,

            // ============================================
            // ROW 4: VOUCHERS & MARKETING
            // ============================================
            VoucherStatsWidget::class,

            // ============================================
            // ROW 5: AFFILIATES & PARTNERS
            // ============================================
            AffiliateStatsWidget::class,
            TopAffiliates::class,

            // ============================================
            // ROW 6: PAYMENTS (CHIP)
            // ============================================
            ChipStatsWidget::class,
            RecentTransactionsWidget::class,

            // ============================================
            // ROW 7: SHIPPING (J&T EXPRESS)
            // ============================================
            JntStatsWidget::class,

            // ============================================
            // ROW 8: CART RECOVERY
            // ============================================
            CartStatsOverviewWidget::class,
            AbandonedCartsWidget::class,
        ];
    }
}
