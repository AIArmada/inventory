<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Testing\InMemoryStorage;
use Akaunting\Money\Money;

describe('ImplementsCheckoutable trait', function (): void {
    beforeEach(function (): void {
        $this->storage = new InMemoryStorage;
        $this->cart = new Cart($this->storage, 'checkout-test');
    });

    describe('getCheckoutLineItems', function (): void {
        it('returns empty array when cart is empty', function (): void {
            $items = $this->cart->getCheckoutLineItems();

            expect($items)->toBeArray()->toBeEmpty();
        });

        it('returns all items in cart', function (): void {
            $this->cart->add('item-1', 'Product 1', 1000, 2);
            $this->cart->add('item-2', 'Product 2', 2000, 1);

            $items = $this->cart->getCheckoutLineItems();

            expect($items)->toHaveCount(2);
        });
    });

    describe('getCheckoutSubtotal', function (): void {
        it('returns zero for empty cart', function (): void {
            $subtotal = $this->cart->getCheckoutSubtotal();

            expect($subtotal)->toBeInstanceOf(Money::class)
                ->and($subtotal->getAmount())->toBe(0);
        });

        it('returns subtotal for items', function (): void {
            $this->cart->add('item-1', 'Product 1', 1000, 2); // 2000
            $this->cart->add('item-2', 'Product 2', 500, 3); // 1500

            $subtotal = $this->cart->getCheckoutSubtotal();

            expect($subtotal->getAmount())->toBe(3500);
        });
    });

    describe('getCheckoutDiscount', function (): void {
        it('returns zero when no discounts applied', function (): void {
            $this->cart->add('item-1', 'Product', 1000, 1);

            $discount = $this->cart->getCheckoutDiscount();

            expect($discount)->toBeInstanceOf(Money::class)
                ->and($discount->getAmount())->toBe(0);
        });

        it('calculates discount from cart conditions', function (): void {
            $this->cart->add('item-1', 'Product', 10000, 1);

            // Add 10% discount
            $this->cart->addCondition(new CartCondition(
                name: 'Promo',
                type: 'discount',
                target: 'cart@cart_subtotal/aggregate',
                value: '-10%',
                attributes: [],
                order: 0,
                rules: null
            ));

            $discount = $this->cart->getCheckoutDiscount();

            expect($discount->getAmount())->toBe(1000); // 10% of 10000
        });
    });

    describe('getCheckoutTax', function (): void {
        it('returns zero when no tax applied', function (): void {
            $this->cart->add('item-1', 'Product', 1000, 1);

            $tax = $this->cart->getCheckoutTax();

            expect($tax)->toBeInstanceOf(Money::class)
                ->and($tax->getAmount())->toBe(0);
        });

        it('calculates tax from cart conditions', function (): void {
            $this->cart->add('item-1', 'Product', 10000, 1);

            // Add 8% tax
            $this->cart->addCondition(new CartCondition(
                name: 'SST',
                type: 'tax',
                target: 'cart@cart_subtotal/aggregate',
                value: '+8%',
                attributes: [],
                order: 0,
                rules: null
            ));

            $tax = $this->cart->getCheckoutTax();

            expect($tax->getAmount())->toBe(800); // 8% of 10000
        });
    });

    describe('getCheckoutShipping', function (): void {
        it('returns zero when no shipping set', function (): void {
            $this->cart->add('item-1', 'Product', 1000, 1);

            $shipping = $this->cart->getCheckoutShipping();

            expect($shipping)->toBeInstanceOf(Money::class)
                ->and($shipping->getAmount())->toBe(0);
        });
    });

    describe('getCheckoutTotal', function (): void {
        it('returns total with conditions', function (): void {
            $this->cart->add('item-1', 'Product', 10000, 1);

            $total = $this->cart->getCheckoutTotal();

            expect($total)->toBeInstanceOf(Money::class)
                ->and($total->getAmount())->toBe(10000);
        });
    });

    describe('getCheckoutCurrency', function (): void {
        it('returns configured currency', function (): void {
            $currency = $this->cart->getCheckoutCurrency();

            expect($currency)->toBeString()
                ->and(strlen($currency))->toBe(3); // ISO currency codes are 3 chars
        });
    });

    describe('getCheckoutReference', function (): void {
        it('returns unique reference', function (): void {
            $reference = $this->cart->getCheckoutReference();

            expect($reference)->toBeString()
                ->and($reference)->not->toBeEmpty();
        });

        it('generates non-empty reference', function (): void {
            $this->cart->add('item-1', 'Product', 1000, 1);

            $reference = $this->cart->getCheckoutReference();

            expect($reference)->toBeString()
                ->and(strlen($reference))->toBeGreaterThan(0);
        });
    });

    describe('getCheckoutNotes', function (): void {
        it('returns null when no notes set', function (): void {
            $notes = $this->cart->getCheckoutNotes();

            expect($notes)->toBeNull();
        });

        it('returns notes from metadata', function (): void {
            $this->cart->setMetadata('notes', 'Please deliver to back door');

            $notes = $this->cart->getCheckoutNotes();

            expect($notes)->toBe('Please deliver to back door');
        });

        it('returns checkout_notes from metadata', function (): void {
            $this->cart->setMetadata('checkout_notes', 'Express shipping preferred');

            $notes = $this->cart->getCheckoutNotes();

            expect($notes)->toBe('Express shipping preferred');
        });
    });

    describe('getCheckoutMetadata', function (): void {
        it('returns cart identifier and instance', function (): void {
            $metadata = $this->cart->getCheckoutMetadata();

            expect($metadata)->toBeArray()
                ->and($metadata)->toHaveKey('cart_identifier')
                ->and($metadata)->toHaveKey('cart_instance')
                ->and($metadata['cart_identifier'])->toBe('checkout-test');
        });

        it('includes item count and quantity', function (): void {
            $this->cart->add('item-1', 'Product 1', 1000, 2);
            $this->cart->add('item-2', 'Product 2', 2000, 3);

            $metadata = $this->cart->getCheckoutMetadata();

            expect($metadata['item_count'])->toBe(2)
                ->and($metadata['total_quantity'])->toBe(5);
        });

        it('includes conditions when present', function (): void {
            $this->cart->add('item-1', 'Product', 1000, 1);
            $this->cart->addCondition(new CartCondition(
                name: 'Tax',
                type: 'tax',
                target: 'cart@cart_subtotal/aggregate',
                value: '+6%',
                attributes: [],
                order: 0,
                rules: null
            ));

            $metadata = $this->cart->getCheckoutMetadata();

            expect($metadata)->toHaveKey('conditions')
                ->and($metadata['conditions'])->toHaveCount(1);
        });
    });
});
