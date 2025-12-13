<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Pages;

use AIArmada\FilamentCart\Widgets\LiveStatsWidget;
use AIArmada\FilamentCart\Widgets\PendingAlertsWidget;
use AIArmada\FilamentCart\Widgets\RecentActivityWidget;
use BackedEnum;
use Filament\Pages\Page;

class LiveDashboardPage extends Page
{
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-signal';

    protected static ?string $navigationLabel = 'Live Monitor';

    protected static ?string $title = 'Live Cart Monitor';

    protected static ?string $slug = 'cart-live';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament-cart::pages.live-dashboard';

    public static function getNavigationGroup(): ?string
    {
        return config('filament-cart.navigation_group', 'E-Commerce');
    }

    public static function canAccess(): bool
    {
        return config('filament-cart.features.monitoring', true);
    }

    public function getHeaderWidgetsColumns(): int | array
    {
        return 1;
    }

    public function getFooterWidgetsColumns(): int | array
    {
        return 2;
    }

    protected function getHeaderWidgets(): array
    {
        return [
            LiveStatsWidget::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            RecentActivityWidget::class,
            PendingAlertsWidget::class,
        ];
    }
}
