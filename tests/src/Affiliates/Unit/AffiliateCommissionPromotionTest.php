<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateCommissionPromotion;
use AIArmada\Affiliates\Models\AffiliateProgram;

describe('AffiliateCommissionPromotion Model', function (): void {
    beforeEach(function (): void {
        $this->program = AffiliateProgram::create([
            'name' => 'Promotion Program ' . uniqid(),
            'slug' => 'promotion-program-' . uniqid(),
            'status' => ProgramStatus::Active,
            'is_public' => true,
            'requires_approval' => false,
            'default_commission_rate_basis_points' => 500,
            'commission_type' => CommissionType::Percentage,
            'cookie_lifetime_days' => 30,
        ]);

        $this->affiliate = Affiliate::create([
            'code' => 'PROMO' . uniqid(),
            'name' => 'Promo Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => 'percentage',
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);
    });

    test('can be created with required fields', function (): void {
        $promotion = AffiliateCommissionPromotion::create([
            'program_id' => $this->program->id,
            'name' => 'Holiday Bonus',
            'description' => 'Extra commission for holidays',
            'bonus_type' => 'percentage',
            'bonus_value' => 500,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addWeek(),
        ]);

        expect($promotion)->toBeInstanceOf(AffiliateCommissionPromotion::class);
        expect($promotion->name)->toBe('Holiday Bonus');
        expect($promotion->bonus_type)->toBe('percentage');
        expect($promotion->bonus_value)->toBe(500);
    });

    test('belongs to program', function (): void {
        $promotion = AffiliateCommissionPromotion::create([
            'program_id' => $this->program->id,
            'name' => 'Program Promo',
            'bonus_type' => 'flat',
            'bonus_value' => 1000,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addWeek(),
        ]);

        expect($promotion->program)->toBeInstanceOf(AffiliateProgram::class);
        expect($promotion->program->id)->toBe($this->program->id);
    });

    test('isActive returns true when promotion is within date range', function (): void {
        $promotion = AffiliateCommissionPromotion::create([
            'name' => 'Active Promo',
            'bonus_type' => 'percentage',
            'bonus_value' => 500,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        expect($promotion->isActive())->toBeTrue();
    });

    test('isActive returns false when promotion has not started', function (): void {
        $promotion = AffiliateCommissionPromotion::create([
            'name' => 'Future Promo',
            'bonus_type' => 'percentage',
            'bonus_value' => 500,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addWeek(),
        ]);

        expect($promotion->isActive())->toBeFalse();
    });

    test('isActive returns false when promotion has ended', function (): void {
        $promotion = AffiliateCommissionPromotion::create([
            'name' => 'Ended Promo',
            'bonus_type' => 'percentage',
            'bonus_value' => 500,
            'starts_at' => now()->subWeek(),
            'ends_at' => now()->subDay(),
        ]);

        expect($promotion->isActive())->toBeFalse();
    });

    test('isActive returns false when max uses reached', function (): void {
        $promotion = AffiliateCommissionPromotion::create([
            'name' => 'Limited Promo',
            'bonus_type' => 'percentage',
            'bonus_value' => 500,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'max_uses' => 10,
            'current_uses' => 10,
        ]);

        expect($promotion->isActive())->toBeFalse();
    });

    test('appliesToAffiliate returns true when affiliate_ids is null', function (): void {
        $promotion = AffiliateCommissionPromotion::create([
            'name' => 'Global Promo',
            'bonus_type' => 'percentage',
            'bonus_value' => 500,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'affiliate_ids' => null,
        ]);

        expect($promotion->appliesToAffiliate($this->affiliate))->toBeTrue();
    });

    test('appliesToAffiliate returns true when affiliate is in list', function (): void {
        $promotion = AffiliateCommissionPromotion::create([
            'name' => 'Targeted Promo',
            'bonus_type' => 'percentage',
            'bonus_value' => 500,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'affiliate_ids' => [$this->affiliate->id],
        ]);

        expect($promotion->appliesToAffiliate($this->affiliate))->toBeTrue();
    });

    test('appliesToAffiliate returns false when affiliate not in list', function (): void {
        $otherAffiliate = Affiliate::create([
            'code' => 'OTHER' . uniqid(),
            'name' => 'Other Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => 'percentage',
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $promotion = AffiliateCommissionPromotion::create([
            'name' => 'Exclusive Promo',
            'bonus_type' => 'percentage',
            'bonus_value' => 500,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'affiliate_ids' => [$this->affiliate->id],
        ]);

        expect($promotion->appliesToAffiliate($otherAffiliate))->toBeFalse();
    });

    test('appliesToAffiliate returns false when promotion not active', function (): void {
        $promotion = AffiliateCommissionPromotion::create([
            'name' => 'Inactive Promo',
            'bonus_type' => 'percentage',
            'bonus_value' => 500,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addWeek(),
        ]);

        expect($promotion->appliesToAffiliate($this->affiliate))->toBeFalse();
    });

    test('incrementUsage increases current_uses', function (): void {
        $promotion = AffiliateCommissionPromotion::create([
            'name' => 'Usage Counter Promo',
            'bonus_type' => 'percentage',
            'bonus_value' => 500,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'current_uses' => 5,
        ]);

        $promotion->incrementUsage();

        $promotion->refresh();
        expect($promotion->current_uses)->toBe(6);
    });

    test('calculateBonus returns correct value for percentage type', function (): void {
        $promotion = AffiliateCommissionPromotion::create([
            'name' => 'Percentage Promo',
            'bonus_type' => 'percentage',
            'bonus_value' => 1000, // 10% in basis points
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        // Base commission of 5000, 10% bonus = 500
        $bonus = $promotion->calculateBonus(5000);
        expect($bonus)->toBe(500);
    });

    test('calculateBonus returns correct value for flat type', function (): void {
        $promotion = AffiliateCommissionPromotion::create([
            'name' => 'Flat Promo',
            'bonus_type' => 'flat',
            'bonus_value' => 250,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $bonus = $promotion->calculateBonus(5000);
        expect($bonus)->toBe(250);
    });

    test('calculateBonus returns correct value for multiplier type', function (): void {
        $promotion = AffiliateCommissionPromotion::create([
            'name' => 'Multiplier Promo',
            'bonus_type' => 'multiplier',
            'bonus_value' => 150, // 1.5x multiplier (as 150%)
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        // Base commission of 10000, 1.5x multiplier means 0.5x extra = 5000
        $bonus = $promotion->calculateBonus(10000);
        expect($bonus)->toBe(5000);
    });

    test('calculateBonus returns zero for unknown type', function (): void {
        $promotion = AffiliateCommissionPromotion::create([
            'name' => 'Unknown Type Promo',
            'bonus_type' => 'unknown',
            'bonus_value' => 1000,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $bonus = $promotion->calculateBonus(5000);
        expect($bonus)->toBe(0);
    });

    test('scopeActive returns only active promotions', function (): void {
        AffiliateCommissionPromotion::create([
            'name' => 'Active Promo 1',
            'bonus_type' => 'percentage',
            'bonus_value' => 500,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        AffiliateCommissionPromotion::create([
            'name' => 'Future Promo',
            'bonus_type' => 'percentage',
            'bonus_value' => 500,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addWeek(),
        ]);

        AffiliateCommissionPromotion::create([
            'name' => 'Maxed Out Promo',
            'bonus_type' => 'percentage',
            'bonus_value' => 500,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'max_uses' => 10,
            'current_uses' => 10,
        ]);

        $activePromotions = AffiliateCommissionPromotion::active()->get();

        expect($activePromotions)->toHaveCount(1);
        expect($activePromotions->first()->name)->toBe('Active Promo 1');
    });

    test('can store conditions array', function (): void {
        $conditions = [
            'min_order_value' => 10000,
            'product_categories' => ['electronics', 'clothing'],
            'new_customers_only' => true,
        ];

        $promotion = AffiliateCommissionPromotion::create([
            'name' => 'Conditional Promo',
            'bonus_type' => 'percentage',
            'bonus_value' => 1000,
            'conditions' => $conditions,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        expect($promotion->conditions)->toBeArray();
        expect($promotion->conditions['min_order_value'])->toBe(10000);
        expect($promotion->conditions['product_categories'])->toContain('electronics');
        expect($promotion->conditions['new_customers_only'])->toBeTrue();
    });

    test('casts dates correctly', function (): void {
        $promotion = AffiliateCommissionPromotion::create([
            'name' => 'Date Cast Promo',
            'bonus_type' => 'percentage',
            'bonus_value' => 500,
            'starts_at' => '2024-12-01 00:00:00',
            'ends_at' => '2024-12-31 23:59:59',
        ]);

        expect($promotion->starts_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
        expect($promotion->ends_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
        expect($promotion->starts_at->format('Y-m-d'))->toBe('2024-12-01');
        expect($promotion->ends_at->format('Y-m-d'))->toBe('2024-12-31');
    });

    test('uses correct table name from config', function (): void {
        $promotion = new AffiliateCommissionPromotion;

        expect($promotion->getTable())->toBe(config('affiliates.table_names.commission_promotions', 'affiliate_commission_promotions'));
    });
});
