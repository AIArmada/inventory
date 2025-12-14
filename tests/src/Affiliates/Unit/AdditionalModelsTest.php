<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateBalance;
use AIArmada\Affiliates\Models\AffiliateLink;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Models\AffiliateProgramMembership;
use AIArmada\Affiliates\Models\AffiliateProgramTier;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

// AffiliateBalance Tests
test('AffiliateBalance can be created with required fields', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'BAL001',
        'name' => 'Balance Test Affiliate',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $balance = AffiliateBalance::create([
        'affiliate_id' => $affiliate->id,
        'currency' => 'USD',
        'holding_minor' => 5000,
        'available_minor' => 10000,
        'lifetime_earnings_minor' => 50000,
        'minimum_payout_minor' => 2500,
    ]);

    expect($balance)->toBeInstanceOf(AffiliateBalance::class);
    expect($balance->holding_minor)->toBe(5000);
    expect($balance->available_minor)->toBe(10000);
});

test('AffiliateBalance has affiliate relationship', function (): void {
    $balance = new AffiliateBalance;

    expect($balance->affiliate())->toBeInstanceOf(BelongsTo::class);
});

test('AffiliateBalance getTotalBalanceMinor calculates correctly', function (): void {
    $balance = new AffiliateBalance([
        'holding_minor' => 5000,
        'available_minor' => 10000,
    ]);

    expect($balance->getTotalBalanceMinor())->toBe(15000);
});

test('AffiliateBalance canRequestPayout returns true when available exceeds minimum', function (): void {
    $balance = new AffiliateBalance([
        'available_minor' => 10000,
        'minimum_payout_minor' => 5000,
    ]);

    expect($balance->canRequestPayout())->toBeTrue();
});

test('AffiliateBalance canRequestPayout returns false when below minimum', function (): void {
    $balance = new AffiliateBalance([
        'available_minor' => 2000,
        'minimum_payout_minor' => 5000,
    ]);

    expect($balance->canRequestPayout())->toBeFalse();
});

test('AffiliateBalance addToHolding increments holding and lifetime earnings', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'BAL002',
        'name' => 'Add Holding Test',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $balance = AffiliateBalance::create([
        'affiliate_id' => $affiliate->id,
        'currency' => 'USD',
        'holding_minor' => 1000,
        'available_minor' => 0,
        'lifetime_earnings_minor' => 1000,
        'minimum_payout_minor' => 2500,
    ]);

    $balance->addToHolding(500);

    expect($balance->fresh()->holding_minor)->toBe(1500);
    expect($balance->fresh()->lifetime_earnings_minor)->toBe(1500);
});

test('AffiliateBalance releaseFromHolding moves funds to available', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'BAL003',
        'name' => 'Release Holding Test',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $balance = AffiliateBalance::create([
        'affiliate_id' => $affiliate->id,
        'currency' => 'USD',
        'holding_minor' => 5000,
        'available_minor' => 0,
        'lifetime_earnings_minor' => 5000,
        'minimum_payout_minor' => 2500,
    ]);

    $balance->releaseFromHolding(3000);

    expect($balance->fresh()->holding_minor)->toBe(2000);
    expect($balance->fresh()->available_minor)->toBe(3000);
});

test('AffiliateBalance releaseFromHolding does not release more than available in holding', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'BAL004',
        'name' => 'Limit Release Test',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $balance = AffiliateBalance::create([
        'affiliate_id' => $affiliate->id,
        'currency' => 'USD',
        'holding_minor' => 2000,
        'available_minor' => 0,
        'lifetime_earnings_minor' => 2000,
        'minimum_payout_minor' => 2500,
    ]);

    $balance->releaseFromHolding(5000);

    expect($balance->fresh()->holding_minor)->toBe(0);
    expect($balance->fresh()->available_minor)->toBe(2000);
});

test('AffiliateBalance deductFromAvailable decrements available', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'BAL005',
        'name' => 'Deduct Test',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $balance = AffiliateBalance::create([
        'affiliate_id' => $affiliate->id,
        'currency' => 'USD',
        'holding_minor' => 0,
        'available_minor' => 10000,
        'lifetime_earnings_minor' => 10000,
        'minimum_payout_minor' => 2500,
    ]);

    $balance->deductFromAvailable(3000);

    expect($balance->fresh()->available_minor)->toBe(7000);
});

test('AffiliateBalance formatHolding returns formatted string', function (): void {
    $balance = new AffiliateBalance([
        'holding_minor' => 12345,
    ]);

    expect($balance->formatHolding())->toBe('123.45');
});

