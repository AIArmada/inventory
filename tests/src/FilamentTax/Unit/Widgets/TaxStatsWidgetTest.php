<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;

uses(TestCase::class);

use AIArmada\FilamentTax\Widgets\TaxStatsWidget;
use AIArmada\Tax\Models\TaxClass;
use AIArmada\Tax\Models\TaxExemption;
use AIArmada\Tax\Models\TaxRate;
use AIArmada\Tax\Models\TaxZone;

it('returns stats based on current tax data', function (): void {
    TaxExemption::query()->delete();
    TaxRate::query()->delete();
    TaxClass::query()->delete();
    TaxZone::query()->delete();

    $zoneActive = TaxZone::create([
        'name' => 'Malaysia',
        'code' => 'MY',
        'is_active' => true,
    ]);

    TaxZone::create([
        'name' => 'Inactive Zone',
        'code' => 'INACTIVE',
        'is_active' => false,
    ]);

    TaxRate::create([
        'zone_id' => $zoneActive->id,
        'tax_class' => 'standard',
        'name' => 'SST',
        'rate' => 600,
        'is_active' => true,
    ]);

    TaxRate::create([
        'zone_id' => $zoneActive->id,
        'tax_class' => 'standard',
        'name' => 'Inactive Rate',
        'rate' => 600,
        'is_active' => false,
    ]);

    $classActive = TaxClass::create([
        'name' => 'Standard',
        'slug' => 'standard',
        'is_active' => true,
    ]);

    TaxClass::create([
        'name' => 'Inactive Class',
        'slug' => 'inactive',
        'is_active' => false,
    ]);

    TaxExemption::create([
        'exemptable_type' => TaxClass::class,
        'exemptable_id' => $classActive->id,
        'reason' => 'Test',
        'status' => 'approved',
        'expires_at' => null,
    ]);

    TaxExemption::create([
        'exemptable_type' => TaxClass::class,
        'exemptable_id' => $classActive->id,
        'reason' => 'Expired',
        'status' => 'approved',
        'expires_at' => now()->subDay(),
    ]);

    $widget = app(TaxStatsWidget::class);

    $ref = new ReflectionClass($widget);
    $method = $ref->getMethod('getStats');
    $method->setAccessible(true);

    $stats = $method->invoke($widget);

    expect($stats)
        ->toHaveCount(4)
        ->and($stats[0]->getLabel())->toBe('Tax Zones')
        ->and($stats[0]->getValue())->toBe('1')
        ->and($stats[1]->getLabel())->toBe('Tax Rates')
        ->and($stats[1]->getValue())->toBe('1')
        ->and($stats[2]->getLabel())->toBe('Tax Classes')
        ->and($stats[2]->getValue())->toBe('1')
        ->and($stats[3]->getLabel())->toBe('Active Exemptions')
        ->and($stats[3]->getValue())->toBe('1');
});
