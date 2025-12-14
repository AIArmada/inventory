<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Models\AffiliateProgramTier;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

describe('AffiliateProgramTier Model', function (): void {
    it('can be created with required fields', function (): void {
        $program = AffiliateProgram::create([
            'name' => 'Test Program',
            'slug' => 'test-prog-tier-' . uniqid(),
            'status' => ProgramStatus::Active,
            'default_commission_rate_basis_points' => 1000,
            'currency' => 'USD',
        ]);

        $tier = AffiliateProgramTier::create([
            'program_id' => $program->id,
            'name' => 'Gold Tier',
            'level' => 1,
            'commission_rate_basis_points' => 1500,
            'min_conversions' => 100,
            'min_revenue' => 50000,
        ]);

        expect($tier)->toBeInstanceOf(AffiliateProgramTier::class)
            ->and($tier->name)->toBe('Gold Tier')
            ->and($tier->level)->toBe(1)
            ->and($tier->commission_rate_basis_points)->toBe(1500)
            ->and($tier->min_conversions)->toBe(100);
    });

    it('belongs to a program', function (): void {
        $program = AffiliateProgram::create([
            'name' => 'Test Program',
            'slug' => 'test-prog-tier2-' . uniqid(),
            'status' => ProgramStatus::Active,
            'default_commission_rate_basis_points' => 1000,
            'currency' => 'USD',
        ]);

        $tier = AffiliateProgramTier::create([
            'program_id' => $program->id,
            'name' => 'Silver Tier',
            'level' => 2,
            'commission_rate_basis_points' => 1000,
            'min_conversions' => 50,
            'min_revenue' => 25000,
        ]);

        expect($tier->program())->toBeInstanceOf(BelongsTo::class)
            ->and($tier->program->id)->toBe($program->id);
    });

    it('has many memberships', function (): void {
        $program = AffiliateProgram::create([
            'name' => 'Test Program',
            'slug' => 'test-prog-tier3-' . uniqid(),
            'status' => ProgramStatus::Active,
            'default_commission_rate_basis_points' => 1000,
            'currency' => 'USD',
        ]);

        $tier = AffiliateProgramTier::create([
            'program_id' => $program->id,
            'name' => 'Bronze Tier',
            'level' => 3,
            'commission_rate_basis_points' => 750,
            'min_conversions' => 10,
            'min_revenue' => 5000,
        ]);

        expect($tier->memberships())->toBeInstanceOf(HasMany::class);
    });

    it('calculates commission rate percentage', function (): void {
        $program = AffiliateProgram::create([
            'name' => 'Test Program',
            'slug' => 'test-prog-tier4-' . uniqid(),
            'status' => ProgramStatus::Active,
            'default_commission_rate_basis_points' => 1000,
            'currency' => 'USD',
        ]);

        $tier = AffiliateProgramTier::create([
            'program_id' => $program->id,
            'name' => 'Premium Tier',
            'level' => 1,
            'commission_rate_basis_points' => 2000,
            'min_conversions' => 200,
            'min_revenue' => 100000,
        ]);

        expect($tier->getCommissionRatePercentage())->toBe(20.0);
    });

    it('stores benefits as array', function (): void {
        $program = AffiliateProgram::create([
            'name' => 'Test Program',
            'slug' => 'test-prog-tier5-' . uniqid(),
            'status' => ProgramStatus::Active,
            'default_commission_rate_basis_points' => 1000,
            'currency' => 'USD',
        ]);

        $tier = AffiliateProgramTier::create([
            'program_id' => $program->id,
            'name' => 'VIP Tier',
            'level' => 1,
            'commission_rate_basis_points' => 2500,
            'min_conversions' => 500,
            'min_revenue' => 250000,
            'benefits' => [
                'dedicated_support' => true,
                'priority_payouts' => true,
                'exclusive_products' => ['SKU001', 'SKU002'],
            ],
        ]);

        expect($tier->benefits)->toBeArray()
            ->and($tier->benefits['dedicated_support'])->toBeTrue()
            ->and($tier->benefits['exclusive_products'])->toBeArray();
    });

    it('casts numeric fields as integers', function (): void {
        $program = AffiliateProgram::create([
            'name' => 'Test Program',
            'slug' => 'test-prog-tier6-' . uniqid(),
            'status' => ProgramStatus::Active,
            'default_commission_rate_basis_points' => 1000,
            'currency' => 'USD',
        ]);

        $tier = AffiliateProgramTier::create([
            'program_id' => $program->id,
            'name' => 'Cast Test',
            'level' => '2',
            'commission_rate_basis_points' => '1200',
            'min_conversions' => '75',
            'min_revenue' => '35000',
        ]);

        expect($tier->level)->toBeInt()
            ->and($tier->commission_rate_basis_points)->toBeInt()
            ->and($tier->min_conversions)->toBeInt()
            ->and($tier->min_revenue)->toBeInt();
    });

    it('uses correct table name from config', function (): void {
        $tier = new AffiliateProgramTier;
        expect($tier->getTable())->toBe('affiliate_program_tiers');
    });
});
