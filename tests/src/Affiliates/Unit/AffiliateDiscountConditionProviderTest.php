<?php

declare(strict_types=1);

use AIArmada\Affiliates\Cart\AffiliateDiscountConditionProvider;
use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Services\AffiliateService;
use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Storage\DatabaseStorage;
use Illuminate\Support\Facades\DB;

function createAffiliateTestCart(string $identifier = 'affiliate-test'): Cart
{
    $storage = new DatabaseStorage(DB::connection('testing'), 'carts');

    return new Cart(
        storage: $storage,
        identifier: $identifier,
        events: null,
        instanceName: 'default'
    );
}

function createTestAffiliateCondition(string $type = 'affiliate_discount', string $affiliateCode = 'TEST'): CartCondition
{
    return new CartCondition(
        name: 'test_affiliate_condition',
        type: $type,
        target: [
            'scope' => 'cart',
            'phase' => 'cart_subtotal',
            'application' => 'aggregate',
        ],
        value: '-5%',
        attributes: ['affiliate_code' => $affiliateCode],
    );
}

describe('AffiliateDiscountConditionProvider', function (): void {
    beforeEach(function (): void {
        config(['affiliates.cart.customer_discounts_enabled' => true]);
        config(['affiliates.cart.metadata_key' => 'affiliate']);
    });

    it('returns empty conditions when feature is disabled', function (): void {
        config(['affiliates.cart.customer_discounts_enabled' => false]);

        $cart = createAffiliateTestCart('disabled-feature');
        $service = app(AffiliateService::class);

        $provider = new AffiliateDiscountConditionProvider($service);
        $conditions = $provider->getConditionsFor($cart);

        expect($conditions)->toBeEmpty();
    });

    it('returns empty conditions when no affiliate attached', function (): void {
        $cart = createAffiliateTestCart('no-affiliate-attached');
        $service = app(AffiliateService::class);

        $provider = new AffiliateDiscountConditionProvider($service);
        $conditions = $provider->getConditionsFor($cart);

        expect($conditions)->toBeEmpty();
    });

    it('returns empty conditions when affiliate has no customer discount', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'NO-DISCOUNT-TEST',
            'name' => 'No Discount Partner',
            'status' => AffiliateStatus::Active,
            'commission_type' => 'percentage',
            'commission_rate' => 500,
            'currency' => 'MYR',
            'metadata' => [],
        ]);

        $cart = createAffiliateTestCart('no-discount-test');
        $service = app(AffiliateService::class);

        // Attach affiliate to cart
        $service->attachAffiliate($affiliate, $cart);

        $provider = new AffiliateDiscountConditionProvider($service);
        $conditions = $provider->getConditionsFor($cart);

        expect($conditions)->toBeEmpty();
    });

    it('creates percentage discount condition from affiliate metadata', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'PARTNER10-TEST',
            'name' => 'Partner Ten',
            'status' => AffiliateStatus::Active,
            'commission_type' => 'percentage',
            'commission_rate' => 500,
            'currency' => 'MYR',
            'metadata' => [
                'customer_discount' => [
                    'type' => 'percentage',
                    'value' => 500, // 5% in basis points
                ],
            ],
        ]);

        $cart = createAffiliateTestCart('percentage-discount-test');
        $service = app(AffiliateService::class);
        $service->attachAffiliate($affiliate, $cart);

        $provider = new AffiliateDiscountConditionProvider($service);
        $conditions = $provider->getConditionsFor($cart);

        expect($conditions)->toHaveCount(1);
        expect($conditions[0])->toBeInstanceOf(CartCondition::class);
        expect($conditions[0]->getType())->toBe('affiliate_discount');
        expect($conditions[0]->getValue())->toBe('-5%');
        expect($conditions[0]->getName())->toBe('affiliate_discount_PARTNER10-TEST');
    });

    it('creates fixed discount condition from affiliate metadata', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'FIXED5-TEST',
            'name' => 'Fixed Five',
            'status' => AffiliateStatus::Active,
            'commission_type' => 'percentage',
            'commission_rate' => 500,
            'currency' => 'MYR',
            'metadata' => [
                'customer_discount' => [
                    'type' => 'fixed',
                    'value' => 500, // RM5.00 in cents
                ],
            ],
        ]);

        $cart = createAffiliateTestCart('fixed-discount-test');
        $service = app(AffiliateService::class);
        $service->attachAffiliate($affiliate, $cart);

        $provider = new AffiliateDiscountConditionProvider($service);
        $conditions = $provider->getConditionsFor($cart);

        expect($conditions)->toHaveCount(1);
        expect($conditions[0]->getValue())->toBe('-500');
    });

    it('returns the correct type', function (): void {
        $service = app(AffiliateService::class);
        $provider = new AffiliateDiscountConditionProvider($service);

        expect($provider->getType())->toBe('affiliate_discount');
    });

    it('returns the correct priority', function (): void {
        $service = app(AffiliateService::class);
        $provider = new AffiliateDiscountConditionProvider($service);

        expect($provider->getPriority())->toBe(120);
    });

    it('validates condition returns true for other types', function (): void {
        $cart = createAffiliateTestCart('other-type-test');

        $condition = createTestAffiliateCondition('voucher');

        $service = app(AffiliateService::class);
        $provider = new AffiliateDiscountConditionProvider($service);

        expect($provider->validate($condition, $cart))->toBeTrue();
    });

    it('validates condition returns false for invalid affiliate code', function (): void {
        $cart = createAffiliateTestCart('invalid-code-test');

        $condition = createTestAffiliateCondition('affiliate_discount', 'INVALID-NONEXISTENT');

        $service = app(AffiliateService::class);
        $provider = new AffiliateDiscountConditionProvider($service);

        expect($provider->validate($condition, $cart))->toBeFalse();
    });

    it('validates condition returns false for inactive affiliate', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'INACTIVE-TEST',
            'name' => 'Inactive Partner',
            'status' => AffiliateStatus::Disabled,
            'commission_type' => 'percentage',
            'commission_rate' => 500,
            'currency' => 'MYR',
        ]);

        $cart = createAffiliateTestCart('inactive-test');
        $condition = createTestAffiliateCondition('affiliate_discount', 'INACTIVE-TEST');

        $service = app(AffiliateService::class);
        $provider = new AffiliateDiscountConditionProvider($service);

        expect($provider->validate($condition, $cart))->toBeFalse();
    });

    it('validates active affiliate with discount returns true', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'VALID-DISCOUNT-TEST',
            'name' => 'Valid Partner',
            'status' => AffiliateStatus::Active,
            'commission_type' => 'percentage',
            'commission_rate' => 500,
            'currency' => 'MYR',
            'metadata' => [
                'customer_discount' => [
                    'type' => 'percentage',
                    'value' => 500,
                ],
            ],
        ]);

        $cart = createAffiliateTestCart('valid-active-test');
        $condition = createTestAffiliateCondition('affiliate_discount', 'VALID-DISCOUNT-TEST');

        $service = app(AffiliateService::class);
        $provider = new AffiliateDiscountConditionProvider($service);

        expect($provider->validate($condition, $cart))->toBeTrue();
    });

    it('ignores zero or negative discount values', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'ZERO-DISCOUNT-TEST',
            'name' => 'Zero Discount',
            'status' => AffiliateStatus::Active,
            'commission_type' => 'percentage',
            'commission_rate' => 500,
            'currency' => 'MYR',
            'metadata' => [
                'customer_discount' => [
                    'type' => 'percentage',
                    'value' => 0,
                ],
            ],
        ]);

        $cart = createAffiliateTestCart('zero-discount-test');
        $service = app(AffiliateService::class);
        $service->attachAffiliate($affiliate, $cart);

        $provider = new AffiliateDiscountConditionProvider($service);
        $conditions = $provider->getConditionsFor($cart);

        expect($conditions)->toBeEmpty();
    });

    it('includes affiliate attributes in condition', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'SPECIAL-ATTRS-TEST',
            'name' => 'Special Partner',
            'status' => AffiliateStatus::Active,
            'commission_type' => 'percentage',
            'commission_rate' => 500,
            'currency' => 'MYR',
            'metadata' => [
                'customer_discount' => [
                    'type' => 'percentage',
                    'value' => 1000, // 10%
                ],
            ],
        ]);

        $cart = createAffiliateTestCart('with-attrs-test');
        $service = app(AffiliateService::class);
        $service->attachAffiliate($affiliate, $cart);

        $provider = new AffiliateDiscountConditionProvider($service);
        $conditions = $provider->getConditionsFor($cart);

        $attributes = $conditions[0]->getAttributes();

        expect($attributes)->toHaveKey('affiliate_id');
        expect($attributes)->toHaveKey('affiliate_code');
        expect($attributes)->toHaveKey('affiliate_name');
        expect($attributes)->toHaveKey('discount_type');
        expect($attributes)->toHaveKey('discount_value');
        expect($attributes['affiliate_code'])->toBe('SPECIAL-ATTRS-TEST');
    });
});
