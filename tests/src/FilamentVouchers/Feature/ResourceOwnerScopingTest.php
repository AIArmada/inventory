<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Support\OwnerResolvers\FixedOwnerResolver;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\FilamentVouchers\Models\Voucher as FilamentVoucher;
use AIArmada\FilamentVouchers\Resources\VoucherResource;
use AIArmada\FilamentVouchers\Resources\VoucherUsageResource;
use AIArmada\FilamentVouchers\Resources\VoucherWalletResource;
use AIArmada\Vouchers\Enums\VoucherStatus;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Models\VoucherUsage;
use AIArmada\Vouchers\Models\VoucherWallet;

uses(TestCase::class);

it('scopes Filament Vouchers resources to the resolved owner (including global)', function (): void {
    config()->set('vouchers.owner.enabled', true);
    config()->set('vouchers.owner.include_global', true);

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'owner-a@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'owner-b@example.com',
        'password' => 'secret',
    ]);

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new FixedOwnerResolver($ownerA));

    $globalVoucher = FilamentVoucher::query()->create([
        'code' => 'GLOBAL-1',
        'name' => 'Global Voucher',
        'type' => VoucherType::Fixed,
        'value' => 1000,
        'currency' => 'USD',
        'status' => VoucherStatus::Active,
        'allows_manual_redemption' => true,
        'starts_at' => now()->subDay(),
    ]);

    $ownerAVoucher = FilamentVoucher::query()->create([
        'code' => 'A-1',
        'name' => 'Owner A Voucher',
        'type' => VoucherType::Fixed,
        'value' => 1000,
        'currency' => 'USD',
        'status' => VoucherStatus::Active,
        'allows_manual_redemption' => true,
        'starts_at' => now()->subDay(),
    ]);
    $ownerAVoucher->assignOwner($ownerA)->save();

    $ownerBVoucher = FilamentVoucher::query()->create([
        'code' => 'B-1',
        'name' => 'Owner B Voucher',
        'type' => VoucherType::Fixed,
        'value' => 1000,
        'currency' => 'USD',
        'status' => VoucherStatus::Active,
        'allows_manual_redemption' => true,
        'starts_at' => now()->subDay(),
    ]);
    $ownerBVoucher->assignOwner($ownerB)->save();

    $globalUsage = VoucherUsage::query()->create([
        'voucher_id' => $globalVoucher->id,
        'discount_amount' => 100,
        'currency' => 'USD',
        'channel' => VoucherUsage::CHANNEL_API,
        'used_at' => now()->subMinutes(5),
    ]);

    $ownerAUsage = VoucherUsage::query()->create([
        'voucher_id' => $ownerAVoucher->id,
        'discount_amount' => 100,
        'currency' => 'USD',
        'channel' => VoucherUsage::CHANNEL_API,
        'used_at' => now()->subMinutes(5),
    ]);

    $ownerBUsage = VoucherUsage::query()->create([
        'voucher_id' => $ownerBVoucher->id,
        'discount_amount' => 100,
        'currency' => 'USD',
        'channel' => VoucherUsage::CHANNEL_API,
        'used_at' => now()->subMinutes(5),
    ]);

    $globalWallet = VoucherWallet::query()->create([
        'voucher_id' => $globalVoucher->id,
        'holder_type' => $ownerA->getMorphClass(),
        'holder_id' => $ownerA->getKey(),
        'is_claimed' => true,
        'claimed_at' => now(),
        'is_redeemed' => false,
    ]);

    $ownerBWallet = VoucherWallet::query()->create([
        'voucher_id' => $ownerBVoucher->id,
        'holder_type' => $ownerB->getMorphClass(),
        'holder_id' => $ownerB->getKey(),
        'is_claimed' => true,
        'claimed_at' => now(),
        'is_redeemed' => false,
    ]);

    $vouchers = VoucherResource::getEloquentQuery()->pluck('id')->all();
    expect($vouchers)->toContain($globalVoucher->id, $ownerAVoucher->id)
        ->not->toContain($ownerBVoucher->id);

    $usages = VoucherUsageResource::getEloquentQuery()->pluck('id')->all();
    expect($usages)->toContain($globalUsage->id, $ownerAUsage->id)
        ->not->toContain($ownerBUsage->id);

    $wallets = VoucherWalletResource::getEloquentQuery()->pluck('id')->all();
    expect($wallets)->toContain($globalWallet->id)
        ->not->toContain($ownerBWallet->id);
});

it('can exclude global records from Filament Vouchers resources', function (): void {
    config()->set('vouchers.owner.enabled', true);
    config()->set('vouchers.owner.include_global', false);

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'owner-a-2@example.com',
        'password' => 'secret',
    ]);

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new FixedOwnerResolver($ownerA));

    $globalVoucher = FilamentVoucher::query()->create([
        'code' => 'GLOBAL-2',
        'name' => 'Global Voucher',
        'type' => VoucherType::Fixed,
        'value' => 1000,
        'currency' => 'USD',
        'status' => VoucherStatus::Active,
        'allows_manual_redemption' => true,
        'starts_at' => now()->subDay(),
    ]);

    $ownerAVoucher = FilamentVoucher::query()->create([
        'code' => 'A-2',
        'name' => 'Owner A Voucher',
        'type' => VoucherType::Fixed,
        'value' => 1000,
        'currency' => 'USD',
        'status' => VoucherStatus::Active,
        'allows_manual_redemption' => true,
        'starts_at' => now()->subDay(),
    ]);
    $ownerAVoucher->assignOwner($ownerA)->save();

    $globalUsage = VoucherUsage::query()->create([
        'voucher_id' => $globalVoucher->id,
        'discount_amount' => 100,
        'currency' => 'USD',
        'channel' => VoucherUsage::CHANNEL_API,
        'used_at' => now()->subMinutes(5),
    ]);

    $ownerAUsage = VoucherUsage::query()->create([
        'voucher_id' => $ownerAVoucher->id,
        'discount_amount' => 100,
        'currency' => 'USD',
        'channel' => VoucherUsage::CHANNEL_API,
        'used_at' => now()->subMinutes(5),
    ]);

    $vouchers = VoucherResource::getEloquentQuery()->pluck('id')->all();
    expect($vouchers)->toContain($ownerAVoucher->id)
        ->not->toContain($globalVoucher->id);

    $usages = VoucherUsageResource::getEloquentQuery()->pluck('id')->all();
    expect($usages)->toContain($ownerAUsage->id)
        ->not->toContain($globalUsage->id);
});
