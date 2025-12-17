<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;

uses(TestCase::class);

use AIArmada\FilamentPricing\Pages\PriceSimulator;
use Filament\Schemas\Schema;

it('returns an empty result infolist when no result exists', function (): void {
    $page = app(PriceSimulator::class);
    $page->result = null;

    $schema = $page->resultInfolist(Schema::make());

    expect($schema->getComponents())->toBeEmpty();
});

it('builds a result infolist when result exists', function (): void {
    $page = app(PriceSimulator::class);

    $page->result = [
        'original_price' => 1000,
        'final_price' => 900,
        'discount_amount' => 100,
        'discount_source' => 'promotion',
        'discount_percentage' => 10,
        'price_list_name' => 'Default',
        'tier_description' => null,
        'promotion_name' => 'Promo',
        'breakdown' => [
            ['step' => 'Base', 'value' => 1000],
            ['step' => 'Discount', 'value' => 100],
        ],
        'quantity' => 1,
        'unit_price' => 900,
        'total_price' => 900,
    ];

    $schema = $page->resultInfolist(Schema::make());

    expect($schema->getComponents())->not()->toBeEmpty();
});
