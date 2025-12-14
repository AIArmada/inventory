<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\MembershipStatus;
use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Models\AffiliateProgramMembership;
use AIArmada\Affiliates\Models\AffiliateProgramTier;

describe('AffiliateProgramMembership Model', function (): void {
    beforeEach(function (): void {
        $this->program = AffiliateProgram::create([
            'name' => 'Membership Program ' . uniqid(),
            'slug' => 'membership-program-' . uniqid(),
            'status' => ProgramStatus::Active,
            'is_public' => true,
            'requires_approval' => true,
            'default_commission_rate_basis_points' => 500,
            'commission_type' => CommissionType::Percentage,
            'cookie_lifetime_days' => 30,
        ]);

        $this->affiliate = Affiliate::create([
            'code' => 'MEMBER' . uniqid(),
            'name' => 'Membership Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => 'percentage',
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $this->tier = AffiliateProgramTier::create([
            'program_id' => $this->program->id,
            'name' => 'Bronze',
            'level' => 1,
            'commission_rate_basis_points' => 500,
            'min_conversions' => 0,
            'min_revenue' => 0,
        ]);
    });

    test('can be created with required fields', function (): void {
        $membership = AffiliateProgramMembership::create([
            'affiliate_id' => $this->affiliate->id,
            'program_id' => $this->program->id,
            'tier_id' => $this->tier->id,
            'status' => MembershipStatus::Pending,
            'applied_at' => now(),
        ]);

        expect($membership)->toBeInstanceOf(AffiliateProgramMembership::class);
        expect($membership->status)->toBe(MembershipStatus::Pending);
    });

    test('belongs to affiliate', function (): void {
        $membership = AffiliateProgramMembership::create([
            'affiliate_id' => $this->affiliate->id,
            'program_id' => $this->program->id,
            'status' => MembershipStatus::Pending,
            'applied_at' => now(),
        ]);

        expect($membership->affiliate)->toBeInstanceOf(Affiliate::class);
        expect($membership->affiliate->id)->toBe($this->affiliate->id);
    });

    test('belongs to program', function (): void {
        $membership = AffiliateProgramMembership::create([
            'affiliate_id' => $this->affiliate->id,
            'program_id' => $this->program->id,
            'status' => MembershipStatus::Pending,
            'applied_at' => now(),
        ]);

        expect($membership->program)->toBeInstanceOf(AffiliateProgram::class);
        expect($membership->program->id)->toBe($this->program->id);
    });

    test('belongs to tier', function (): void {
        $membership = AffiliateProgramMembership::create([
            'affiliate_id' => $this->affiliate->id,
            'program_id' => $this->program->id,
            'tier_id' => $this->tier->id,
            'status' => MembershipStatus::Approved,
            'applied_at' => now(),
        ]);

        expect($membership->tier)->toBeInstanceOf(AffiliateProgramTier::class);
        expect($membership->tier->id)->toBe($this->tier->id);
    });

    test('isActive returns true when approved and not expired', function (): void {
        $membership = AffiliateProgramMembership::create([
            'affiliate_id' => $this->affiliate->id,
            'program_id' => $this->program->id,
            'status' => MembershipStatus::Approved,
            'applied_at' => now()->subDay(),
            'approved_at' => now(),
        ]);

        expect($membership->isActive())->toBeTrue();
    });

    test('isActive returns false when pending', function (): void {
        $membership = AffiliateProgramMembership::create([
            'affiliate_id' => $this->affiliate->id,
            'program_id' => $this->program->id,
            'status' => MembershipStatus::Pending,
            'applied_at' => now(),
        ]);

        expect($membership->isActive())->toBeFalse();
    });

    test('isActive returns false when rejected', function (): void {
        $membership = AffiliateProgramMembership::create([
            'affiliate_id' => $this->affiliate->id,
            'program_id' => $this->program->id,
            'status' => MembershipStatus::Rejected,
            'applied_at' => now(),
        ]);

        expect($membership->isActive())->toBeFalse();
    });

    test('isActive returns false when expired', function (): void {
        $membership = AffiliateProgramMembership::create([
            'affiliate_id' => $this->affiliate->id,
            'program_id' => $this->program->id,
            'status' => MembershipStatus::Approved,
            'applied_at' => now()->subMonth(),
            'approved_at' => now()->subMonth(),
            'expires_at' => now()->subDay(),
        ]);

        expect($membership->isActive())->toBeFalse();
    });

    test('isActive returns true when not yet expired', function (): void {
        $membership = AffiliateProgramMembership::create([
            'affiliate_id' => $this->affiliate->id,
            'program_id' => $this->program->id,
            'status' => MembershipStatus::Approved,
            'applied_at' => now()->subMonth(),
            'approved_at' => now()->subMonth(),
            'expires_at' => now()->addMonth(),
        ]);

        expect($membership->isActive())->toBeTrue();
    });

    test('approve method updates status and timestamp', function (): void {
        $membership = AffiliateProgramMembership::create([
            'affiliate_id' => $this->affiliate->id,
            'program_id' => $this->program->id,
            'status' => MembershipStatus::Pending,
            'applied_at' => now(),
        ]);

        $membership->approve('admin@example.com');

        $membership->refresh();
        expect($membership->status)->toBe(MembershipStatus::Approved);
        expect($membership->approved_at)->not->toBeNull();
        expect($membership->approved_by)->toBe('admin@example.com');
    });

    test('approve method works without approver', function (): void {
        $membership = AffiliateProgramMembership::create([
            'affiliate_id' => $this->affiliate->id,
            'program_id' => $this->program->id,
            'status' => MembershipStatus::Pending,
            'applied_at' => now(),
        ]);

        $membership->approve();

        $membership->refresh();
        expect($membership->status)->toBe(MembershipStatus::Approved);
        expect($membership->approved_by)->toBeNull();
    });

    test('reject method updates status', function (): void {
        $membership = AffiliateProgramMembership::create([
            'affiliate_id' => $this->affiliate->id,
            'program_id' => $this->program->id,
            'status' => MembershipStatus::Pending,
            'applied_at' => now(),
        ]);

        $membership->reject();

        $membership->refresh();
        expect($membership->status)->toBe(MembershipStatus::Rejected);
    });

    test('suspend method updates status', function (): void {
        $membership = AffiliateProgramMembership::create([
            'affiliate_id' => $this->affiliate->id,
            'program_id' => $this->program->id,
            'status' => MembershipStatus::Approved,
            'applied_at' => now(),
            'approved_at' => now(),
        ]);

        $membership->suspend();

        $membership->refresh();
        expect($membership->status)->toBe(MembershipStatus::Suspended);
    });

    test('upgradeTier method updates tier', function (): void {
        $silverTier = AffiliateProgramTier::create([
            'program_id' => $this->program->id,
            'name' => 'Silver',
            'level' => 2,
            'commission_rate_basis_points' => 750,
            'min_conversions' => 10,
            'min_revenue' => 100000,
        ]);

        $membership = AffiliateProgramMembership::create([
            'affiliate_id' => $this->affiliate->id,
            'program_id' => $this->program->id,
            'tier_id' => $this->tier->id,
            'status' => MembershipStatus::Approved,
            'applied_at' => now(),
            'approved_at' => now(),
        ]);

        $membership->upgradeTier($silverTier);

        $membership->refresh();
        expect($membership->tier_id)->toBe($silverTier->id);
    });

    test('can store custom_terms array', function (): void {
        $customTerms = [
            'special_rate' => 1200,
            'bonus_percentage' => 5,
            'exclusive_products' => ['product-a', 'product-b'],
        ];

        $membership = AffiliateProgramMembership::create([
            'affiliate_id' => $this->affiliate->id,
            'program_id' => $this->program->id,
            'status' => MembershipStatus::Approved,
            'applied_at' => now(),
            'custom_terms' => $customTerms,
        ]);

        expect($membership->custom_terms)->toBeArray();
        expect($membership->custom_terms['special_rate'])->toBe(1200);
        expect($membership->custom_terms['bonus_percentage'])->toBe(5);
    });

    test('casts status as enum', function (): void {
        $membership = AffiliateProgramMembership::create([
            'affiliate_id' => $this->affiliate->id,
            'program_id' => $this->program->id,
            'status' => MembershipStatus::Pending,
            'applied_at' => now(),
        ]);

        expect($membership->status)->toBeInstanceOf(MembershipStatus::class);
    });

    test('casts dates correctly', function (): void {
        $membership = AffiliateProgramMembership::create([
            'affiliate_id' => $this->affiliate->id,
            'program_id' => $this->program->id,
            'status' => MembershipStatus::Approved,
            'applied_at' => '2024-06-01 10:00:00',
            'approved_at' => '2024-06-02 14:00:00',
            'expires_at' => '2025-06-01 23:59:59',
        ]);

        expect($membership->applied_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
        expect($membership->approved_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
        expect($membership->expires_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    });

    test('uses correct table name from config', function (): void {
        $membership = new AffiliateProgramMembership;

        expect($membership->getTable())->toBe(config('affiliates.table_names.program_memberships', 'affiliate_program_memberships'));
    });
});
