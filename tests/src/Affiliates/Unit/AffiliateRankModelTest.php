<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateRank;
use Illuminate\Database\Eloquent\Relations\HasMany;

describe('AffiliateRank Model', function (): void {
    it('can be created with required fields', function (): void {
        $rank = AffiliateRank::create([
            'name' => 'Gold',
            'slug' => 'gold',
            'level' => 2,
            'commission_rate_basis_points' => 1500,
        ]);

        expect($rank)->toBeInstanceOf(AffiliateRank::class)
            ->and($rank->name)->toBe('Gold')
            ->and($rank->slug)->toBe('gold')
            ->and($rank->level)->toBe(2)
            ->and($rank->commission_rate_basis_points)->toBe(1500);
    });

    it('has many affiliates', function (): void {
        $rank = AffiliateRank::create([
            'name' => 'Silver',
            'slug' => 'silver',
            'level' => 3,
            'commission_rate_basis_points' => 1000,
        ]);

        expect($rank->affiliates())->toBeInstanceOf(HasMany::class);
    });

    it('relates to affiliates correctly', function (): void {
        $rank = AffiliateRank::create([
            'name' => 'Gold',
            'slug' => 'gold',
            'level' => 2,
            'commission_rate_basis_points' => 1500,
        ]);

        Affiliate::create([
            'code' => 'RANK-TEST-' . uniqid(),
            'name' => 'Ranked Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
            'rank_id' => $rank->id,
        ]);

        $rank->refresh();
        expect($rank->affiliates)->toHaveCount(1);
    });

    it('determines if rank is higher than another', function (): void {
        $goldRank = AffiliateRank::create([
            'name' => 'Gold',
            'slug' => 'gold',
            'level' => 1, // Lower level = higher rank
            'commission_rate_basis_points' => 2000,
        ]);

        $silverRank = AffiliateRank::create([
            'name' => 'Silver',
            'slug' => 'silver',
            'level' => 2,
            'commission_rate_basis_points' => 1500,
        ]);

        expect($goldRank->isHigherThan($silverRank))->toBeTrue()
            ->and($silverRank->isHigherThan($goldRank))->toBeFalse();
    });

    it('determines if rank is lower than another', function (): void {
        $goldRank = AffiliateRank::create([
            'name' => 'Gold',
            'slug' => 'gold',
            'level' => 1,
            'commission_rate_basis_points' => 2000,
        ]);

        $silverRank = AffiliateRank::create([
            'name' => 'Silver',
            'slug' => 'silver',
            'level' => 2,
            'commission_rate_basis_points' => 1500,
        ]);

        expect($silverRank->isLowerThan($goldRank))->toBeTrue()
            ->and($goldRank->isLowerThan($silverRank))->toBeFalse();
    });

    it('returns equal for same level ranks', function (): void {
        // Level is unique, so we compare ranks by testing isHigherThan/isLowerThan
        // Both should return false for the same rank
        $rank = AffiliateRank::create([
            'name' => 'Gold',
            'slug' => 'gold',
            'level' => 2,
            'commission_rate_basis_points' => 1500,
        ]);

        expect($rank->isHigherThan($rank))->toBeFalse()
            ->and($rank->isLowerThan($rank))->toBeFalse();
    });

    it('gets override rate for specific depth', function (): void {
        $rank = AffiliateRank::create([
            'name' => 'Gold',
            'slug' => 'gold',
            'level' => 2,
            'commission_rate_basis_points' => 1500,
            'override_rates' => [
                1 => 500,
                2 => 300,
                3 => 100,
            ],
        ]);

        expect($rank->getOverrideRateForDepth(1))->toBe(500)
            ->and($rank->getOverrideRateForDepth(2))->toBe(300)
            ->and($rank->getOverrideRateForDepth(3))->toBe(100);
    });

    it('returns zero for undefined override depth', function (): void {
        $rank = AffiliateRank::create([
            'name' => 'Gold',
            'slug' => 'gold',
            'level' => 2,
            'commission_rate_basis_points' => 1500,
            'override_rates' => [
                1 => 500,
            ],
        ]);

        expect($rank->getOverrideRateForDepth(5))->toBe(0);
    });

    it('returns zero override when override_rates is null', function (): void {
        $rank = AffiliateRank::create([
            'name' => 'Gold',
            'slug' => 'gold',
            'level' => 2,
            'commission_rate_basis_points' => 1500,
        ]);

        expect($rank->getOverrideRateForDepth(1))->toBe(0);
    });

    it('checks qualification requirements - passes all', function (): void {
        $rank = AffiliateRank::create([
            'name' => 'Gold',
            'slug' => 'gold',
            'level' => 2,
            'commission_rate_basis_points' => 1500,
            'min_personal_sales' => 10000,
            'min_team_sales' => 50000,
            'min_active_downlines' => 5,
        ]);

        $affiliate = Affiliate::create([
            'code' => 'QUAL-TEST-' . uniqid(),
            'name' => 'Qualification Test',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        expect($rank->meetsQualification($affiliate, 15000, 60000, 10))->toBeTrue();
    });

    it('checks qualification requirements - fails personal sales', function (): void {
        $rank = AffiliateRank::create([
            'name' => 'Gold',
            'slug' => 'gold',
            'level' => 2,
            'commission_rate_basis_points' => 1500,
            'min_personal_sales' => 10000,
            'min_team_sales' => 50000,
            'min_active_downlines' => 5,
        ]);

        $affiliate = Affiliate::create([
            'code' => 'QUAL-FAIL-' . uniqid(),
            'name' => 'Qualification Fail Test',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        expect($rank->meetsQualification($affiliate, 5000, 60000, 10))->toBeFalse();
    });

    it('checks qualification requirements - fails team sales', function (): void {
        $rank = AffiliateRank::create([
            'name' => 'Gold',
            'slug' => 'gold',
            'level' => 2,
            'commission_rate_basis_points' => 1500,
            'min_personal_sales' => 10000,
            'min_team_sales' => 50000,
            'min_active_downlines' => 5,
        ]);

        $affiliate = Affiliate::create([
            'code' => 'QUAL-FAIL-' . uniqid(),
            'name' => 'Qualification Fail Test',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        expect($rank->meetsQualification($affiliate, 15000, 30000, 10))->toBeFalse();
    });

    it('checks qualification requirements - fails active downlines', function (): void {
        $rank = AffiliateRank::create([
            'name' => 'Gold',
            'slug' => 'gold',
            'level' => 2,
            'commission_rate_basis_points' => 1500,
            'min_personal_sales' => 10000,
            'min_team_sales' => 50000,
            'min_active_downlines' => 5,
        ]);

        $affiliate = Affiliate::create([
            'code' => 'QUAL-FAIL-' . uniqid(),
            'name' => 'Qualification Fail Test',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        expect($rank->meetsQualification($affiliate, 15000, 60000, 3))->toBeFalse();
    });

    it('passes qualification with exact minimum values', function (): void {
        $rank = AffiliateRank::create([
            'name' => 'Gold',
            'slug' => 'gold',
            'level' => 2,
            'commission_rate_basis_points' => 1500,
            'min_personal_sales' => 10000,
            'min_team_sales' => 50000,
            'min_active_downlines' => 5,
        ]);

        $affiliate = Affiliate::create([
            'code' => 'QUAL-EXACT-' . uniqid(),
            'name' => 'Qualification Exact Test',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        expect($rank->meetsQualification($affiliate, 10000, 50000, 5))->toBeTrue();
    });

    it('casts numeric fields as integers', function (): void {
        $rank = AffiliateRank::create([
            'name' => 'Gold',
            'slug' => 'gold',
            'level' => '2',
            'min_personal_sales' => '10000',
            'min_team_sales' => '50000',
            'min_active_downlines' => '5',
            'commission_rate_basis_points' => '1500',
        ]);

        expect($rank->level)->toBeInt()
            ->and($rank->min_personal_sales)->toBeInt()
            ->and($rank->min_team_sales)->toBeInt()
            ->and($rank->min_active_downlines)->toBeInt()
            ->and($rank->commission_rate_basis_points)->toBeInt();
    });

    it('casts array fields correctly', function (): void {
        $rank = AffiliateRank::create([
            'name' => 'Gold',
            'slug' => 'gold',
            'level' => 2,
            'commission_rate_basis_points' => 1500,
            'override_rates' => [1 => 500, 2 => 300],
            'benefits' => ['free_shipping' => true, 'exclusive_products' => true],
            'metadata' => ['custom' => 'data'],
        ]);

        expect($rank->override_rates)->toBeArray()
            ->and($rank->benefits)->toBeArray()
            ->and($rank->metadata)->toBeArray();
    });

    it('uses correct table name from config', function (): void {
        $rank = new AffiliateRank;

        expect($rank->getTable())->toBe('affiliate_ranks');
    });
});
