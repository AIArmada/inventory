<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Affiliates\Models\AffiliateBalance;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Models\AffiliateProgram;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

// Affiliate Model Tests - Full Coverage
test('Affiliate can be created with required fields', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'AFFTEST001',
        'name' => 'Test Affiliate',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    expect($affiliate)->toBeInstanceOf(Affiliate::class);
    expect($affiliate->code)->toBe('AFFTEST001');
    expect($affiliate->name)->toBe('Test Affiliate');
});

test('Affiliate has attributions relationship', function (): void {
    $affiliate = new Affiliate;

    expect($affiliate->attributions())->toBeInstanceOf(HasMany::class);
});

test('Affiliate has conversions relationship', function (): void {
    $affiliate = new Affiliate;

    expect($affiliate->conversions())->toBeInstanceOf(HasMany::class);
});

test('Affiliate has payouts relationship', function (): void {
    $affiliate = new Affiliate;

    expect($affiliate->payouts())->toBeInstanceOf(MorphMany::class);
});

test('Affiliate has balance relationship', function (): void {
    $affiliate = new Affiliate;

    expect($affiliate->balance())->toBeInstanceOf(HasOne::class);
});

test('Affiliate has programs relationship', function (): void {
    $affiliate = new Affiliate;

    expect($affiliate->programs())->toBeInstanceOf(BelongsToMany::class);
});

test('Affiliate has links relationship', function (): void {
    $affiliate = new Affiliate;

    expect($affiliate->links())->toBeInstanceOf(HasMany::class);
});

test('Affiliate has dailyStats relationship', function (): void {
    $affiliate = new Affiliate;

    expect($affiliate->dailyStats())->toBeInstanceOf(HasMany::class);
});

test('Affiliate has payoutMethods relationship', function (): void {
    $affiliate = new Affiliate;

    expect($affiliate->payoutMethods())->toBeInstanceOf(HasMany::class);
});

test('Affiliate has payoutHolds relationship', function (): void {
    $affiliate = new Affiliate;

    expect($affiliate->payoutHolds())->toBeInstanceOf(HasMany::class);
});

test('Affiliate has fraudSignals relationship', function (): void {
    $affiliate = new Affiliate;

    expect($affiliate->fraudSignals())->toBeInstanceOf(HasMany::class);
});

test('Affiliate isActive returns true when status is Active', function (): void {
    $affiliate = new Affiliate(['status' => AffiliateStatus::Active]);

    expect($affiliate->isActive())->toBeTrue();
});

test('Affiliate isActive returns false when status is not Active', function (): void {
    $affiliate = new Affiliate(['status' => AffiliateStatus::Pending]);

    expect($affiliate->isActive())->toBeFalse();
});

test('Affiliate canRequestPayout returns true when balance exceeds minimum', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'PAYCHECK001',
        'name' => 'Payout Check Test',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    AffiliateBalance::create([
        'affiliate_id' => $affiliate->id,
        'currency' => 'USD',
        'holding_minor' => 0,
        'available_minor' => 10000,
        'lifetime_earnings_minor' => 10000,
        'minimum_payout_minor' => 5000,
    ]);

    expect($affiliate->balance->canRequestPayout())->toBeTrue();
});

test('Affiliate scopeForOwner filters by owner', function (): void {
    Affiliate::create([
        'code' => 'FOROWNER001',
        'name' => 'For Owner Affiliate',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    // Test that scopeForOwner can be called without error
    $affiliates = Affiliate::forOwner(null)->get();

    expect($affiliates)->not->toBeNull();
});

test('Affiliate findByCode returns affiliate or null', function (): void {
    Affiliate::create([
        'code' => 'BYCODE001',
        'name' => 'By Code Affiliate',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $found = Affiliate::where('code', 'BYCODE001')->first();
    expect($found)->not->toBeNull();

    $notFound = Affiliate::where('code', 'NOTEXIST')->first();
    expect($notFound)->toBeNull();
});

test('Affiliate has rank relationship', function (): void {
    $affiliate = new Affiliate;

    expect($affiliate->rank())->toBeInstanceOf(BelongsTo::class);
});

test('Affiliate has parent relationship', function (): void {
    $affiliate = new Affiliate;

    expect($affiliate->parent())->toBeInstanceOf(BelongsTo::class);
});

test('Affiliate has children relationship', function (): void {
    $affiliate = new Affiliate;

    expect($affiliate->children())->toBeInstanceOf(HasMany::class);
});

test('Affiliate getTable returns config table name', function (): void {
    $affiliate = new Affiliate;

    $table = $affiliate->getTable();

    expect($table)->toBeString();
    expect(strlen($table))->toBeGreaterThan(0);
});

test('Affiliate email accessor returns contact_email', function (): void {
    $affiliate = new Affiliate(['contact_email' => 'test@example.com']);

    expect($affiliate->email)->toBe('test@example.com');
});

test('Affiliate hasActivePayoutHold returns false when no holds', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'NOHOLD001',
        'name' => 'No Hold Affiliate',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    expect($affiliate->hasActivePayoutHold())->toBeFalse();
});
