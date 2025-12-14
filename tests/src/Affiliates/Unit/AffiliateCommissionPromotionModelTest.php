<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateCommissionPromotion;
use AIArmada\Affiliates\Models\AffiliateProgram;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

describe('AffiliateCommissionPromotion Model', function (): void {
    it('can be created with required fields', function (): void {
        $promo = AffiliateCommissionPromotion::create([
            'name' => 'Holiday Bonus',
            'bonus_type' => 'percentage',
            'bonus_value' => 500,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'current_uses' => 0,
        ]);

        expect($promo)->toBeInstanceOf(AffiliateCommissionPromotion::class)
            ->and($promo->name)->toBe('Holiday Bonus')
            ->and($promo->bonus_type)->toBe('percentage')
            ->and($promo->bonus_value)->toBe(500);
    });

    it('belongs to a program', function (): void {
        $program = AffiliateProgram::create([
            'name' => 'Test Program',
            'slug' => 'test-promo-' . uniqid(),
            'status' => ProgramStatus::Active,
            'default_commission_rate_basis_points' => 1000,
            'currency' => 'USD',
        ]);

        $promo = AffiliateCommissionPromotion::create([
            'program_id' => $program->id,
            'name' => 'Program Bonus',
            'bonus_type' => 'flat',
            'bonus_value' => 1000,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'current_uses' => 0,
        ]);

        expect($promo->program())->toBeInstanceOf(BelongsTo::class)
            ->and($promo->program->id)->toBe($program->id);
    });

    it('is active when within date range', function (): void {
        $promo = AffiliateCommissionPromotion::create([
            'name' => 'Active Promo',
            'bonus_type' => 'percentage',
            'bonus_value' => 300,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'current_uses' => 0,
        ]);

        expect($promo->isActive())->toBeTrue();
    });

    it('is not active when starts in future', function (): void {
        $promo = AffiliateCommissionPromotion::create([
            'name' => 'Future Promo',
            'bonus_type' => 'percentage',
            'bonus_value' => 300,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addMonth(),
            'current_uses' => 0,
        ]);

        expect($promo->isActive())->toBeFalse();
    });

    it('is not active when ended', function (): void {
        $promo = AffiliateCommissionPromotion::create([
            'name' => 'Ended Promo',
            'bonus_type' => 'percentage',
            'bonus_value' => 300,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subDay(),
            'current_uses' => 0,
        ]);

        expect($promo->isActive())->toBeFalse();
    });

    it('is not active when max uses reached', function (): void {
        $promo = AffiliateCommissionPromotion::create([
            'name' => 'Limited Promo',
            'bonus_type' => 'percentage',
            'bonus_value' => 300,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'max_uses' => 10,
            'current_uses' => 10,
        ]);

        expect($promo->isActive())->toBeFalse();
    });

    it('applies to any affiliate when affiliate_ids is null', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'PROMO-AFF-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $promo = AffiliateCommissionPromotion::create([
            'name' => 'Universal Promo',
            'bonus_type' => 'percentage',
            'bonus_value' => 300,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'current_uses' => 0,
            'affiliate_ids' => null,
        ]);

        expect($promo->appliesToAffiliate($affiliate))->toBeTrue();
    });

    it('applies to specific affiliates when affiliate_ids is set', function (): void {
        $includedAffiliate = Affiliate::create([
            'code' => 'PROMO-INC-' . uniqid(),
            'name' => 'Included Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $excludedAffiliate = Affiliate::create([
            'code' => 'PROMO-EXC-' . uniqid(),
            'name' => 'Excluded Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $promo = AffiliateCommissionPromotion::create([
            'name' => 'Limited Promo',
            'bonus_type' => 'percentage',
            'bonus_value' => 300,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'current_uses' => 0,
            'affiliate_ids' => [$includedAffiliate->id],
        ]);

        expect($promo->appliesToAffiliate($includedAffiliate))->toBeTrue()
            ->and($promo->appliesToAffiliate($excludedAffiliate))->toBeFalse();
    });

    it('increments usage', function (): void {
        $promo = AffiliateCommissionPromotion::create([
            'name' => 'Increment Test',
            'bonus_type' => 'percentage',
            'bonus_value' => 300,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'current_uses' => 5,
        ]);

        $promo->incrementUsage();

        $promo->refresh();
        expect($promo->current_uses)->toBe(6);
    });

    it('calculates percentage bonus', function (): void {
        $promo = AffiliateCommissionPromotion::create([
            'name' => 'Percentage Bonus',
            'bonus_type' => 'percentage',
            'bonus_value' => 2500, // 25%
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'current_uses' => 0,
        ]);

        // 25% of 10000 = 2500
        expect($promo->calculateBonus(10000))->toBe(2500);
    });

    it('calculates flat bonus', function (): void {
        $promo = AffiliateCommissionPromotion::create([
            'name' => 'Flat Bonus',
            'bonus_type' => 'flat',
            'bonus_value' => 500,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'current_uses' => 0,
        ]);

        expect($promo->calculateBonus(10000))->toBe(500);
    });

    it('calculates multiplier bonus', function (): void {
        $promo = AffiliateCommissionPromotion::create([
            'name' => 'Multiplier Bonus',
            'bonus_type' => 'multiplier',
            'bonus_value' => 200, // 2x multiplier
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'current_uses' => 0,
        ]);

        // (2.0 - 1) * 10000 = 10000
        expect($promo->calculateBonus(10000))->toBe(10000);
    });

    it('scopes active promotions', function (): void {
        AffiliateCommissionPromotion::create([
            'name' => 'Active Scoped',
            'bonus_type' => 'percentage',
            'bonus_value' => 300,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'current_uses' => 0,
        ]);

        AffiliateCommissionPromotion::create([
            'name' => 'Expired Scoped',
            'bonus_type' => 'percentage',
            'bonus_value' => 300,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subDay(),
            'current_uses' => 0,
        ]);

        $active = AffiliateCommissionPromotion::active()->get();

        expect($active->pluck('name')->contains('Active Scoped'))->toBeTrue()
            ->and($active->pluck('name')->contains('Expired Scoped'))->toBeFalse();
    });

    it('uses correct table name from config', function (): void {
        $promo = new AffiliateCommissionPromotion;
        expect($promo->getTable())->toBe('affiliate_commission_promotions');
    });
});