test('AffiliateBalance formatAvailable returns formatted string', function (): void {
    $balance = new AffiliateBalance([
        'available_minor' => 50000,
    ]);

    expect($balance->formatAvailable())->toBe('500.00');
});

test('AffiliateBalance formatLifetimeEarnings returns formatted string', function (): void {
    $balance = new AffiliateBalance([
        'lifetime_earnings_minor' => 100000,
    ]);

    expect($balance->formatLifetimeEarnings())->toBe('1,000.00');
});

// AffiliateLink Tests
test('AffiliateLink can be created with required fields', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'LINK001',
        'name' => 'Link Test Affiliate',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $link = AffiliateLink::create([
        'affiliate_id' => $affiliate->id,
        'destination_url' => 'https://example.com/product',
        'tracking_url' => 'https://track.example.com/a/LINK001',
        'is_active' => true,
    ]);

    expect($link)->toBeInstanceOf(AffiliateLink::class);
    expect($link->destination_url)->toBe('https://example.com/product');
});

test('AffiliateLink has affiliate relationship', function (): void {
    $link = new AffiliateLink;

    expect($link->affiliate())->toBeInstanceOf(BelongsTo::class);
});

test('AffiliateLink has program relationship', function (): void {
    $link = new AffiliateLink;

    expect($link->program())->toBeInstanceOf(BelongsTo::class);
});

test('AffiliateLink incrementClicks increments click count', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'LINK002',
        'name' => 'Click Test Affiliate',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $link = AffiliateLink::create([
        'affiliate_id' => $affiliate->id,
        'destination_url' => 'https://example.com',
        'tracking_url' => 'https://track.example.com',
        'clicks' => 10,
        'conversions' => 0,
        'is_active' => true,
    ]);

    $link->incrementClicks();

    expect($link->fresh()->clicks)->toBe(11);
});

test('AffiliateLink incrementConversions increments conversion count', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'LINK003',
        'name' => 'Conversion Test Affiliate',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $link = AffiliateLink::create([
        'affiliate_id' => $affiliate->id,
        'destination_url' => 'https://example.com',
        'tracking_url' => 'https://track.example.com',
        'clicks' => 100,
        'conversions' => 5,
        'is_active' => true,
    ]);

    $link->incrementConversions();

    expect($link->fresh()->conversions)->toBe(6);
});

test('AffiliateLink getConversionRate calculates correctly', function (): void {
    $link = new AffiliateLink([
        'clicks' => 100,
        'conversions' => 5,
    ]);

    expect($link->getConversionRate())->toBe(5.0);
});

test('AffiliateLink getConversionRate returns zero when no clicks', function (): void {
    $link = new AffiliateLink([
        'clicks' => 0,
        'conversions' => 0,
    ]);

    expect($link->getConversionRate())->toBe(0.0);
});

test('AffiliateLink getDisplayUrl returns short_url when available', function (): void {
    $link = new AffiliateLink([
        'tracking_url' => 'https://track.example.com/long-url',
        'short_url' => 'https://t.co/short',
    ]);

    expect($link->getDisplayUrl())->toBe('https://t.co/short');
});

test('AffiliateLink getDisplayUrl returns tracking_url when no short_url', function (): void {
    $link = new AffiliateLink([
        'tracking_url' => 'https://track.example.com/long-url',
        'short_url' => null,
    ]);

    expect($link->getDisplayUrl())->toBe('https://track.example.com/long-url');
});

// AffiliateProgram Tests
test('AffiliateProgram can be created with required fields', function (): void {
    $program = AffiliateProgram::create([
        'name' => 'Test Program',
        'slug' => 'test-program',
        'status' => ProgramStatus::Active,
        'commission_type' => 'percentage',
        'default_commission_rate_basis_points' => 1000,
        'cookie_lifetime_days' => 30,
    ]);

    expect($program)->toBeInstanceOf(AffiliateProgram::class);
    expect($program->name)->toBe('Test Program');
});

test('AffiliateProgram has tiers relationship', function (): void {
    $program = new AffiliateProgram;

    expect($program->tiers())->toBeInstanceOf(HasMany::class);
});

test('AffiliateProgram has affiliates relationship', function (): void {
    $program = new AffiliateProgram;

    expect($program->affiliates())->toBeInstanceOf(BelongsToMany::class);
});

test('AffiliateProgram has creatives relationship', function (): void {
    $program = new AffiliateProgram;

    expect($program->creatives())->toBeInstanceOf(HasMany::class);
});

