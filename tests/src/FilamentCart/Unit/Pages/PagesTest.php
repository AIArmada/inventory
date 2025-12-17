<?php

declare(strict_types=1);

use AIArmada\FilamentCart\Pages\AnalyticsPage;
use AIArmada\FilamentCart\Pages\CartDashboard;
use AIArmada\FilamentCart\Pages\LiveDashboardPage;
use AIArmada\FilamentCart\Pages\RecoverySettingsPage;
use Filament\Pages\Page;

describe('Pages Instantiation', function (): void {
    it('can instantiate AnalyticsPage', function (): void {
        $page = new AnalyticsPage();
        expect($page)->toBeInstanceOf(Page::class);
    });

    it('can instantiate CartDashboard', function (): void {
        $page = new CartDashboard();
        expect($page)->toBeInstanceOf(Page::class);
    });

    it('can instantiate LiveDashboardPage', function (): void {
        $page = new LiveDashboardPage();
        expect($page)->toBeInstanceOf(Page::class);
    });

    it('can instantiate RecoverySettingsPage', function (): void {
        $page = new RecoverySettingsPage();
        expect($page)->toBeInstanceOf(Page::class);
    });
});
