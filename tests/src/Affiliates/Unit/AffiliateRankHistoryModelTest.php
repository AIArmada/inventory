<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\RankQualificationReason;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateRank;
use AIArmada\Affiliates\Models\AffiliateRankHistory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

describe('AffiliateRankHistory Model', function (): void {
    it('can be created with required fields', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'RANKHIST-TEST-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $rank = AffiliateRank::create([
            'name' => 'Gold',
            'slug' => 'gold-' . uniqid(),
            'level' => rand(1, 100),
            'commission_rate_basis_points' => 1500,
        ]);

        $history = AffiliateRankHistory::create([
            'affiliate_id' => $affiliate->id,
            'from_rank_id' => null,
            'to_rank_id' => $rank->id,
            'reason' => RankQualificationReason::Initial,
            'qualified_at' => now(),
        ]);

        expect($history)->toBeInstanceOf(AffiliateRankHistory::class)
            ->and($history->affiliate_id)->toBe($affiliate->id)
            ->and($history->to_rank_id)->toBe($rank->id)
            ->and($history->reason)->toBe(RankQualificationReason::Initial);
    });

    it('belongs to an affiliate', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'RANKHIST-AFF-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $rank = AffiliateRank::create([
            'name' => 'Silver',
            'slug' => 'silver-' . uniqid(),
            'level' => rand(1, 100),
            'commission_rate_basis_points' => 1000,
        ]);

        $history = AffiliateRankHistory::create([
            'affiliate_id' => $affiliate->id,
            'to_rank_id' => $rank->id,
            'reason' => RankQualificationReason::Qualified,
            'qualified_at' => now(),
        ]);

        expect($history->affiliate())->toBeInstanceOf(BelongsTo::class)
            ->and($history->affiliate->id)->toBe($affiliate->id);
    });

    it('belongs to from rank and to rank', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'RANKHIST-RANKS-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $bronzeRank = AffiliateRank::create([
            'name' => 'Bronze',
            'slug' => 'bronze-' . uniqid(),
            'level' => rand(100, 200),
            'commission_rate_basis_points' => 500,
        ]);

        $silverRank = AffiliateRank::create([
            'name' => 'Silver',
            'slug' => 'silver-' . uniqid(),
            'level' => rand(50, 99),
            'commission_rate_basis_points' => 1000,
        ]);

        $history = AffiliateRankHistory::create([
            'affiliate_id' => $affiliate->id,
            'from_rank_id' => $bronzeRank->id,
            'to_rank_id' => $silverRank->id,
            'reason' => RankQualificationReason::Qualified,
            'qualified_at' => now(),
        ]);

        expect($history->fromRank())->toBeInstanceOf(BelongsTo::class)
            ->and($history->toRank())->toBeInstanceOf(BelongsTo::class)
            ->and($history->fromRank->id)->toBe($bronzeRank->id)
            ->and($history->toRank->id)->toBe($silverRank->id);
    });

    it('detects promotion from lower to higher rank', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'RANKHIST-PROMO-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $silverRank = AffiliateRank::create([
            'name' => 'Silver',
            'slug' => 'silver-promo-' . uniqid(),
            'level' => 2, // Lower level = higher rank
            'commission_rate_basis_points' => 1000,
        ]);

        $goldRank = AffiliateRank::create([
            'name' => 'Gold',
            'slug' => 'gold-promo-' . uniqid(),
            'level' => 1,
            'commission_rate_basis_points' => 1500,
        ]);

        $history = AffiliateRankHistory::create([
            'affiliate_id' => $affiliate->id,
            'from_rank_id' => $silverRank->id,
            'to_rank_id' => $goldRank->id,
            'reason' => RankQualificationReason::Qualified,
            'qualified_at' => now(),
        ]);

        expect($history->isPromotion())->toBeTrue()
            ->and($history->isDemotion())->toBeFalse();
    });

    it('detects demotion from higher to lower rank', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'RANKHIST-DEMO-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $goldRank = AffiliateRank::create([
            'name' => 'Gold',
            'slug' => 'gold-demo-' . uniqid(),
            'level' => 3,
            'commission_rate_basis_points' => 1500,
        ]);

        $silverRank = AffiliateRank::create([
            'name' => 'Silver',
            'slug' => 'silver-demo-' . uniqid(),
            'level' => 4,
            'commission_rate_basis_points' => 1000,
        ]);

        $history = AffiliateRankHistory::create([
            'affiliate_id' => $affiliate->id,
            'from_rank_id' => $goldRank->id,
            'to_rank_id' => $silverRank->id,
            'reason' => RankQualificationReason::Demoted,
            'qualified_at' => now(),
        ]);

        expect($history->isDemotion())->toBeTrue()
            ->and($history->isPromotion())->toBeFalse();
    });

    it('detects promotion when gaining first rank', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'RANKHIST-FIRST-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $bronzeRank = AffiliateRank::create([
            'name' => 'Bronze',
            'slug' => 'bronze-first-' . uniqid(),
            'level' => 5,
            'commission_rate_basis_points' => 500,
        ]);

        $history = AffiliateRankHistory::create([
            'affiliate_id' => $affiliate->id,
            'from_rank_id' => null,
            'to_rank_id' => $bronzeRank->id,
            'reason' => RankQualificationReason::Initial,
            'qualified_at' => now(),
        ]);

        expect($history->isPromotion())->toBeTrue()
            ->and($history->isDemotion())->toBeFalse();
    });

    it('detects demotion when losing rank', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'RANKHIST-LOSE-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $bronzeRank = AffiliateRank::create([
            'name' => 'Bronze',
            'slug' => 'bronze-lose-' . uniqid(),
            'level' => 6,
            'commission_rate_basis_points' => 500,
        ]);

        $history = AffiliateRankHistory::create([
            'affiliate_id' => $affiliate->id,
            'from_rank_id' => $bronzeRank->id,
            'to_rank_id' => null,
            'reason' => RankQualificationReason::Demoted,
            'qualified_at' => now(),
        ]);

        expect($history->isDemotion())->toBeTrue()
            ->and($history->isPromotion())->toBeFalse();
    });

    it('casts reason as enum', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'RANKHIST-ENUM-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $rank = AffiliateRank::create([
            'name' => 'Gold',
            'slug' => 'gold-enum-' . uniqid(),
            'level' => 7,
            'commission_rate_basis_points' => 1500,
        ]);

        $history = AffiliateRankHistory::create([
            'affiliate_id' => $affiliate->id,
            'to_rank_id' => $rank->id,
            'reason' => RankQualificationReason::Manual,
            'qualified_at' => now(),
        ]);

        expect($history->reason)->toBe(RankQualificationReason::Manual)
            ->and($history->reason)->toBeInstanceOf(RankQualificationReason::class);
    });

    it('casts qualified_at as datetime', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'RANKHIST-DATE-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $rank = AffiliateRank::create([
            'name' => 'Gold',
            'slug' => 'gold-date-' . uniqid(),
            'level' => 8,
            'commission_rate_basis_points' => 1500,
        ]);

        $history = AffiliateRankHistory::create([
            'affiliate_id' => $affiliate->id,
            'to_rank_id' => $rank->id,
            'reason' => RankQualificationReason::Qualified,
            'qualified_at' => '2024-01-15 10:30:00',
        ]);

        expect($history->qualified_at)->toBeInstanceOf(Illuminate\Support\Carbon::class)
            ->and($history->qualified_at->format('Y-m-d'))->toBe('2024-01-15');
    });

    it('uses correct table name from config', function (): void {
        $history = new AffiliateRankHistory;
        expect($history->getTable())->toBe('affiliate_rank_histories');
    });
});
