<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentOrders\Resources\OrderResource;
use AIArmada\FilamentVouchers\Resources\VoucherResource;
use AIArmada\FilamentVouchers\Resources\VoucherWalletResource;
use AIArmada\FilamentShipping\Pages\FulfillmentQueue;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\PendingPayment;
use AIArmada\Orders\States\Processing;
use AIArmada\Vouchers\Enums\VoucherStatus;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Models\VoucherWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('orders + vouchers filament navigation badges work for the single tenant', function (): void {
    config()->set('orders.owner.enabled', true);
    config()->set('orders.owner.include_global', false);

    config()->set('vouchers.owner.enabled', true);
    config()->set('vouchers.owner.include_global', false);

    $owner = \App\Models\User::factory()->create(['email' => 'admin@commerce.demo']);

    $createOrder = static function (string $status, \App\Models\User $owner): void {
        $order = Order::create([
            'status' => $status,
            'subtotal' => 10000,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'grand_total' => 10000,
            'currency' => 'MYR',
        ]);

        $order->assignOwner($owner)->save();
    };

    OwnerContext::withOwner($owner, function () use ($owner, $createOrder): void {
        $createOrder(PendingPayment::class, $owner);
        $createOrder(Processing::class, $owner);
        $createOrder(Processing::class, $owner);

        Voucher::create([
            'code' => 'WELCOME-1',
            'name' => 'Welcome Voucher 1',
            'type' => VoucherType::Fixed,
            'value' => 1000,
            'currency' => 'MYR',
            'status' => VoucherStatus::Active,
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => (string) $owner->getKey(),
        ]);

        Voucher::create([
            'code' => 'WELCOME-2',
            'name' => 'Welcome Voucher 2',
            'type' => VoucherType::Fixed,
            'value' => 2000,
            'currency' => 'MYR',
            'status' => VoucherStatus::Active,
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => (string) $owner->getKey(),
        ]);

        $voucher = Voucher::query()->where('code', 'WELCOME-1')->firstOrFail();

        VoucherWallet::create([
            'voucher_id' => $voucher->id,
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => (string) $owner->getKey(),
            'is_claimed' => true,
            'is_redeemed' => false,
        ]);

        VoucherWallet::create([
            'voucher_id' => $voucher->id,
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => (string) $owner->getKey(),
            'is_claimed' => true,
            'is_redeemed' => false,
        ]);
    });

    OwnerContext::withOwner($owner, function (): void {
        expect(OrderResource::getNavigationBadge())->toBe('3');
        expect(FulfillmentQueue::getNavigationBadge())->toBe('2');

        expect(VoucherResource::getNavigationBadge())->toBe('2');
        expect(VoucherWalletResource::getNavigationBadge())->toBe('2');
    });
});
