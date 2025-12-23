<?php

declare(strict_types=1);

use AIArmada\Vouchers\Enums\VoucherStatus;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Models\VoucherUsage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

test('voucher has relations', function (): void {
    $voucher = Voucher::create([
        'code' => 'TEST',
        'name' => 'Test Voucher',
        'type' => 'percentage',
        'value' => 10,
        'currency' => 'MYR',
        'status' => 'active',
    ]);

    expect($voucher->usages())->toBeInstanceOf(HasMany::class)
        ->and($voucher->walletEntries())->toBeInstanceOf(HasMany::class)
        ->and($voucher->owner())->toBeInstanceOf(MorphTo::class);
});

test('voucher scope for owner', function (): void {
    // Test with owner disabled
    Config::set('vouchers.owner.enabled', false);
    $query = Voucher::forOwner(null);
    expect($query)->toBeInstanceOf(Builder::class);

    Config::set('vouchers.owner.enabled', true);

    // Test with no owner, include global
    $query = Voucher::forOwner(null, true);
    expect($query)->toBeInstanceOf(Builder::class);

    // Test with no owner, exclude global
    $query = Voucher::forOwner(null, false);
    expect($query)->toBeInstanceOf(Builder::class);

    // Test with owner
    $user = new class extends Model
    {
        protected $table = 'users';

        public function getMorphClass()
        {
            return 'User';
        }

        public function getKey()
        {
            return 1;
        }
    };

    $query = Voucher::forOwner($user, true);
    expect($query)->toBeInstanceOf(Builder::class);
});

test('voucher has uses left', function (): void {
    $voucher = Voucher::create([
        'code' => 'LIMITED',
        'name' => 'Limited Voucher',
        'type' => 'fixed',
        'value' => 10,
        'currency' => 'MYR',
        'status' => 'active',
        'usage_limit' => 5,
    ]);

    expect($voucher->hasUsageLimitRemaining())->toBeTrue();

    // Add usages
    for ($i = 0; $i < 5; $i++) {
        VoucherUsage::create([
            'voucher_id' => $voucher->id,
            'discount_amount' => 100,
            'currency' => 'MYR',
            'used_at' => now(),
            'redeemed_by_id' => $i + 1,
            'redeemed_by_type' => 'User',
        ]);
    }

    expect($voucher->hasUsageLimitRemaining())->toBeFalse();
});

test('voucher get remaining uses', function (): void {
    $voucher = Voucher::create([
        'code' => 'REMAIN',
        'name' => 'Remaining Voucher',
        'type' => 'fixed',
        'value' => 10,
        'currency' => 'MYR',
        'status' => 'active',
        'usage_limit' => 10,
    ]);

    expect($voucher->getRemainingUses())->toBe(10);

    VoucherUsage::create([
        'voucher_id' => $voucher->id,
        'discount_amount' => 100,
        'currency' => 'MYR',
        'used_at' => now(),
        'redeemed_by_id' => 1,
        'redeemed_by_type' => 'User',
    ]);

    expect($voucher->getRemainingUses())->toBe(9);

    // No limit
    $unlimited = Voucher::create([
        'code' => 'UNLIMITED',
        'name' => 'Unlimited Voucher',
        'type' => 'fixed',
        'value' => 10,
        'currency' => 'MYR',
        'status' => 'active',
    ]);

    expect($unlimited->getRemainingUses())->toBeNull();
});

test('voucher increment usage', function (): void {
    $voucher = Voucher::create([
        'code' => 'INCREMENT',
        'name' => 'Increment Voucher',
        'type' => 'fixed',
        'value' => 10,
        'currency' => 'MYR',
        'status' => 'active',
        'usage_limit' => 1,
    ]);

    VoucherUsage::create([
        'voucher_id' => $voucher->id,
        'discount_amount' => 100,
        'currency' => 'MYR',
        'used_at' => now(),
        'redeemed_by_id' => 1,
        'redeemed_by_type' => 'User',
    ]);

    $voucher->incrementUsage();

    $voucher->refresh();
    expect($voucher->status)->toBe(VoucherStatus::Depleted);
});

test('voucher wallet count accessors prefer preloaded counts', function (): void {
    $voucher = Voucher::create([
        'code' => 'WALLETCOUNTS',
        'name' => 'Wallet Counts Voucher',
        'type' => 'fixed',
        'value' => 10,
        'currency' => 'MYR',
        'status' => 'active',
    ]);

    $voucher->setAttribute('wallet_entries_count', 10);
    $voucher->setAttribute('wallet_claimed_count', 7);
    $voucher->setAttribute('wallet_redeemed_count', 4);
    $voucher->setAttribute('wallet_available_count', 6);

    expect($voucher->wallet_entries_count)->toBe(10)
        ->and($voucher->wallet_claimed_count)->toBe(7)
        ->and($voucher->wallet_redeemed_count)->toBe(4)
        ->and($voucher->wallet_available_count)->toBe(6);
});
