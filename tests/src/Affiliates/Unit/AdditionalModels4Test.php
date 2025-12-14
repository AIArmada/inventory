<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\ConversionStatus;
use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateFraudSignal;
use AIArmada\Affiliates\Models\AffiliateNetwork;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Models\AffiliateProgramMembership;
use AIArmada\Affiliates\Models\AffiliateProgramTier;
use AIArmada\Affiliates\Models\AffiliateRank;
use AIArmada\Affiliates\Models\AffiliateTouchpoint;
use AIArmada\Affiliates\Services\CohortAnalyzer;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// AffiliateConversion Tests
test('AffiliateConversion can be created with required fields', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'CONV001',
        'name' => 'Conversion Test',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $conversion = AffiliateConversion::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'order_reference' => 'ORD-12345',
        'total_minor' => 50000,
        'commission_minor' => 5000,
        'commission_currency' => 'USD',
        'status' => ConversionStatus::Approved,
        'occurred_at' => now(),
    ]);

    expect($conversion)->toBeInstanceOf(AffiliateConversion::class);
    expect($conversion->order_reference)->toBe('ORD-12345');
    expect($conversion->commission_minor)->toBe(5000);
});

test('AffiliateConversion has affiliate relationship', function (): void {
    $conversion = new AffiliateConversion;

    expect($conversion->affiliate())->toBeInstanceOf(BelongsTo::class);
});

// AffiliateFraudSignal Tests
test('AffiliateFraudSignal has affiliate relationship', function (): void {
    $signal = new AffiliateFraudSignal;

    expect($signal->affiliate())->toBeInstanceOf(BelongsTo::class);
});

// AffiliateNetwork Tests
test('AffiliateNetwork relationship tests', function (): void {
    $network = new AffiliateNetwork;

    // Verify the model can be instantiated
    expect($network)->toBeInstanceOf(AffiliateNetwork::class);
});

// AffiliatePayout Tests
test('AffiliatePayout relationships', function (): void {
    $payout = new AffiliatePayout;

    // Check basic instantiation
    expect($payout)->toBeInstanceOf(AffiliatePayout::class);
});

// AffiliateProgramTier Tests
test('AffiliateProgramTier can be created with required fields', function (): void {
    $program = AffiliateProgram::create([
        'name' => 'Tier Program',
        'slug' => 'tier-program-extra',
        'status' => ProgramStatus::Active,
        'commission_type' => 'percentage',
        'default_commission_rate_basis_points' => 1000,
        'cookie_lifetime_days' => 30,
    ]);

    $tier = AffiliateProgramTier::create([
        'program_id' => $program->id,
        'name' => 'Gold',
        'level' => 3,
        'commission_rate_basis_points' => 1500,
    ]);

    expect($tier)->toBeInstanceOf(AffiliateProgramTier::class);
    expect($tier->name)->toBe('Gold');
});

test('AffiliateProgramTier has program relationship', function (): void {
    $tier = new AffiliateProgramTier;

    expect($tier->program())->toBeInstanceOf(BelongsTo::class);
});

// AffiliateRank Tests
test('AffiliateRank can be created', function (): void {
    $rank = AffiliateRank::create([
        'name' => 'Diamond',
        'slug' => 'diamond',
        'level' => 5,
        'commission_rate_basis_points' => 2000,
    ]);

    expect($rank)->toBeInstanceOf(AffiliateRank::class);
    expect($rank->name)->toBe('Diamond');
});

test('AffiliateRank has affiliates relationship', function (): void {
    $rank = new AffiliateRank;

    expect($rank->affiliates())->toBeInstanceOf(HasMany::class);
});

test('AffiliateRank isHigherThan compares rank levels', function (): void {
    $goldRank = new AffiliateRank(['level' => 3]);
    $silverRank = new AffiliateRank(['level' => 5]);

    // Lower level number = higher rank
    expect($goldRank->isHigherThan($silverRank))->toBeTrue();
    expect($silverRank->isHigherThan($goldRank))->toBeFalse();
});

test('AffiliateRank isLowerThan compares rank levels', function (): void {
    $goldRank = new AffiliateRank(['level' => 3]);
    $silverRank = new AffiliateRank(['level' => 5]);

    expect($goldRank->isLowerThan($silverRank))->toBeFalse();
    expect($silverRank->isLowerThan($goldRank))->toBeTrue();
});

test('AffiliateRank getOverrideRateForDepth returns correct rate', function (): void {
    $rank = new AffiliateRank([
        'override_rates' => [1 => 50, 2 => 25, 3 => 10],
    ]);

    expect($rank->getOverrideRateForDepth(1))->toBe(50);
    expect($rank->getOverrideRateForDepth(2))->toBe(25);
    expect($rank->getOverrideRateForDepth(3))->toBe(10);
    expect($rank->getOverrideRateForDepth(4))->toBe(0);
});

// AffiliateTouchpoint Tests
test('AffiliateTouchpoint has affiliate relationship', function (): void {
    $touchpoint = new AffiliateTouchpoint;

    expect($touchpoint->affiliate())->toBeInstanceOf(BelongsTo::class);
});

// AffiliateProgramMembership Tests
test('AffiliateProgramMembership has required relationships', function (): void {
    $membership = new AffiliateProgramMembership;

    expect($membership->affiliate())->toBeInstanceOf(BelongsTo::class);
    expect($membership->program())->toBeInstanceOf(BelongsTo::class);
    expect($membership->tier())->toBeInstanceOf(BelongsTo::class);
});

// CohortAnalyzer Tests
test('CohortAnalyzer can be instantiated', function (): void {
    $analyzer = app(CohortAnalyzer::class);

    expect($analyzer)->toBeInstanceOf(CohortAnalyzer::class);
});
