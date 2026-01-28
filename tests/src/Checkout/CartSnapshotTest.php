<?php

declare(strict_types=1);

use AIArmada\Cart\Facades\Cart;
use AIArmada\Checkout\Contracts\CheckoutServiceInterface;

it('captures a full cart snapshot when starting checkout', function (): void {
    Cart::setIdentifier('guest-checkout');
    Cart::add('sku-1', 'Test Item', 1000, 2);
    Cart::setMetadata('checkout_session_id', 'sess_test');
    Cart::addShipping('Standard Shipping', 500, null, 'standard');

    $cartId = Cart::getId();

    expect($cartId)->not->toBeNull();

    $service = app(CheckoutServiceInterface::class);
    $session = $service->startCheckout($cartId);

    $snapshot = $session->cart_snapshot;

    expect($snapshot)->toHaveKey('items')
        ->and($snapshot)->toHaveKey('metadata')
        ->and($snapshot)->toHaveKey('conditions')
        ->and($snapshot)->toHaveKey('totals')
        ->and($snapshot['metadata']['checkout_session_id'])->toBe('sess_test')
        ->and($snapshot['totals'])->toHaveKey('subtotal')
        ->and($snapshot['totals'])->toHaveKey('total');
});