test('AffiliateProgram has memberships relationship', function (): void {
    $program = new AffiliateProgram;

    expect($program->memberships())->toBeInstanceOf(HasMany::class);
});

test('AffiliateProgram isActive returns true for active program within date range', function (): void {
    $program = new AffiliateProgram([
        'status' => ProgramStatus::Active,
        'starts_at' => now()->subDays(10),
        'ends_at' => now()->addDays(10),
    ]);

    expect($program->isActive())->toBeTrue();
});

test('AffiliateProgram isActive returns false for non-active status', function (): void {
    $program = new AffiliateProgram([
        'status' => ProgramStatus::Draft,
    ]);

    expect($program->isActive())->toBeFalse();
});

test('AffiliateProgram isActive returns false when starts_at is in future', function (): void {
    $program = new AffiliateProgram([
        'status' => ProgramStatus::Active,
        'starts_at' => now()->addDays(10),
    ]);

    expect($program->isActive())->toBeFalse();
});

test('AffiliateProgram isActive returns false when ends_at is in past', function (): void {
    $program = new AffiliateProgram([
        'status' => ProgramStatus::Active,
        'ends_at' => now()->subDays(10),
    ]);

    expect($program->isActive())->toBeFalse();
});

test('AffiliateProgram isOpen returns true when active and public', function (): void {
    $program = new AffiliateProgram([
        'status' => ProgramStatus::Active,
        'is_public' => true,
    ]);

    expect($program->isOpen())->toBeTrue();
});

test('AffiliateProgram isOpen returns false when not public', function (): void {
    $program = new AffiliateProgram([
        'status' => ProgramStatus::Active,
        'is_public' => false,
    ]);

    expect($program->isOpen())->toBeFalse();
});

test('AffiliateProgram generates slug on creation', function (): void {
    $program = AffiliateProgram::create([
        'name' => 'My New Program',
        'status' => ProgramStatus::Active,
        'commission_type' => 'percentage',
        'default_commission_rate_basis_points' => 1000,
        'cookie_lifetime_days' => 30,
    ]);

    expect($program->slug)->toBe('my-new-program');
});

test('AffiliateProgram scopeActive filters correctly', function (): void {
    AffiliateProgram::create([
        'name' => 'Active Program',
        'slug' => 'active-program',
        'status' => ProgramStatus::Active,
        'commission_type' => 'percentage',
        'default_commission_rate_basis_points' => 1000,
        'cookie_lifetime_days' => 30,
    ]);

    AffiliateProgram::create([
        'name' => 'Draft Program',
        'slug' => 'draft-program',
        'status' => ProgramStatus::Draft,
        'commission_type' => 'percentage',
        'default_commission_rate_basis_points' => 1000,
        'cookie_lifetime_days' => 30,
    ]);

    $activePrograms = AffiliateProgram::active()->get();

    expect($activePrograms->pluck('slug'))->toContain('active-program');
    expect($activePrograms->pluck('slug'))->not->toContain('draft-program');
});

test('AffiliateProgram scopePublic filters correctly', function (): void {
    AffiliateProgram::create([
        'name' => 'Public Program',
        'slug' => 'public-program',
        'status' => ProgramStatus::Active,
        'is_public' => true,
        'commission_type' => 'percentage',
        'default_commission_rate_basis_points' => 1000,
        'cookie_lifetime_days' => 30,
    ]);

    AffiliateProgram::create([
        'name' => 'Private Program',
        'slug' => 'private-program',
        'status' => ProgramStatus::Active,
        'is_public' => false,
        'commission_type' => 'percentage',
        'default_commission_rate_basis_points' => 1000,
        'cookie_lifetime_days' => 30,
    ]);

    $publicPrograms = AffiliateProgram::public()->get();

    expect($publicPrograms->pluck('slug'))->toContain('public-program');
    expect($publicPrograms->pluck('slug'))->not->toContain('private-program');
});

// AffiliateProgramMembership Tests
test('AffiliateProgramMembership can be created', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'MEMB001',
        'name' => 'Membership Test Affiliate',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $program = AffiliateProgram::create([
        'name' => 'Membership Test Program',
        'slug' => 'membership-test-program',
        'status' => ProgramStatus::Active,
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

    expect($membership)->toBeInstanceOf(AffiliateProgramMembership::class);
    expect($membership->status->value)->toBe('approved');
});

// AffiliateProgramTier Tests
test('AffiliateProgramTier can be created', function (): void {
    $program = AffiliateProgram::create([
        'name' => 'Tier Test Program',
        'slug' => 'tier-test-program',
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
    expect($tier->level)->toBe(3);
});
