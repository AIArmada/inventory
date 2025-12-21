<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentAffiliates\Support\Integrations\VoucherBridge;
use AIArmada\Vouchers\Enums\VoucherStatus;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Models\Voucher;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config()->set('affiliates.owner.enabled', true);
    config()->set('affiliates.owner.include_global', false);

    config()->set('vouchers.owner.enabled', true);
    config()->set('vouchers.owner.include_global', false);

    Voucher::query()->delete();

    OwnerContext::clearOverride();
});

afterEach(function (): void {
    OwnerContext::clearOverride();
});

it('does not leak cross-tenant voucher urls by code', function (): void {
    $ownerA = User::create([
        'name' => 'Owner A',
        'email' => 'owner-a-voucher-bridge@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::create([
        'name' => 'Owner B',
        'email' => 'owner-b-voucher-bridge@example.com',
        'password' => 'secret',
    ]);

    $code = 'VCH-' . Str::upper(Str::random(10));

    OwnerContext::withOwner($ownerB, function () use ($code, $ownerB): void {
        Voucher::create([
            'owner_type' => $ownerB->getMorphClass(),
            'owner_id' => $ownerB->getKey(),
            'code' => $code,
            'name' => 'Tenant Voucher',
            'type' => VoucherType::Percentage,
            'value' => 1000,
            'currency' => 'USD',
            'status' => VoucherStatus::Active,
            'allows_manual_redemption' => true,
            'applied_count' => 0,
            'stacking_priority' => 0,
        ]);

        expect(Voucher::query()->where('code', $code)->exists())->toBeTrue();
    });

    $bridge = new VoucherBridge;

    OwnerContext::withOwner($ownerA, function () use ($bridge, $code): void {
        expect($bridge->resolveUrl($code))->toBeNull();
    });
});
