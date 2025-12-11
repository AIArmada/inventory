<?php

declare(strict_types=1);

use AIArmada\Tax\Models\TaxClass;
use AIArmada\Tax\Models\TaxExemption;
use AIArmada\Tax\Models\TaxRate;
use AIArmada\Tax\Models\TaxZone;
use Illuminate\Support\Carbon;

describe('TaxZone Model', function (): void {
    describe('TaxZone Creation', function (): void {
        it('can create a country-based tax zone', function (): void {
            $zone = TaxZone::create([
                'name' => 'Malaysia',
                'code' => 'MY-' . uniqid(),
                'type' => 'country',
                'countries' => ['MY'],
                'is_active' => true,
            ]);

            expect($zone)->toBeInstanceOf(TaxZone::class)
                ->and($zone->name)->toBe('Malaysia')
                ->and($zone->type)->toBe('country')
                ->and($zone->countries)->toContain('MY');
        });

        it('can create a state-based tax zone', function (): void {
            $zone = TaxZone::create([
                'name' => 'California',
                'code' => 'US-CA-' . uniqid(),
                'type' => 'state',
                'countries' => ['US'],
                'states' => ['CA'],
                'is_active' => true,
            ]);

            expect($zone->type)->toBe('state')
                ->and($zone->states)->toContain('CA');
        });

        it('can create a postcode-based tax zone', function (): void {
            $zone = TaxZone::create([
                'name' => 'Central KL',
                'code' => 'MY-KL-CTR-' . uniqid(),
                'type' => 'postcode',
                'countries' => ['MY'],
                'postcodes' => ['50000-50999'],
                'is_active' => true,
            ]);

            expect($zone->type)->toBe('postcode')
                ->and($zone->postcodes)->toContain('50000-50999');
        });
    });

    describe('TaxZone Scopes', function (): void {
        it('can filter active zones', function (): void {
            $prefix = uniqid();
            TaxZone::create([
                'name' => 'Active Zone',
                'code' => "ACT-{$prefix}",
                'type' => 'country',
                'countries' => ['MY'],
                'is_active' => true,
            ]);

            TaxZone::create([
                'name' => 'Inactive Zone',
                'code' => "INACT-{$prefix}",
                'type' => 'country',
                'countries' => ['US'],
                'is_active' => false,
            ]);

            $active = TaxZone::where('code', 'like', "%-{$prefix}")->active()->get();

            expect($active)->toHaveCount(1);
        });
    });
});

describe('TaxClass Model', function (): void {
    describe('TaxClass Creation', function (): void {
        it('can create a tax class', function (): void {
            $class = TaxClass::create([
                'name' => 'Standard Rate',
                'slug' => 'standard-' . uniqid(),
                'description' => 'Standard tax rate for most goods',
                'is_active' => true,
            ]);

            expect($class)->toBeInstanceOf(TaxClass::class)
                ->and($class->name)->toBe('Standard Rate');
        });

        it('can create a default tax class', function (): void {
            $class = TaxClass::create([
                'name' => 'Default',
                'slug' => 'default-' . uniqid(),
                'is_default' => true,
                'is_active' => true,
            ]);

            expect($class->is_default)->toBeTrue();
        });
    });

    describe('TaxClass Positioning', function (): void {
        it('can order tax classes by position', function (): void {
            $prefix = uniqid();
            TaxClass::create(['name' => 'Third', 'slug' => "third-{$prefix}", 'position' => 3, 'is_active' => true]);
            TaxClass::create(['name' => 'First', 'slug' => "first-{$prefix}", 'position' => 1, 'is_active' => true]);
            TaxClass::create(['name' => 'Second', 'slug' => "second-{$prefix}", 'position' => 2, 'is_active' => true]);

            $ordered = TaxClass::where('slug', 'like', "%-{$prefix}")->orderBy('position')->get();

            expect($ordered->first()->name)->toBe('First')
                ->and($ordered->last()->name)->toBe('Third');
        });
    });
});

