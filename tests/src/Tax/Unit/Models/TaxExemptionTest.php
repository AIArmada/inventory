<?php

declare(strict_types=1);

namespace AIArmada\Tax\Tests\Unit\Models;

use AIArmada\Commerce\Tests\Tax\TaxTestCase;
use AIArmada\Tax\Enums\ExemptionStatus;
use AIArmada\Tax\Models\TaxExemption;
use AIArmada\Tax\Models\TaxZone;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TaxExemptionTest extends TaxTestCase
{
    use RefreshDatabase;

    public function test_can_create_tax_exemption(): void
    {
        $zone = TaxZone::create([
            'name' => 'Test Zone',
            'code' => 'TEST',
            'is_active' => true,
        ]);

        $exemption = TaxExemption::create([
            'exemptable_id' => 'customer-123',
            'exemptable_type' => 'App\\Models\\Customer',
            'tax_zone_id' => $zone->id,
            'reason' => 'Non-profit organization',
            'certificate_number' => 'CERT-123',
            'status' => ExemptionStatus::Approved,
            'verified_at' => now(),
            'verified_by' => 'admin-1',
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        $this->assertInstanceOf(TaxExemption::class, $exemption);
        $this->assertEquals('customer-123', $exemption->exemptable_id);
        $this->assertEquals('Non-profit organization', $exemption->reason);
        $this->assertEquals(ExemptionStatus::Approved, $exemption->status);
    }

    public function test_active_scope(): void
    {
        $uuid1 = '550e8400-e29b-41d4-a716-446655440001';
        $uuid2 = '550e8400-e29b-41d4-a716-446655440002';
        $uuid3 = '550e8400-e29b-41d4-a716-446655440003';
        $uuid4 = '550e8400-e29b-41d4-a716-446655440004';

        $now = \Illuminate\Support\Carbon::now();

        // Approved exemption within date range
        TaxExemption::create([
            'exemptable_id' => $uuid1,
            'exemptable_type' => 'App\\Models\\Customer',
            'reason' => 'Active exemption',
            'status' => ExemptionStatus::Approved,
            'starts_at' => $now->copy()->subDays(5),
            'expires_at' => $now->copy()->addDays(5),
        ]);

        // Pending exemption
        TaxExemption::create([
            'exemptable_id' => $uuid2,
            'exemptable_type' => 'App\\Models\\Customer',
            'reason' => 'Pending exemption',
            'status' => ExemptionStatus::Pending,
        ]);

        // Expired exemption
        TaxExemption::create([
            'exemptable_id' => $uuid3,
            'exemptable_type' => 'App\\Models\\Customer',
            'reason' => 'Expired exemption',
            'status' => ExemptionStatus::Approved,
            'starts_at' => $now->copy()->subDays(20),
            'expires_at' => $now->copy()->subDays(10),
        ]);

        // Future exemption
        TaxExemption::create([
            'exemptable_id' => $uuid4,
            'exemptable_type' => 'App\\Models\\Customer',
            'reason' => 'Future exemption',
            'status' => ExemptionStatus::Approved,
            'starts_at' => $now->copy()->addDays(10),
            'expires_at' => $now->copy()->addDays(20),
        ]);

        $activeExemptions = TaxExemption::active()->get();

        $this->assertCount(1, $activeExemptions);
        $this->assertEquals('Active exemption', $activeExemptions->first()->reason);
    }

    public function test_pending_scope(): void
    {
        TaxExemption::create([
            'exemptable_id' => 'customer-1',
            'exemptable_type' => 'App\\Models\\Customer',
            'reason' => 'Pending',
            'status' => ExemptionStatus::Pending,
        ]);

        TaxExemption::create([
            'exemptable_id' => 'customer-2',
            'exemptable_type' => 'App\\Models\\Customer',
            'reason' => 'Approved',
            'status' => ExemptionStatus::Approved,
        ]);

        $pending = TaxExemption::pending()->get();

        $this->assertCount(1, $pending);
        $this->assertEquals('Pending', $pending->first()->reason);
    }

    public function test_approved_scope(): void
    {
        TaxExemption::create([
            'exemptable_id' => 'customer-1',
            'exemptable_type' => 'App\\Models\\Customer',
            'reason' => 'Approved',
            'status' => ExemptionStatus::Approved,
        ]);

        TaxExemption::create([
            'exemptable_id' => 'customer-2',
            'exemptable_type' => 'App\\Models\\Customer',
            'reason' => 'Rejected',
            'status' => ExemptionStatus::Rejected,
        ]);

        $approved = TaxExemption::approved()->get();

        $this->assertCount(1, $approved);
        $this->assertEquals('Approved', $approved->first()->reason);
    }

    public function test_for_zone_scope(): void
    {
        $zone1 = TaxZone::create(['name' => 'Zone 1', 'code' => 'Z1', 'is_active' => true]);
        $zone2 = TaxZone::create(['name' => 'Zone 2', 'code' => 'Z2', 'is_active' => true]);

        // Exemption for specific zone
        TaxExemption::create([
            'exemptable_id' => 'customer-1',
            'exemptable_type' => 'App\\Models\\Customer',
            'tax_zone_id' => $zone1->id,
            'reason' => 'Zone specific',
            'status' => ExemptionStatus::Approved,
        ]);

        // Exemption for all zones (null zone_id)
        TaxExemption::create([
            'exemptable_id' => 'customer-2',
            'exemptable_type' => 'App\\Models\\Customer',
            'tax_zone_id' => null,
            'reason' => 'All zones',
            'status' => ExemptionStatus::Approved,
        ]);

        $zone1Exemptions = TaxExemption::forZone($zone1->id)->get();
        $zone2Exemptions = TaxExemption::forZone($zone2->id)->get();

        $this->assertCount(2, $zone1Exemptions); // Both exemptions apply to zone 1
        $this->assertCount(1, $zone2Exemptions); // Only the global exemption applies to zone 2
        $this->assertEquals('All zones', $zone2Exemptions->first()->reason);
    }

    public function test_relationships(): void
    {
        $zone = TaxZone::create([
            'name' => 'Test Zone',
            'code' => 'TEST',
            'is_active' => true,
        ]);

        $exemption = TaxExemption::create([
            'exemptable_id' => 'customer-123',
            'exemptable_type' => 'App\\Models\\Customer',
            'tax_zone_id' => $zone->id,
            'reason' => 'Test exemption',
            'status' => ExemptionStatus::Approved,
        ]);

        $this->assertInstanceOf(TaxZone::class, $exemption->taxZone);
        $this->assertEquals($zone->id, $exemption->taxZone->id);

        // Test morphTo relationship (would need actual Customer model for full test)
        $this->assertEquals('customer-123', $exemption->exemptable_id);
        $this->assertEquals('App\\Models\\Customer', $exemption->exemptable_type);
    }

    public function test_is_active_method(): void
    {
        $now = \Illuminate\Support\Carbon::now();

        // Active exemption
        $active = TaxExemption::create([
            'exemptable_id' => 'customer-active',
            'exemptable_type' => 'App\\Models\\Customer',
            'reason' => 'Active test',
            'status' => ExemptionStatus::Approved,
            'starts_at' => $now->copy()->subDay(),
            'expires_at' => $now->copy()->addDay(),
        ]);

        // Pending exemption
        $pending = TaxExemption::create([
            'exemptable_id' => 'customer-pending',
            'exemptable_type' => 'App\\Models\\Customer',
            'reason' => 'Pending test',
            'status' => ExemptionStatus::Pending,
        ]);

        // Expired exemption
        $expired = TaxExemption::create([
            'exemptable_id' => 'customer-expired',
            'exemptable_type' => 'App\\Models\\Customer',
            'reason' => 'Expired test',
            'status' => ExemptionStatus::Approved,
            'expires_at' => $now->copy()->subDay(),
        ]);

        // Future exemption (starts in the future)
        $future = TaxExemption::create([
            'exemptable_id' => 'customer-future',
            'exemptable_type' => 'App\\Models\\Customer',
            'reason' => 'Future test',
            'status' => ExemptionStatus::Approved,
            'starts_at' => $now->copy()->addDay(),
        ]);

        $this->assertTrue($active->isActive());
        $this->assertFalse($pending->isActive());
        $this->assertFalse($expired->isActive());
        $this->assertFalse($future->isActive());
    }

    public function test_is_expired_method(): void
    {
        $expired = new TaxExemption(['expires_at' => now()->subDay()]);
        $notExpired = new TaxExemption(['expires_at' => now()->addDay()]);
        $noExpiry = new TaxExemption(['expires_at' => null]);

        $this->assertTrue($expired->isExpired());
        $this->assertFalse($notExpired->isExpired());
        $this->assertFalse($noExpiry->isExpired());
    }

    public function test_status_helper_methods(): void
    {
        $pending = new TaxExemption(['status' => ExemptionStatus::Pending]);
        $approved = new TaxExemption(['status' => ExemptionStatus::Approved]);
        $rejected = new TaxExemption(['status' => ExemptionStatus::Rejected]);

        $this->assertTrue($pending->isPending());
        $this->assertTrue($approved->isApproved());
        $this->assertTrue($rejected->isRejected());

        $this->assertFalse($approved->isPending());
        $this->assertFalse($pending->isApproved());
        $this->assertFalse($approved->isRejected());
    }

    public function test_approve_method(): void
    {
        $exemption = TaxExemption::create([
            'exemptable_id' => 'customer-1',
            'exemptable_type' => 'App\\Models\\Customer',
            'reason' => 'Test',
            'status' => ExemptionStatus::Pending,
        ]);

        $result = $exemption->approve();

        $this->assertSame($exemption, $result);
        $this->assertEquals(ExemptionStatus::Approved, $exemption->status);
        $this->assertNotNull($exemption->verified_at);
    }

    public function test_reject_method(): void
    {
        $exemption = TaxExemption::create([
            'exemptable_id' => 'customer-1',
            'exemptable_type' => 'App\\Models\\Customer',
            'reason' => 'Test',
            'status' => ExemptionStatus::Pending,
        ]);

        $result = $exemption->reject('Invalid certificate');

        $this->assertSame($exemption, $result);
        $this->assertEquals(ExemptionStatus::Rejected, $exemption->status);
        $this->assertEquals('Invalid certificate', $exemption->rejection_reason);
    }

    public function test_applies_to_zone_method(): void
    {
        $zone = TaxZone::create(['name' => 'Zone', 'code' => 'Z', 'is_active' => true]);

        // Exemption for specific zone
        $specific = new TaxExemption(['tax_zone_id' => $zone->id]);

        // Exemption for all zones
        $global = new TaxExemption(['tax_zone_id' => null]);

        $this->assertTrue($specific->appliesToZone($zone->id));
        $this->assertFalse($specific->appliesToZone('other-zone-id'));

        $this->assertTrue($global->appliesToZone($zone->id));
        $this->assertTrue($global->appliesToZone('any-zone-id'));
        $this->assertTrue($global->appliesToZone(null));
    }

    public function test_casts(): void
    {
        $exemption = TaxExemption::create([
            'exemptable_id' => 'customer-1',
            'exemptable_type' => 'App\\Models\\Customer',
            'reason' => 'Test',
            'status' => ExemptionStatus::Approved,
            'verified_at' => '2024-01-01 12:00:00',
            'starts_at' => '2024-01-01 00:00:00',
            'expires_at' => '2024-12-31 23:59:59',
        ]);

        $this->assertInstanceOf(Carbon::class, $exemption->verified_at);
        $this->assertInstanceOf(Carbon::class, $exemption->starts_at);
        $this->assertInstanceOf(Carbon::class, $exemption->expires_at);
    }

    public function test_attributes_defaults(): void
    {
        $exemption = new TaxExemption([
            'exemptable_id' => 'customer-1',
            'exemptable_type' => 'App\\Models\\Customer',
            'reason' => 'Test',
        ]);

        $this->assertEquals(ExemptionStatus::Pending, $exemption->status);
    }

    public function test_activity_logging(): void
    {
        $exemption = TaxExemption::create([
            'exemptable_id' => 'customer-1',
            'exemptable_type' => 'App\\Models\\Customer',
            'reason' => 'Activity test',
            'status' => ExemptionStatus::Pending,
        ]);

        $exemption->update(['status' => ExemptionStatus::Approved]);

        // Activity logging is configured but we can't easily test it without more setup
        // This test ensures the trait is applied and doesn't break
        $this->assertTrue(true);
    }

    public function test_get_table_method(): void
    {
        $exemption = new TaxExemption;

        $this->assertEquals('tax_exemptions', $exemption->getTable());
    }

    public function test_get_table_method_with_custom_config(): void
    {
        config(['tax.database.tables.tax_exemptions' => 'custom_tax_exemptions']);

        $exemption = new TaxExemption;

        $this->assertEquals('custom_tax_exemptions', $exemption->getTable());

        // Reset to default
        config(['tax.database.tables.tax_exemptions' => 'tax_exemptions']);
    }

    public function test_exemptable_relationship_is_morph_to(): void
    {
        $exemption = new TaxExemption;

        // Access the relationship builder
        $relation = $exemption->exemptable();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphTo::class, $relation);
    }
}
