<?php

declare(strict_types=1);

use AIArmada\FilamentAffiliates\FilamentAffiliates;
use AIArmada\FilamentAffiliates\FilamentAffiliatesPlugin;

it('make returns plugin instance', function (): void {
    $plugin = FilamentAffiliates::make();

    expect($plugin)->toBeInstanceOf(FilamentAffiliatesPlugin::class);
});

it('get returns plugin instance', function (): void {
    $plugin = FilamentAffiliates::get();

    expect($plugin)->toBeInstanceOf(FilamentAffiliatesPlugin::class);
});
