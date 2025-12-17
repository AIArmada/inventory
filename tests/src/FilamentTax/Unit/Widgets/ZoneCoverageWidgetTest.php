<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;

uses(TestCase::class);

use AIArmada\FilamentTax\Widgets\ZoneCoverageWidget;
use AIArmada\Tax\Models\TaxRate;
use AIArmada\Tax\Models\TaxZone;

it('formats active zones with their rates for display', function (): void {
    TaxRate::query()->delete();
    TaxZone::query()->delete();

    $zone = TaxZone::create([
        'name' => 'Malaysia',
        'code' => 'MY',
        'type' => 'country',
        'countries' => ['MY'],
        'priority' => 10,
        'is_default' => true,
        'is_active' => true,
    ]);

    TaxRate::create([
        'zone_id' => $zone->id,
        'tax_class' => 'standard',
        'name' => 'SST',
        'rate' => 600,
        'is_compound' => false,
        'is_active' => true,
    ]);

    $widget = app(ZoneCoverageWidget::class);

    $ref = new ReflectionClass($widget);
    $method = $ref->getMethod('getViewData');
    $method->setAccessible(true);

    $data = $method->invoke($widget);

    expect($data)
        ->toHaveKey('zones')
        ->and($data['zones'])->toHaveCount(1)
        ->and($data['zones'][0]['code'])->toBe('MY')
        ->and($data['zones'][0]['rate_count'])->toBe(1)
        ->and($data['zones'][0]['rates'][0]['rate'])->toBe('6.00%');
});
