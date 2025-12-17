<?php

declare(strict_types=1);

use AIArmada\FilamentChip\BillingPanelProvider;
use Filament\Panel;

it('applies billing panel config values', function (): void {
    config()->set('filament-chip.billing.panel_id', 'billing');
    config()->set('filament-chip.billing.path', 'portal');
    config()->set('filament-chip.billing.brand_name', 'My Billing');
    config()->set('filament-chip.billing.login_enabled', false);
    config()->set('filament-chip.billing.auth_guard', 'web');

    $provider = new BillingPanelProvider(app());

    $panel = $provider->panel(Panel::make());

    expect($panel->getId())->toBe('billing');
    expect($panel->getPath())->toBe('portal');
    expect($panel->getBrandName())->toBe('My Billing');
    expect($panel->hasLogin())->toBeFalse();
    expect($panel->getAuthGuard())->toBe('web');
});

it('adds role middleware when allowed roles are configured', function (): void {
    config()->set('filament-chip.billing.allowed_roles', ['admin', 'customer']);

    $provider = new BillingPanelProvider(app());
    $panel = $provider->panel(Panel::make());

    expect($panel->getAuthMiddleware())->toContain('role:admin|customer');
});
