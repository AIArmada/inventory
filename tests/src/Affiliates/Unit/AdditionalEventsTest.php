<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Events\AffiliateActivated;
use AIArmada\Affiliates\Events\AffiliateProgramJoined;
use AIArmada\Affiliates\Events\AffiliateProgramLeft;
use AIArmada\Affiliates\Events\AffiliateRankChanged;
use AIArmada\Affiliates\Events\AffiliateTierUpgraded;
use AIArmada\Affiliates\Events\DailyStatsAggregated;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Models\AffiliateProgramMembership;
use AIArmada\Affiliates\Models\AffiliateProgramTier;
use AIArmada\Affiliates\Models\AffiliateRank;
use AIArmada\Affiliates\Enums\RankQualificationReason;
use Illuminate\Support\Carbon;

test('AffiliateActivated event can be constructed with an affiliate', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'TEST001',
        'name' => 'Test Affiliate',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $event = new AffiliateActivated($affiliate);

    expect($event->affiliate)->toBe($affiliate);
    expect($event->affiliate->code)->toBe('TEST001');
});

test('AffiliateActivated event uses Dispatchable trait', function (): void {
    expect(in_array(\Illuminate\Foundation\Events\Dispatchable::class, class_uses_recursive(AffiliateActivated::class)))->toBeTrue();
});

test('AffiliateActivated event uses SerializesModels trait', function (): void {
    expect(in_array(\Illuminate\Queue\SerializesModels::class, class_uses_recursive(AffiliateActivated::class)))->toBeTrue();
});

test('AffiliateProgramJoined event can be constructed with affiliate, program and membership', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'PROG001',
        'name' => 'Program Test Affiliate',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $program = AffiliateProgram::create([
        'name' => 'Test Program',
        'slug' => 'test-program',
        'status' => 'active',
        'commission_type' => 'percentage',
        'default_commission_rate_basis_points' => 1000,
        'cookie_lifetime_days' => 30,
    ]);

    $membership = AffiliateProgramMembership::create([
        'affiliate_id' => $affiliate->id,
        'program_id' => $program->id,
        'status' => 'approved',
        'applied_at' => now(),
    ]);

    $event = new AffiliateProgramJoined($affiliate, $program, $membership);

    expect($event->affiliate)->toBe($affiliate);
    expect($event->program)->toBe($program);
    expect($event->membership)->toBe($membership);
});

test('AffiliateProgramLeft event can be constructed with affiliate and program', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'LEFT001',
        'name' => 'Leaving Affiliate',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $program = AffiliateProgram::create([
        'name' => 'Test Program 2',
        'slug' => 'test-program-2',
        'status' => 'active',
        'commission_type' => 'percentage',
        'default_commission_rate_basis_points' => 1000,
        'cookie_lifetime_days' => 30,
    ]);

    $event = new AffiliateProgramLeft($affiliate, $program);

    expect($event->affiliate)->toBe($affiliate);
    expect($event->program)->toBe($program);
});

test('AffiliateTierUpgraded event can be constructed with all parameters', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'TIER001',
        'name' => 'Tier Test Affiliate',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $program = AffiliateProgram::create([
        'name' => 'Tier Program',
        'slug' => 'tier-program',
        'status' => 'active',
        'commission_type' => 'percentage',
        'default_commission_rate_basis_points' => 1000,
        'cookie_lifetime_days' => 30,
    ]);

    $fromTier = AffiliateProgramTier::create([
        'program_id' => $program->id,
        'name' => 'Bronze',
        'level' => 1,
        'commission_rate_basis_points' => 500,
    ]);

    $toTier = AffiliateProgramTier::create([
        'program_id' => $program->id,
        'name' => 'Silver',
        'level' => 2,
        'commission_rate_basis_points' => 1000,
    ]);

    $event = new AffiliateTierUpgraded($affiliate, $program, $fromTier, $toTier);

    expect($event->affiliate)->toBe($affiliate);
    expect($event->program)->toBe($program);
    expect($event->fromTier)->toBe($fromTier);
    expect($event->toTier)->toBe($toTier);
});

