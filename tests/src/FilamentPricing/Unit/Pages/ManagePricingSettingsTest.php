<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;

uses(TestCase::class);

use AIArmada\FilamentPricing\Pages\ManagePricingSettings;

it('exposes pricing settings form components for testing', function (): void {
    $components = ManagePricingSettings::getFormComponents();

    expect($components)->not()->toBeEmpty();
});
