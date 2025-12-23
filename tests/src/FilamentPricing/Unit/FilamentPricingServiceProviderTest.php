<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentPricing\FilamentPricingServiceProvider;

uses(TestCase::class);

it('boots and registers without errors', function (): void {
    $provider = new FilamentPricingServiceProvider(app());

    $provider->register();
    $provider->boot();

    expect(view()->exists('filament-pricing::pages.price-simulator'))->toBeTrue();
    expect(view()->exists('filament-pricing::pages.manage-pricing-settings'))->toBeTrue();
});
