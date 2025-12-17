<?php

declare(strict_types=1);

use AIArmada\FilamentPricing\FilamentPricingServiceProvider;
use AIArmada\Commerce\Tests\TestCase;

uses(TestCase::class);

it('boots and registers without errors', function (): void {
    $provider = new FilamentPricingServiceProvider(app());

    $provider->register();
    $provider->boot();

    expect(true)->toBeTrue();
});
