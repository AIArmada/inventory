<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Support\OwnerResolvers\FixedOwnerResolver;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\FilamentVouchers\Support\OwnerScopedQueries;
use AIArmada\Vouchers\Enums\VoucherStatus;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Models\Voucher;

uses(TestCase::class);

it('forces owner columns on create when owner mode enabled', function (): void {
    config()->set('vouchers.owner.enabled', true);

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'owner-a-write@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'owner-b-write@example.com',
        'password' => 'secret',
    ]);

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new FixedOwnerResolver($ownerA));

    $data = OwnerScopedQueries::enforceOwnerOnCreate([
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => (string) $ownerB->getKey(),
    ]);

    expect($data['owner_type'])->toBe($ownerA->getMorphClass());
    expect($data['owner_id'])->toBe((string) $ownerA->getKey());
});

it('keeps global rows global on update when owner mode enabled', function (): void {
    config()->set('vouchers.owner.enabled', true);

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'owner-a-update-global@example.com',
        'password' => 'secret',
    ]);

    $voucher = Voucher::query()->create([
        'code' => 'GLOBAL-UPDATE-1',
        'name' => 'Global Voucher',
        'type' => VoucherType::Fixed,
        'value' => 1000,
        'currency' => 'USD',
        'status' => VoucherStatus::Active,
        'allows_manual_redemption' => true,
        'starts_at' => now()->subDay(),
    ]);

    $data = OwnerScopedQueries::enforceOwnerOnUpdate($voucher, [
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => (string) $ownerA->getKey(),
    ]);

    expect($data['owner_type'])->toBeNull();
    expect($data['owner_id'])->toBeNull();
});

it('prevents changing ownership on update when owner mode enabled', function (): void {
    config()->set('vouchers.owner.enabled', true);

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'owner-a-update-owned@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'owner-b-update-owned@example.com',
        'password' => 'secret',
    ]);

    $voucher = Voucher::query()->create([
        'code' => 'OWNED-UPDATE-1',
        'name' => 'Owned Voucher',
        'type' => VoucherType::Fixed,
        'value' => 1000,
        'currency' => 'USD',
        'status' => VoucherStatus::Active,
        'allows_manual_redemption' => true,
        'starts_at' => now()->subDay(),
    ]);
    $voucher->assignOwner($ownerA)->save();

    $data = OwnerScopedQueries::enforceOwnerOnUpdate($voucher, [
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => (string) $ownerB->getKey(),
    ]);

    expect($data['owner_type'])->toBe($ownerA->getMorphClass());
    expect($data['owner_id'])->toBe((string) $ownerA->getKey());
});
