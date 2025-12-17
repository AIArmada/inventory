<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;

uses(TestCase::class);

use AIArmada\FilamentTax\Widgets\ExpiringExemptionsWidget;
use AIArmada\Tax\Models\TaxClass;
use AIArmada\Tax\Models\TaxExemption;

it('queries only exemptions expiring within 30 days', function (): void {
    TaxExemption::query()->delete();
    TaxClass::query()->delete();

    $class = TaxClass::create([
        'name' => 'Standard',
        'slug' => 'standard',
        'is_active' => true,
    ]);

    $expiringSoon = TaxExemption::create([
        'exemptable_type' => TaxClass::class,
        'exemptable_id' => $class->id,
        'reason' => 'Soon',
        'status' => 'approved',
        'expires_at' => now()->addDays(10),
    ]);

    TaxExemption::create([
        'exemptable_type' => TaxClass::class,
        'exemptable_id' => $class->id,
        'reason' => 'Later',
        'status' => 'approved',
        'expires_at' => now()->addDays(40),
    ]);

    TaxExemption::create([
        'exemptable_type' => TaxClass::class,
        'exemptable_id' => $class->id,
        'reason' => 'Pending',
        'status' => 'pending',
        'expires_at' => now()->addDays(10),
    ]);

    $widget = app(ExpiringExemptionsWidget::class);

    $ref = new ReflectionClass($widget);
    $method = $ref->getMethod('getTableQuery');
    $method->setAccessible(true);

    $query = $method->invoke($widget);

    $results = $query->get();

    expect($results)
        ->toHaveCount(1)
        ->and($results->first()?->id)->toBe($expiringSoon->id);
});