describe('TaxRate Model', function (): void {
    describe('TaxRate Creation', function (): void {
        it('can create a tax rate', function (): void {
            $zone = TaxZone::create([
                'name' => 'Malaysia',
                'code' => 'MY-RATE-' . uniqid(),
                'type' => 'country',
                'countries' => ['MY'],
                'is_active' => true,
            ]);

            $rate = TaxRate::create([
                'zone_id' => $zone->id,
                'tax_class' => 'standard',
                'name' => 'SST',
                'rate' => 600,
                'is_active' => true,
            ]);

            expect($rate)->toBeInstanceOf(TaxRate::class)
                ->and($rate->name)->toBe('SST')
                ->and($rate->rate)->toBe(600);
        });

        it('can create compound tax rate', function (): void {
            $zone = TaxZone::create([
                'name' => 'Malaysia',
                'code' => 'MY-COMPOUND-' . uniqid(),
                'type' => 'country',
                'countries' => ['MY'],
                'is_active' => true,
            ]);

            $rate = TaxRate::create([
                'zone_id' => $zone->id,
                'tax_class' => 'standard',
                'name' => 'Compound Tax',
                'rate' => 500,
                'is_compound' => true,
                'is_active' => true,
            ]);

            expect($rate->is_compound)->toBeTrue();
        });
    });

    describe('TaxRate Priority', function (): void {
        it('can order rates by priority', function (): void {
            $zone = TaxZone::create([
                'name' => 'Zone',
                'code' => 'ZN-' . uniqid(),
                'type' => 'country',
                'countries' => ['MY'],
                'is_active' => true,
            ]);

            TaxRate::create(['zone_id' => $zone->id, 'tax_class' => 'standard', 'name' => 'Low', 'rate' => 100, 'priority' => 1, 'is_active' => true]);
            TaxRate::create(['zone_id' => $zone->id, 'tax_class' => 'standard', 'name' => 'High', 'rate' => 300, 'priority' => 10, 'is_active' => true]);
            TaxRate::create(['zone_id' => $zone->id, 'tax_class' => 'standard', 'name' => 'Medium', 'rate' => 200, 'priority' => 5, 'is_active' => true]);

            $ordered = TaxRate::where('zone_id', $zone->id)->orderBy('priority', 'desc')->get();

            expect($ordered->first()->name)->toBe('High')
                ->and($ordered->last()->name)->toBe('Low');
        });
    });

    describe('TaxRate Calculations', function (): void {
        it('can calculate tax percentage', function (): void {
            $zone = TaxZone::create([
                'name' => 'Zone',
                'code' => 'ZN-CALC-' . uniqid(),
                'type' => 'country',
                'countries' => ['MY'],
                'is_active' => true,
            ]);

            $rate = TaxRate::create([
                'zone_id' => $zone->id,
                'tax_class' => 'standard',
                'name' => 'SST',
                'rate' => 600, // 6%
                'is_active' => true,
            ]);

            expect($rate->getRatePercentage())->toBe(6.0);
        });

        it('can calculate tax for amount', function (): void {
            $zone = TaxZone::create([
                'name' => 'Zone',
                'code' => 'ZN-CALC2-' . uniqid(),
                'type' => 'country',
                'countries' => ['MY'],
                'is_active' => true,
            ]);

            $rate = TaxRate::create([
                'zone_id' => $zone->id,
                'tax_class' => 'standard',
                'name' => 'SST',
                'rate' => 600, // 6%
                'is_active' => true,
            ]);

            expect($rate->calculateTax(10000))->toBe(600); // 6% of RM100
        });
    });
});

describe('TaxExemption Model', function (): void {
    describe('TaxExemption Creation', function (): void {
        it('can create a tax exemption', function (): void {
            $exemption = TaxExemption::create([
                'exemptable_type' => 'AIArmada\Customers\Models\Customer',
                'exemptable_id' => 'customer-uuid-' . uniqid(),
                'certificate_number' => 'TAX-EXEMPT-001',
                'reason' => 'Non-profit organization',
                'status' => 'approved',
            ]);

            expect($exemption)->toBeInstanceOf(TaxExemption::class)
                ->and($exemption->certificate_number)->toBe('TAX-EXEMPT-001')
                ->and($exemption->status)->toBe('approved');
        });

        it('can set exemption expiration', function (): void {
            $exemption = TaxExemption::create([
                'exemptable_type' => 'Customer',
                'exemptable_id' => 'cust-' . uniqid(),
                'certificate_number' => 'CERT-001',
                'status' => 'approved',
                'expires_at' => Carbon::now()->addYear(),
            ]);

            expect($exemption->expires_at)->not->toBeNull()
                ->and($exemption->expires_at->isFuture())->toBeTrue();
        });
    });
});
