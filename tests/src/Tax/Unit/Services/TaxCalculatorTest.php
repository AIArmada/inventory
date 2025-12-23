<?php

declare(strict_types=1);

namespace AIArmada\Tax\Tests\Unit\Services;

use AIArmada\Commerce\Tests\Tax\TaxTestCase;
use AIArmada\Tax\Data\TaxResultData;
use AIArmada\Tax\Exceptions\TaxZoneNotFoundException;
use AIArmada\Tax\Models\TaxExemption;
use AIArmada\Tax\Models\TaxRate;
use AIArmada\Tax\Models\TaxZone;
use AIArmada\Tax\Services\TaxCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TaxCalculatorTest extends TaxTestCase
{
    use RefreshDatabase;

    private TaxCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new TaxCalculator;
    }

    public function test_calculate_tax_with_explicit_zone(): void
    {
        $zone = TaxZone::create([
            'name' => 'Malaysia',
            'code' => 'MY',
            'is_active' => true,
        ]);

        TaxRate::create([
            'zone_id' => $zone->id,
            'name' => 'Standard Rate',
            'rate' => 600, // 6%
            'tax_class' => 'standard',
            'is_active' => true,
        ]);

        $result = $this->calculator->calculateTax(10000, 'standard', $zone->id);

        $this->assertInstanceOf(TaxResultData::class, $result);
        $this->assertEquals(600, $result->taxAmount); // 6% of 10000 cents = 600 cents
        $this->assertEquals($zone->id, $result->zone->id);
        $this->assertFalse($result->includedInPrice);
    }

    public function test_calculate_tax_with_address_resolution(): void
    {
        $zone = TaxZone::create([
            'name' => 'Malaysia',
            'code' => 'MY',
            'countries' => ['MY'],
            'is_active' => true,
        ]);

        TaxRate::create([
            'zone_id' => $zone->id,
            'name' => 'Standard Rate',
            'rate' => 600,
            'tax_class' => 'standard',
            'is_active' => true,
        ]);

        $context = [
            'shipping_address' => [
                'country' => 'MY',
                'state' => 'Selangor',
                'postcode' => '43000',
            ],
        ];

        $result = $this->calculator->calculateTax(10000, 'standard', null, $context);

        $this->assertEquals(600, $result->taxAmount);
        $this->assertEquals($zone->id, $result->zone->id);
    }

    public function test_calculate_tax_with_default_zone_fallback(): void
    {
        $zone = TaxZone::create([
            'name' => 'Default Zone',
            'code' => 'DEFAULT',
            'is_default' => true,
            'is_active' => true,
        ]);

        TaxRate::create([
            'zone_id' => $zone->id,
            'name' => 'Default Rate',
            'rate' => 1000, // 10%
            'tax_class' => 'standard',
            'is_active' => true,
        ]);

        // No zone specified and no address
        $result = $this->calculator->calculateTax(20000, 'standard');

        $this->assertEquals(2000, $result->taxAmount); // 10% of 20000
        $this->assertEquals($zone->id, $result->zone->id);
    }

    public function test_calculate_tax_with_zero_rate_fallback(): void
    {
        // No zones or rates configured
        $result = $this->calculator->calculateTax(10000, 'standard');

        $this->assertEquals(0, $result->taxAmount);
        $this->assertEquals('ZERO', $result->zone->code);
    }

    public function test_calculate_tax_with_tax_inclusive_pricing(): void
    {
        config(['tax.defaults.prices_include_tax' => true]);

        $zone = TaxZone::create([
            'name' => 'Inclusive Zone',
            'code' => 'INCL',
            'is_active' => true,
        ]);

        TaxRate::create([
            'zone_id' => $zone->id,
            'name' => 'Inclusive Rate',
            'rate' => 1000, // 10%
            'tax_class' => 'standard',
            'is_active' => true,
        ]);

        $result = $this->calculator->calculateTax(11000, 'standard', $zone->id);

        $this->assertEquals(1000, $result->taxAmount); // Extract 10% from 11000
        $this->assertTrue($result->includedInPrice);
    }

    public function test_calculate_tax_with_rounding(): void
    {
        config(['tax.defaults.round_at_subtotal' => true]);

        $zone = TaxZone::create([
            'name' => 'Rounding Zone',
            'code' => 'ROUND',
            'is_active' => true,
        ]);

        TaxRate::create([
            'zone_id' => $zone->id,
            'name' => 'Rounding Rate',
            'rate' => 875, // 8.75%
            'tax_class' => 'standard',
            'is_active' => true,
        ]);

        $result = $this->calculator->calculateTax(10000, 'standard', $zone->id);

        // 10000 * 0.0875 = 875, rounded to 875
        $this->assertEquals(875, $result->taxAmount);
    }

    public function test_calculate_tax_with_exemption(): void
    {
        $zone = TaxZone::create([
            'name' => 'Exempt Zone',
            'code' => 'EXEMPT',
            'is_active' => true,
        ]);

        TaxRate::create([
            'zone_id' => $zone->id,
            'name' => 'Normal Rate',
            'rate' => 600,
            'tax_class' => 'standard',
            'is_active' => true,
        ]);

        $customerId = '550e8400-e29b-41d4-a716-446655440123';

        TaxExemption::create([
            'exemptable_id' => $customerId,
            'exemptable_type' => 'App\\Models\\Customer',
            'reason' => 'Non-profit',
            'status' => 'approved',
        ]);

        $context = ['customer_id' => $customerId];

        // Debug: check what exemptions exist
        $exemptions = TaxExemption::all();
        $this->assertCount(1, $exemptions);
        $exemption = $exemptions->first();
        $this->assertNull($exemption->tax_zone_id, 'tax_zone_id should be null');

        // Debug: check if the query finds the exemption
        $found = TaxExemption::query()
            ->where('exemptable_id', $customerId)
            ->where('status', 'approved')
            ->first();
        $this->assertNotNull($found, 'Exemption should be found by basic query');

        // Test forZone separately
        $foundWithZone = TaxExemption::query()
            ->where('exemptable_id', $customerId)
            ->where('status', 'approved')
            ->forZone($zone->id)
            ->first();
        $this->assertNotNull($foundWithZone, 'Exemption should be found by forZone query');

        $result = $this->calculator->calculateTax(10000, 'standard', $zone->id, $context);

        $this->assertEquals(0, $result->taxAmount);
        $this->assertEquals('Non-profit', $result->exemptionReason);
        $this->assertTrue($result->isExempt());
    }

    public function test_calculate_tax_with_zone_specific_exemption(): void
    {
        $zone1 = TaxZone::create(['name' => 'Zone 1', 'code' => 'Z1', 'is_active' => true]);
        $zone2 = TaxZone::create(['name' => 'Zone 2', 'code' => 'Z2', 'is_active' => true]);

        TaxRate::create([
            'zone_id' => $zone1->id,
            'name' => 'Rate 1',
            'rate' => 600,
            'tax_class' => 'standard',
            'is_active' => true,
        ]);

        TaxRate::create([
            'zone_id' => $zone2->id,
            'name' => 'Rate 2',
            'rate' => 800,
            'tax_class' => 'standard',
            'is_active' => true,
        ]);

        // Exemption only for zone 1
        TaxExemption::create([
            'exemptable_id' => 'customer-123',
            'exemptable_type' => 'App\\Models\\Customer',
            'tax_zone_id' => $zone1->id,
            'reason' => 'Zone specific exemption',
            'status' => 'approved',
        ]);

        $context = ['customer_id' => 'customer-123'];

        // Should be exempt in zone 1
        $result1 = $this->calculator->calculateTax(10000, 'standard', $zone1->id, $context);
        $this->assertEquals(0, $result1->taxAmount);

        // Should NOT be exempt in zone 2
        $result2 = $this->calculator->calculateTax(10000, 'standard', $zone2->id, $context);
        $this->assertEquals(800, $result2->taxAmount);
    }

    public function test_calculate_shipping_tax_enabled(): void
    {
        config(['tax.defaults.calculate_tax_on_shipping' => true]);

        $zone = TaxZone::create([
            'name' => 'Shipping Zone',
            'code' => 'SHIP',
            'is_active' => true,
        ]);

        TaxRate::create([
            'zone_id' => $zone->id,
            'name' => 'Shipping Rate',
            'rate' => 600,
            'tax_class' => 'standard',
            'is_active' => true,
        ]);

        $result = $this->calculator->calculateShippingTax(5000, $zone->id);

        $this->assertEquals(300, $result->taxAmount); // 6% of 5000
    }

    public function test_calculate_shipping_tax_disabled(): void
    {
        config(['tax.defaults.calculate_tax_on_shipping' => false]);

        $result = $this->calculator->calculateShippingTax(5000);

        $this->assertEquals(0, $result->taxAmount);
    }

    public function test_calculate_tax_with_different_tax_classes(): void
    {
        $zone = TaxZone::create([
            'name' => 'Class Zone',
            'code' => 'CLASS',
            'is_active' => true,
        ]);

        TaxRate::create([
            'zone_id' => $zone->id,
            'name' => 'Standard Rate',
            'rate' => 600,
            'tax_class' => 'standard',
            'is_active' => true,
        ]);

        TaxRate::create([
            'zone_id' => $zone->id,
            'name' => 'Reduced Rate',
            'rate' => 300,
            'tax_class' => 'reduced',
            'priority' => 10, // Higher priority
            'is_active' => true,
        ]);

        $standardResult = $this->calculator->calculateTax(10000, 'standard', $zone->id);
        $reducedResult = $this->calculator->calculateTax(10000, 'reduced', $zone->id);

        $this->assertEquals(600, $standardResult->taxAmount);
        $this->assertEquals(300, $reducedResult->taxAmount);
    }

    public function test_calculate_tax_with_rate_priority(): void
    {
        $zone = TaxZone::create([
            'name' => 'Priority Zone',
            'code' => 'PRIO',
            'is_active' => true,
        ]);

        // Lower priority rate
        TaxRate::create([
            'zone_id' => $zone->id,
            'name' => 'Low Priority',
            'rate' => 600,
            'tax_class' => 'standard',
            'priority' => 1,
            'is_active' => true,
        ]);

        // Higher priority rate
        TaxRate::create([
            'zone_id' => $zone->id,
            'name' => 'High Priority',
            'rate' => 800,
            'tax_class' => 'standard',
            'priority' => 10,
            'is_active' => true,
        ]);

        $result = $this->calculator->calculateTax(10000, 'standard', $zone->id);

        // Should use higher priority rate
        $this->assertEquals(800, $result->taxAmount);
    }

    public function test_calculate_tax_with_unknown_zone_error_behavior(): void
    {
        config(['tax.features.zone_resolution.unknown_zone_behavior' => 'error']);

        $this->expectException(TaxZoneNotFoundException::class);

        // No zones configured, should throw error
        $this->calculator->calculateTax(10000, 'standard');
    }

    public function test_tax_disabled_does_not_throw_when_unknown_zone_behavior_is_error(): void
    {
        config(['tax.features.enabled' => false]);
        config(['tax.features.zone_resolution.unknown_zone_behavior' => 'error']);

        $result = $this->calculator->calculateTax(10000, 'standard');

        $this->assertEquals(0, $result->taxAmount);
        $this->assertEquals('ZERO', $result->zone->code);
    }

    public function test_calculate_tax_with_unknown_zone_zero_behavior(): void
    {
        config(['tax.features.zone_resolution.unknown_zone_behavior' => 'zero']);

        $result = $this->calculator->calculateTax(10000, 'standard');

        $this->assertEquals(0, $result->taxAmount);
        $this->assertEquals('ZERO', $result->zone->code);
    }

    public function test_calculate_tax_with_address_priority(): void
    {
        config(['tax.features.zone_resolution.address_priority' => 'billing']);

        $zone = TaxZone::create([
            'name' => 'Billing Priority',
            'code' => 'BILL',
            'countries' => ['US'],
            'is_active' => true,
        ]);

        TaxRate::create([
            'zone_id' => $zone->id,
            'name' => 'Billing Rate',
            'rate' => 700,
            'tax_class' => 'standard',
            'is_active' => true,
        ]);

        $context = [
            'shipping_address' => ['country' => 'MY'],
            'billing_address' => ['country' => 'US'],
        ];

        $result = $this->calculator->calculateTax(10000, 'standard', null, $context);

        // Should use billing address (US) over shipping (MY)
        $this->assertEquals(700, $result->taxAmount);
    }

    public function test_calculate_tax_with_disabled_exemptions(): void
    {
        config(['tax.features.exemptions.enabled' => false]);

        $zone = TaxZone::create([
            'name' => 'Disabled Exemptions',
            'code' => 'DISABLED',
            'is_active' => true,
        ]);

        TaxRate::create([
            'zone_id' => $zone->id,
            'name' => 'Normal Rate',
            'rate' => 600,
            'tax_class' => 'standard',
            'is_active' => true,
        ]);

        TaxExemption::create([
            'exemptable_id' => 'customer-123',
            'exemptable_type' => 'App\\Models\\Customer',
            'reason' => 'Should be ignored',
            'status' => 'approved',
        ]);

        $context = ['customer_id' => 'customer-123'];
        $result = $this->calculator->calculateTax(10000, 'standard', $zone->id, $context);

        // Exemption should be ignored, tax should be calculated
        $this->assertEquals(600, $result->taxAmount);
        $this->assertNull($result->exemptionReason);
    }
}
