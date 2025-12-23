<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;

uses(TestCase::class);

use AIArmada\FilamentPricing\Widgets\PricingStatsWidget;
use AIArmada\Pricing\Models\PriceList;
use AIArmada\Pricing\Models\Promotion;

it('builds pricing stats with correct counts', function (): void {
    PriceList::query()->create([
        'name' => 'List A',
        'slug' => 'list-a',
        'currency' => 'MYR',
        'is_active' => true,
    ]);

    PriceList::query()->create([
        'name' => 'List Inactive',
        'slug' => 'list-inactive',
        'currency' => 'MYR',
        'is_active' => false,
    ]);

    $activePromotion = Promotion::query()->create([
        'name' => 'Promo A',
        'type' => 'percentage',
        'discount_value' => 10,
        'is_active' => true,
    ]);
    $activePromotion->forceFill(['usage_count' => 3])->save();

    $inactivePromotion = Promotion::query()->create([
        'name' => 'Promo Inactive',
        'type' => 'percentage',
        'discount_value' => 10,
        'is_active' => false,
    ]);
    $inactivePromotion->forceFill(['usage_count' => 5])->save();

    $widget = app(PricingStatsWidget::class);

    $reflection = new ReflectionClass($widget);
    $method = $reflection->getMethod('getStats');
    $method->setAccessible(true);

    /** @var array<int, Filament\Widgets\StatsOverviewWidget\Stat> $stats */
    $stats = $method->invoke($widget);

    expect($stats)->toHaveCount(3)
        ->and($stats[0]->getLabel())->toBe('Active Price Lists')
        ->and($stats[0]->getValue())->toBe('1')
        ->and($stats[1]->getLabel())->toBe('Active Promotions')
        ->and($stats[1]->getValue())->toBe('1')
        ->and($stats[2]->getLabel())->toBe('Promotion Uses')
        ->and($stats[2]->getValue())->toBe('8');
});
