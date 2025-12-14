<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\ConversionStatus;
use AIArmada\Affiliates\Enums\MembershipStatus;
use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Models\AffiliateProgramMembership;
use AIArmada\Affiliates\Models\AffiliateProgramTier;

describe('AffiliateProgramTier Model', function (): void {
    beforeEach(function (): void {
        $this->program = AffiliateProgram::create([
            'name' => 'Test Program ' . uniqid(),
            'slug' => 'test-program-' . uniqid(),
            'status' => ProgramStatus::Active,
            'is_public' => true,
            'requires_approval' => false,
            'default_commission_rate_basis_points' => 500,
            'commission_type' => CommissionType::Percentage,
            'cookie_lifetime_days' => 30,
        ]);
    });

    test('can be created with required fields', function (): void {
        $tier = AffiliateProgramTier::create([
            'program_id' => $this->program->id,
            'name' => 'Bronze',
            'level' => 1,
            'commission_rate_basis_points' => 500,
            'min_conversions' => 0,
            'min_revenue' => 0,
        ]);

        expect($tier)->toBeInstanceOf(AffiliateProgramTier::class);
        expect($tier->name)->toBe('Bronze');
        expect($tier->level)->toBe(1);
        expect($tier->commission_rate_basis_points)->toBe(500);
    });

    test('belongs to program', function (): void {
        $tier = AffiliateProgramTier::create([
            'program_id' => $this->program->id,
            'name' => 'Silver',
            'level' => 2,
            'commission_rate_basis_points' => 750,
            'min_conversions' => 10,
            'min_revenue' => 100000,
        ]);

        expect($tier->program)->toBeInstanceOf(AffiliateProgram::class);
        expect($tier->program->id)->toBe($this->program->id);
    });

    test('has many memberships', function (): void {
        $tier = AffiliateProgramTier::create([
            'program_id' => $this->program->id,
            'name' => 'Gold',
            'level' => 3,
            'commission_rate_basis_points' => 1000,
            'min_conversions' => 50,
            'min_revenue' => 500000,
        ]);

        $affiliate = Affiliate::create([
            'code' => 'TIER' . uniqid(),
            'name' => 'Tier Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => 'percentage',
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        AffiliateProgramMembership::create([
            'program_id' => $this->program->id,
            'affiliate_id' => $affiliate->id,
            'tier_id' => $tier->id,
            'status' => MembershipStatus::Approved,
            'applied_at' => now(),
        ]);

        expect($tier->memberships)->toHaveCount(1);
        expect($tier->memberships->first()->affiliate_id)->toBe($affiliate->id);
    });

    test('getCommissionRatePercentage returns rate as percentage', function (): void {
        $tier = AffiliateProgramTier::create([
            'program_id' => $this->program->id,
            'name' => 'Platinum',
            'level' => 4,
            'commission_rate_basis_points' => 1500,
            'min_conversions' => 100,
            'min_revenue' => 1000000,
        ]);

        // 1500 basis points = 15%
        expect($tier->getCommissionRatePercentage())->toBe(15.0);
    });

    test('getCommissionRatePercentage handles various basis points', function (): void {
        $tier = AffiliateProgramTier::create([
            'program_id' => $this->program->id,
            'name' => 'Custom',
            'level' => 5,
            'commission_rate_basis_points' => 250,
            'min_conversions' => 5,
            'min_revenue' => 50000,
        ]);

        // 250 basis points = 2.5%
        expect($tier->getCommissionRatePercentage())->toBe(2.5);
    });

    test('meetsUpgradeRequirements returns false when conversions below minimum', function (): void {
        $tier = AffiliateProgramTier::create([
            'program_id' => $this->program->id,
            'name' => 'Silver',
            'level' => 2,
            'commission_rate_basis_points' => 750,
            'min_conversions' => 10,
            'min_revenue' => 0,
        ]);

        $affiliate = Affiliate::create([
            'code' => 'UPGRADE' . uniqid(),
            'name' => 'Upgrade Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => 'percentage',
            'commission_rate' => 500,
            'currency' => 'USD',
        ]);

        // Affiliate has no conversions
        expect($tier->meetsUpgradeRequirements($affiliate, $this->program))->toBeFalse();
    });

    test('meetsUpgradeRequirements returns false when revenue below minimum', function (): void {
        $tier = AffiliateProgramTier::create([
            'program_id' => $this->program->id,
            'name' => 'Gold',
            'level' => 3,
            'commission_rate_basis_points' => 1000,
            'min_conversions' => 0,
            'min_revenue' => 100000,
        ]);

        $affiliate = Affiliate::create([
            'code' => 'REVTEST' . uniqid(),
            'name' => 'Revenue Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => 'percentage',
            'commission_rate' => 500,
            'currency' => 'USD',
        ]);

        // Create attribution for program
        $attribution = AffiliateAttribution::create([
            'affiliate_id' => $affiliate->id,
            'affiliate_code' => $affiliate->code,
            'program_id' => $this->program->id,
            'visitor_fingerprint' => 'visitor123',
            'first_click_at' => now(),
            'last_click_at' => now(),
        ]);

        // Create conversion with low total
        AffiliateConversion::create([
            'affiliate_id' => $affiliate->id,
            'affiliate_code' => $affiliate->code,
            'affiliate_attribution_id' => $attribution->id,
            'order_reference' => 'ORD-REV-001',
            'total_minor' => 50000, // Only 500 in minor units, below 100000 minimum
            'commission_minor' => 5000,
            'commission_currency' => 'USD',
            'status' => ConversionStatus::Approved,
            'occurred_at' => now(),
        ]);

        // Revenue is below minimum
        expect($tier->meetsUpgradeRequirements($affiliate, $this->program))->toBeFalse();
    });

    test('meetsUpgradeRequirements returns true when all requirements met', function (): void {
        $tier = AffiliateProgramTier::create([
            'program_id' => $this->program->id,
            'name' => 'Entry Level',
            'level' => 1,
            'commission_rate_basis_points' => 500,
            'min_conversions' => 0,
            'min_revenue' => 0,
        ]);

        $affiliate = Affiliate::create([
            'code' => 'QUALIFY' . uniqid(),
            'name' => 'Qualified Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => 'percentage',
            'commission_rate' => 500,
            'currency' => 'USD',
        ]);

        // Tier with 0 min requirements should pass
        expect($tier->meetsUpgradeRequirements($affiliate, $this->program))->toBeTrue();
    });

    test('can store benefits array', function (): void {
        $benefits = [
            'priority_support' => true,
            'custom_links' => 5,
            'exclusive_offers' => ['summer_promo', 'black_friday'],
        ];

        $tier = AffiliateProgramTier::create([
            'program_id' => $this->program->id,
            'name' => 'VIP',
            'level' => 10,
            'commission_rate_basis_points' => 2000,
            'min_conversions' => 500,
            'min_revenue' => 5000000,
            'benefits' => $benefits,
        ]);

        expect($tier->benefits)->toBeArray();
        expect($tier->benefits['priority_support'])->toBeTrue();
        expect($tier->benefits['custom_links'])->toBe(5);
        expect($tier->benefits['exclusive_offers'])->toContain('summer_promo');
    });

    test('casts level as integer', function (): void {
        $tier = AffiliateProgramTier::create([
            'program_id' => $this->program->id,
            'name' => 'Entry',
            'level' => '5',
            'commission_rate_basis_points' => 300,
            'min_conversions' => 1,
            'min_revenue' => 1000,
        ]);

        expect($tier->level)->toBeInt();
        expect($tier->level)->toBe(5);
    });

    test('casts commission_rate_basis_points as integer', function (): void {
        $tier = AffiliateProgramTier::create([
            'program_id' => $this->program->id,
            'name' => 'Standard',
            'level' => 1,
            'commission_rate_basis_points' => '800',
            'min_conversions' => 0,
            'min_revenue' => 0,
        ]);

        expect($tier->commission_rate_basis_points)->toBeInt();
        expect($tier->commission_rate_basis_points)->toBe(800);
    });

    test('casts min_conversions as integer', function (): void {
        $tier = AffiliateProgramTier::create([
            'program_id' => $this->program->id,
            'name' => 'Active',
            'level' => 2,
            'commission_rate_basis_points' => 600,
            'min_conversions' => '25',
            'min_revenue' => 0,
        ]);

        expect($tier->min_conversions)->toBeInt();
        expect($tier->min_conversions)->toBe(25);
    });

    test('casts min_revenue as integer', function (): void {
        $tier = AffiliateProgramTier::create([
            'program_id' => $this->program->id,
            'name' => 'Revenue Tier',
            'level' => 3,
            'commission_rate_basis_points' => 900,
            'min_conversions' => 0,
            'min_revenue' => '250000',
        ]);

        expect($tier->min_revenue)->toBeInt();
        expect($tier->min_revenue)->toBe(250000);
    });

    test('uses correct table name from config', function (): void {
        $tier = new AffiliateProgramTier;

        expect($tier->getTable())->toBe(config('affiliates.table_names.program_tiers', 'affiliate_program_tiers'));
    });
});