test('AffiliateTierUpgraded event allows null fromTier for initial tier assignment', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'TIER002',
        'name' => 'Initial Tier Affiliate',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $program = AffiliateProgram::create([
        'name' => 'Initial Tier Program',
        'slug' => 'initial-tier-program',
        'status' => 'active',
        'commission_type' => 'percentage',
        'default_commission_rate_basis_points' => 1000,
        'cookie_lifetime_days' => 30,
    ]);

    $toTier = AffiliateProgramTier::create([
        'program_id' => $program->id,
        'name' => 'Bronze',
        'level' => 1,
        'commission_rate_basis_points' => 500,
    ]);

    $event = new AffiliateTierUpgraded($affiliate, $program, null, $toTier);

    expect($event->fromTier)->toBeNull();
    expect($event->toTier)->toBe($toTier);
});

test('DailyStatsAggregated event can be constructed with date and count', function (): void {
    $date = Carbon::parse('2024-01-15');

    $event = new DailyStatsAggregated($date, 150);

    expect($event->date)->toBe($date);
    expect($event->affiliateCount)->toBe(150);
});

test('DailyStatsAggregated event uses Dispatchable and SerializesModels traits', function (): void {
    $traits = class_uses_recursive(DailyStatsAggregated::class);

    expect(in_array(\Illuminate\Foundation\Events\Dispatchable::class, $traits))->toBeTrue();
    expect(in_array(\Illuminate\Queue\SerializesModels::class, $traits))->toBeTrue();
});

test('AffiliateRankChanged event can be constructed with all parameters', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'RANK001',
        'name' => 'Rank Test Affiliate',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $fromRank = AffiliateRank::create([
        'name' => 'Bronze',
        'slug' => 'bronze',
        'level' => 1,
        'commission_rate_basis_points' => 0,
    ]);

    $toRank = AffiliateRank::create([
        'name' => 'Silver',
        'slug' => 'silver',
        'level' => 2,
        'commission_rate_basis_points' => 100,
    ]);

    $event = new AffiliateRankChanged(
        affiliate: $affiliate,
        fromRank: $fromRank,
        toRank: $toRank,
        reason: RankQualificationReason::Qualified
    );

    expect($event->affiliate)->toBe($affiliate);
    expect($event->fromRank)->toBe($fromRank);
    expect($event->toRank)->toBe($toRank);
    expect($event->reason)->toBe(RankQualificationReason::Qualified);
});

test('AffiliateRankChanged event allows null fromRank for initial rank', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'RANK002',
        'name' => 'Initial Rank Affiliate',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $toRank = AffiliateRank::create([
        'name' => 'Entry',
        'slug' => 'entry',
        'level' => 0,
        'commission_rate_basis_points' => 0,
    ]);

    $event = new AffiliateRankChanged(
        affiliate: $affiliate,
        fromRank: null,
        toRank: $toRank,
        reason: RankQualificationReason::Initial
    );

    expect($event->fromRank)->toBeNull();
    expect($event->toRank)->toBe($toRank);
    expect($event->reason)->toBe(RankQualificationReason::Initial);
});

test('AffiliateRankChanged event methods work correctly', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'RANK003',
        'name' => 'Method Test Affiliate',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $fromRank = AffiliateRank::create([
        'name' => 'Silver',
        'slug' => 'silver-2',
        'level' => 2,
        'commission_rate_basis_points' => 100,
    ]);

    $toRank = AffiliateRank::create([
        'name' => 'Gold',
        'slug' => 'gold',
        'level' => 3,
        'commission_rate_basis_points' => 200,
    ]);

    $event = new AffiliateRankChanged(
        affiliate: $affiliate,
        fromRank: $fromRank,
        toRank: $toRank,
        reason: RankQualificationReason::Qualified
    );

    // Test isPromotion - lower level number means higher rank
    expect($event->isPromotion())->toBeFalse();

    // Test isDemotion - toRank has higher level number (lower rank)
    expect($event->isDemotion())->toBeTrue();
});

test('AffiliateRankChanged event detects demotion correctly', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'RANK004',
        'name' => 'Demotion Test Affiliate',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $fromRank = AffiliateRank::create([
        'name' => 'Gold',
        'slug' => 'gold-2',
        'level' => 3,
        'commission_rate_basis_points' => 200,
    ]);

    $toRank = AffiliateRank::create([
        'name' => 'Silver',
        'slug' => 'silver-3',
        'level' => 2,
        'commission_rate_basis_points' => 100,
    ]);

    $event = new AffiliateRankChanged(
        affiliate: $affiliate,
        fromRank: $fromRank,
        toRank: $toRank,
        reason: RankQualificationReason::Demoted
    );

    expect($event->isPromotion())->toBeTrue();
    expect($event->isDemotion())->toBeFalse();
});
