<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\MembershipStatus;
use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Models\AffiliateProgramCreative;
use AIArmada\Affiliates\Models\AffiliateProgramMembership;
use AIArmada\Affiliates\Models\AffiliateProgramTier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

describe('AffiliateProgram Model', function (): void {
    it('can be created with required fields', function (): void {
        $program = AffiliateProgram::create([
            'name' => 'Test Program',
            'status' => ProgramStatus::Active,
            'requires_approval' => false,
            'is_public' => true,
            'default_commission_rate_basis_points' => 1000,
            'commission_type' => CommissionType::Percentage,
            'cookie_lifetime_days' => 30,
        ]);

        expect($program)->toBeInstanceOf(AffiliateProgram::class)
            ->and($program->name)->toBe('Test Program')
            ->and($program->status)->toBe(ProgramStatus::Active);
    });

    it('auto-generates slug from name on creation', function (): void {
        $program = AffiliateProgram::create([
            'name' => 'My Awesome Program',
            'status' => ProgramStatus::Active,
            'requires_approval' => false,
            'is_public' => true,
            'default_commission_rate_basis_points' => 1000,
            'commission_type' => CommissionType::Percentage,
            'cookie_lifetime_days' => 30,
        ]);

        expect($program->slug)->toBe('my-awesome-program');
    });

    it('does not overwrite explicit slug', function (): void {
        $program = AffiliateProgram::create([
            'name' => 'My Program',
            'slug' => 'custom-slug',
            'status' => ProgramStatus::Active,
            'requires_approval' => false,
            'is_public' => true,
            'default_commission_rate_basis_points' => 1000,
            'commission_type' => CommissionType::Percentage,
            'cookie_lifetime_days' => 30,
        ]);

        expect($program->slug)->toBe('custom-slug');
    });

    it('has many tiers ordered by level', function (): void {
        $program = AffiliateProgram::create([
            'name' => 'Test Program',
            'status' => ProgramStatus::Active,
            'requires_approval' => false,
            'is_public' => true,
            'default_commission_rate_basis_points' => 1000,
            'commission_type' => CommissionType::Percentage,
            'cookie_lifetime_days' => 30,
        ]);

        AffiliateProgramTier::create([
            'program_id' => $program->id,
            'name' => 'Gold',
            'level' => 2,
            'commission_rate_basis_points' => 1500,
        ]);

        AffiliateProgramTier::create([
            'program_id' => $program->id,
            'name' => 'Silver',
            'level' => 1,
            'commission_rate_basis_points' => 1000,
        ]);

        $program->refresh();
        $tiers = $program->tiers;

        expect($tiers)->toHaveCount(2)
            ->and($tiers->first()->name)->toBe('Silver') // Level 1 first (ordered by level)
            ->and($tiers->last()->name)->toBe('Gold'); // Level 2 second
    });

    it('belongs to many affiliates through memberships', function (): void {
        $program = AffiliateProgram::create([
            'name' => 'Test Program',
            'status' => ProgramStatus::Active,
            'requires_approval' => false,
            'is_public' => true,
            'default_commission_rate_basis_points' => 1000,
            'commission_type' => CommissionType::Percentage,
            'cookie_lifetime_days' => 30,
        ]);

        $affiliate = Affiliate::create([
            'code' => 'TEST-001',
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => 'percentage',
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        AffiliateProgramMembership::create([
            'affiliate_id' => $affiliate->id,
            'program_id' => $program->id,
            'status' => MembershipStatus::Approved,
            'applied_at' => now(),
        ]);

        $program->refresh();
        expect($program->affiliates)->toHaveCount(1)
            ->and($program->affiliates->first()->id)->toBe($affiliate->id);
    });

    it('has many creatives', function (): void {
        $program = AffiliateProgram::create([
            'name' => 'Test Program',
            'status' => ProgramStatus::Active,
            'requires_approval' => false,
            'is_public' => true,
            'default_commission_rate_basis_points' => 1000,
            'commission_type' => CommissionType::Percentage,
            'cookie_lifetime_days' => 30,
        ]);

        AffiliateProgramCreative::create([
            'program_id' => $program->id,
            'name' => 'Banner Ad',
            'type' => 'banner',
            'asset_url' => 'https://example.com/banner.jpg',
            'destination_url' => 'https://example.com',
            'tracking_code' => 'ABC123',
        ]);

        $program->refresh();
        expect($program->creatives)->toHaveCount(1);
    });

    it('has many memberships', function (): void {
        $program = AffiliateProgram::create([
            'name' => 'Test Program',
            'status' => ProgramStatus::Active,
            'requires_approval' => false,
            'is_public' => true,
            'default_commission_rate_basis_points' => 1000,
            'commission_type' => CommissionType::Percentage,
            'cookie_lifetime_days' => 30,
        ]);

        $affiliate = Affiliate::create([
            'code' => 'TEST-001',
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => 'percentage',
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        AffiliateProgramMembership::create([
            'affiliate_id' => $affiliate->id,
            'program_id' => $program->id,
            'status' => MembershipStatus::Approved,
            'applied_at' => now(),
        ]);

        $program->refresh();
        expect($program->memberships)->toHaveCount(1);
    });

    it('isActive returns true when status is active and within date range', function (): void {
        $program = AffiliateProgram::create([
            'name' => 'Test Program',
            'status' => ProgramStatus::Active,
            'starts_at' => Carbon::now()->subDay(),
            'ends_at' => Carbon::now()->addDay(),
            'requires_approval' => false,
            'is_public' => true,
            'default_commission_rate_basis_points' => 1000,
            'commission_type' => CommissionType::Percentage,
            'cookie_lifetime_days' => 30,
        ]);

        expect($program->isActive())->toBeTrue();
    });

    it('isActive returns false when status is not active', function (): void {
        $program = AffiliateProgram::create([
            'name' => 'Test Program',
            'status' => ProgramStatus::Draft,
            'requires_approval' => false,
            'is_public' => true,
            'default_commission_rate_basis_points' => 1000,
            'commission_type' => CommissionType::Percentage,
            'cookie_lifetime_days' => 30,
        ]);

        expect($program->isActive())->toBeFalse();
    });

    it('isActive returns false when starts_at is in future', function (): void {
        $program = AffiliateProgram::create([
            'name' => 'Test Program',
            'status' => ProgramStatus::Active,
            'starts_at' => Carbon::now()->addWeek(),
            'requires_approval' => false,
            'is_public' => true,
            'default_commission_rate_basis_points' => 1000,
            'commission_type' => CommissionType::Percentage,
            'cookie_lifetime_days' => 30,
        ]);

        expect($program->isActive())->toBeFalse();
    });

    it('isActive returns false when ends_at is in past', function (): void {
        $program = AffiliateProgram::create([
            'name' => 'Test Program',
            'status' => ProgramStatus::Active,
            'ends_at' => Carbon::now()->subDay(),
            'requires_approval' => false,
            'is_public' => true,
            'default_commission_rate_basis_points' => 1000,
            'commission_type' => CommissionType::Percentage,
            'cookie_lifetime_days' => 30,
        ]);

        expect($program->isActive())->toBeFalse();
    });

    it('isOpen returns true when active and public', function (): void {
        $program = AffiliateProgram::create([
            'name' => 'Test Program',
            'status' => ProgramStatus::Active,
            'requires_approval' => false,
            'is_public' => true,
            'default_commission_rate_basis_points' => 1000,
            'commission_type' => CommissionType::Percentage,
            'cookie_lifetime_days' => 30,
        ]);

        expect($program->isOpen())->toBeTrue();
    });

    it('isOpen returns false when not public', function (): void {
        $program = AffiliateProgram::create([
            'name' => 'Test Program',
            'status' => ProgramStatus::Active,
            'requires_approval' => false,
            'is_public' => false,
            'default_commission_rate_basis_points' => 1000,
            'commission_type' => CommissionType::Percentage,
            'cookie_lifetime_days' => 30,
        ]);

        expect($program->isOpen())->toBeFalse();
    });

    it('canJoin returns true for eligible affiliate', function (): void {
        $program = AffiliateProgram::create([
            'name' => 'Test Program',
            'status' => ProgramStatus::Active,
            'requires_approval' => false,
            'is_public' => true,
            'default_commission_rate_basis_points' => 1000,
            'commission_type' => CommissionType::Percentage,
            'cookie_lifetime_days' => 30,
        ]);

        $affiliate = Affiliate::create([
            'code' => 'TEST-001',
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => 'percentage',
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        expect($program->canJoin($affiliate))->toBeTrue();
    });

    it('canJoin returns false when already a member', function (): void {
        $program = AffiliateProgram::create([
            'name' => 'Test Program',
            'status' => ProgramStatus::Active,
            'requires_approval' => false,
            'is_public' => true,
            'default_commission_rate_basis_points' => 1000,
            'commission_type' => CommissionType::Percentage,
            'cookie_lifetime_days' => 30,
        ]);

        $affiliate = Affiliate::create([
            'code' => 'TEST-001',
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => 'percentage',
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        AffiliateProgramMembership::create([
            'affiliate_id' => $affiliate->id,
            'program_id' => $program->id,
            'status' => MembershipStatus::Approved,
            'applied_at' => now(),
        ]);

        expect($program->canJoin($affiliate))->toBeFalse();
    });

    it('canJoin evaluates min_conversions eligibility rule', function (): void {
        $program = AffiliateProgram::create([
            'name' => 'Test Program',
            'status' => ProgramStatus::Active,
            'requires_approval' => false,
            'is_public' => true,
            'default_commission_rate_basis_points' => 1000,
            'commission_type' => CommissionType::Percentage,
            'cookie_lifetime_days' => 30,
            'eligibility_rules' => ['min_conversions' => 10],
        ]);

        $affiliate = Affiliate::create([
            'code' => 'TEST-001',
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => 'percentage',
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        // Affiliate has no conversions, should not be able to join
        expect($program->canJoin($affiliate))->toBeFalse();
    });

    it('canJoin evaluates required_status eligibility rule', function (): void {
        $program = AffiliateProgram::create([
            'name' => 'Test Program',
            'status' => ProgramStatus::Active,
            'requires_approval' => false,
            'is_public' => true,
            'default_commission_rate_basis_points' => 1000,
            'commission_type' => CommissionType::Percentage,
            'cookie_lifetime_days' => 30,
            'eligibility_rules' => ['required_status' => 'active'],
        ]);

        $affiliate = Affiliate::create([
            'code' => 'TEST-001',
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Pending,
            'commission_type' => 'percentage',
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        expect($program->canJoin($affiliate))->toBeFalse();
    });

    it('getDefaultTier returns lowest level tier', function (): void {
        $program = AffiliateProgram::create([
            'name' => 'Test Program',
            'status' => ProgramStatus::Active,
            'requires_approval' => false,
            'is_public' => true,
            'default_commission_rate_basis_points' => 1000,
            'commission_type' => CommissionType::Percentage,
            'cookie_lifetime_days' => 30,
        ]);

        AffiliateProgramTier::create([
            'program_id' => $program->id,
            'name' => 'Bronze',
            'level' => 1,
            'commission_rate_basis_points' => 500,
        ]);

        AffiliateProgramTier::create([
            'program_id' => $program->id,
            'name' => 'Silver',
            'level' => 2,
            'commission_rate_basis_points' => 1000,
        ]);

        $defaultTier = $program->getDefaultTier();

        // getDefaultTier returns the entry-level tier (lowest level number)
        expect($defaultTier)->not->toBeNull()
            ->and($defaultTier->name)->toBe('Bronze');
    });

    it('scope active filters correctly', function (): void {
        AffiliateProgram::create([
            'name' => 'Active Program',
            'status' => ProgramStatus::Active,
            'requires_approval' => false,
            'is_public' => true,
            'default_commission_rate_basis_points' => 1000,
            'commission_type' => CommissionType::Percentage,
            'cookie_lifetime_days' => 30,
        ]);

        AffiliateProgram::create([
            'name' => 'Draft Program',
            'status' => ProgramStatus::Draft,
            'requires_approval' => false,
            'is_public' => true,
            'default_commission_rate_basis_points' => 1000,
            'commission_type' => CommissionType::Percentage,
            'cookie_lifetime_days' => 30,
        ]);

        AffiliateProgram::create([
            'name' => 'Future Program',
            'status' => ProgramStatus::Active,
            'starts_at' => Carbon::now()->addWeek(),
            'requires_approval' => false,
            'is_public' => true,
            'default_commission_rate_basis_points' => 1000,
            'commission_type' => CommissionType::Percentage,
            'cookie_lifetime_days' => 30,
        ]);

        $activePrograms = AffiliateProgram::active()->get();

        expect($activePrograms)->toHaveCount(1)
            ->and($activePrograms->first()->name)->toBe('Active Program');
    });

    it('scope public filters correctly', function (): void {
        AffiliateProgram::create([
            'name' => 'Public Program',
            'status' => ProgramStatus::Active,
            'requires_approval' => false,
            'is_public' => true,
            'default_commission_rate_basis_points' => 1000,
            'commission_type' => CommissionType::Percentage,
            'cookie_lifetime_days' => 30,
        ]);

        AffiliateProgram::create([
            'name' => 'Private Program',
            'status' => ProgramStatus::Active,
            'requires_approval' => false,
            'is_public' => false,
            'default_commission_rate_basis_points' => 1000,
            'commission_type' => CommissionType::Percentage,
            'cookie_lifetime_days' => 30,
        ]);

        $publicPrograms = AffiliateProgram::public()->get();

        expect($publicPrograms)->toHaveCount(1)
            ->and($publicPrograms->first()->name)->toBe('Public Program');
    });

    it('casts status as enum', function (): void {
        $program = AffiliateProgram::create([
            'name' => 'Test Program',
            'status' => ProgramStatus::Active,
            'requires_approval' => false,
            'is_public' => true,
            'default_commission_rate_basis_points' => 1000,
            'commission_type' => CommissionType::Percentage,
            'cookie_lifetime_days' => 30,
        ]);

        expect($program->status)->toBeInstanceOf(ProgramStatus::class)
            ->and($program->status)->toBe(ProgramStatus::Active);
    });

    it('casts commission_type as enum', function (): void {
        $program = AffiliateProgram::create([
            'name' => 'Test Program',
            'status' => ProgramStatus::Active,
            'requires_approval' => false,
            'is_public' => true,
            'default_commission_rate_basis_points' => 1000,
            'commission_type' => CommissionType::Percentage,
            'cookie_lifetime_days' => 30,
        ]);

        expect($program->commission_type)->toBeInstanceOf(CommissionType::class);
    });

    it('casts boolean fields correctly', function (): void {
        $program = AffiliateProgram::create([
            'name' => 'Test Program',
            'status' => ProgramStatus::Active,
            'requires_approval' => '1',
            'is_public' => '0',
            'default_commission_rate_basis_points' => 1000,
            'commission_type' => CommissionType::Percentage,
            'cookie_lifetime_days' => 30,
        ]);

        expect($program->requires_approval)->toBeBool()->toBeTrue()
            ->and($program->is_public)->toBeBool()->toBeFalse();
    });

    it('casts eligibility_rules as array', function (): void {
        $rules = ['min_conversions' => 5, 'min_revenue' => 10000];

        $program = AffiliateProgram::create([
            'name' => 'Test Program',
            'status' => ProgramStatus::Active,
            'requires_approval' => false,
            'is_public' => true,
            'default_commission_rate_basis_points' => 1000,
            'commission_type' => CommissionType::Percentage,
            'cookie_lifetime_days' => 30,
            'eligibility_rules' => $rules,
        ]);

        expect($program->eligibility_rules)->toBeArray()
            ->and($program->eligibility_rules)->toBe($rules);
    });

    it('casts metadata as array', function (): void {
        $metadata = ['category' => 'premium', 'priority' => 1];

        $program = AffiliateProgram::create([
            'name' => 'Test Program',
            'status' => ProgramStatus::Active,
            'requires_approval' => false,
            'is_public' => true,
            'default_commission_rate_basis_points' => 1000,
            'commission_type' => CommissionType::Percentage,
            'cookie_lifetime_days' => 30,
            'metadata' => $metadata,
        ]);

        expect($program->metadata)->toBeArray()
            ->and($program->metadata)->toBe($metadata);
    });

    it('uses correct table name from config', function (): void {
        $program = new AffiliateProgram;

        expect($program->getTable())->toBe('affiliate_programs');
    });

    it('uses soft deletes', function (): void {
        $program = AffiliateProgram::create([
            'name' => 'Test Program',
            'status' => ProgramStatus::Active,
            'requires_approval' => false,
            'is_public' => true,
            'default_commission_rate_basis_points' => 1000,
            'commission_type' => CommissionType::Percentage,
            'cookie_lifetime_days' => 30,
        ]);

        $program->delete();

        expect($program->trashed())->toBeTrue()
            ->and(AffiliateProgram::withTrashed()->find($program->id))->not->toBeNull();
    });
});
