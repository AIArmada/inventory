<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\ConversionStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentAffiliates\Support\Integrations\CartBridge;
use AIArmada\FilamentCart\Models\Cart;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config()->set('affiliates.owner.enabled', true);
    config()->set('affiliates.owner.include_global', false);

    config()->set('filament-cart.owner.enabled', true);
    config()->set('filament-cart.owner.include_global', false);

    AffiliateConversion::query()->delete();
    Affiliate::query()->delete();
    Cart::query()->delete();

    OwnerContext::clearOverride();
});

afterEach(function (): void {
    OwnerContext::clearOverride();
});

it('does not leak cross-tenant cart urls by identifier', function (): void {
    $ownerA = User::create([
        'name' => 'Owner A',
        'email' => 'owner-a-cart-bridge@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::create([
        'name' => 'Owner B',
        'email' => 'owner-b-cart-bridge@example.com',
        'password' => 'secret',
    ]);

    $identifier = 'CART-' . Str::upper(Str::random(10));
    $instance = 'web';

    OwnerContext::withOwner($ownerB, function () use ($identifier, $instance, $ownerB): void {
        Cart::create([
            'owner_type' => $ownerB->getMorphClass(),
            'owner_id' => $ownerB->getKey(),
            'identifier' => $identifier,
            'instance' => $instance,
            'currency' => 'USD',
            'items' => [],
            'conditions' => [],
            'metadata' => [],
            'items_count' => 0,
            'quantity' => 0,
            'subtotal' => 0,
            'total' => 0,
            'savings' => 0,
        ]);

        expect(Cart::query()->where('identifier', $identifier)->exists())->toBeTrue();
    });

    OwnerContext::withOwner($ownerA, function () use ($identifier, $instance): void {
        $affiliate = Affiliate::create([
            'code' => 'AFF-' . Str::uuid(),
            'name' => 'Affiliate A',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 500,
            'currency' => 'USD',
        ]);

        AffiliateConversion::create([
            'affiliate_id' => $affiliate->getKey(),
            'affiliate_code' => $affiliate->code,
            'cart_identifier' => $identifier,
            'cart_instance' => $instance,
            'order_reference' => 'ORDER-' . Str::uuid(),
            'subtotal_minor' => 10000,
            'total_minor' => 10000,
            'commission_minor' => 500,
            'commission_currency' => 'USD',
            'status' => ConversionStatus::Approved,
            'channel' => 'test',
            'occurred_at' => now(),
        ]);

        expect(AffiliateConversion::query()->where('cart_identifier', $identifier)->exists())->toBeTrue();
    });

    $bridge = new CartBridge;

    OwnerContext::withOwner($ownerA, function () use ($bridge, $identifier, $instance): void {
        expect($bridge->resolveUrl($identifier, $instance))->toBeNull();
    });
});
