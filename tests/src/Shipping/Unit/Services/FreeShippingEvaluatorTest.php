<?php

declare(strict_types=1);

use AIArmada\Shipping\Services\FreeShippingEvaluator;
use AIArmada\Shipping\Services\FreeShippingResult;

// ============================================
// FreeShippingEvaluator Tests
// ============================================

// Stub class for testing since Cart is final
class CartStub
{
    public function __construct(private int $subtotal = 0) {}

    public function getSubtotal(): int
    {
        return $this->subtotal;
    }
}

it('returns null when free shipping is disabled', function (): void {
    $evaluator = new FreeShippingEvaluator([
        'enabled' => false,
        'threshold' => 10000,
    ]);

    $cart = new CartStub();

    $result = $evaluator->evaluate($cart);

    expect($result)->toBeNull();
})->skip('Requires actual Cart instance - Cart is final');

it('returns null when no threshold configured', function (): void {
    $evaluator = new FreeShippingEvaluator([
        'enabled' => true,
        'threshold' => null,
    ]);

    $cart = new CartStub();

    $result = $evaluator->evaluate($cart);

    expect($result)->toBeNull();
})->skip('Requires actual Cart instance - Cart is final');

it('applies free shipping when cart meets threshold', function (): void {
    $evaluator = new FreeShippingEvaluator([
        'enabled' => true,
        'threshold' => 10000, // RM100.00
    ]);

    $cart = new CartStub(15000); // RM150.00

    $result = $evaluator->evaluate($cart);

    expect($result)->toBeInstanceOf(FreeShippingResult::class);
    expect($result->applies)->toBeTrue();
    expect($result->message)->toContain('Free shipping');
})->skip('Requires actual Cart instance - Cart is final');

it('returns remaining amount when below threshold', function (): void {
    $evaluator = new FreeShippingEvaluator([
        'enabled' => true,
        'threshold' => 10000, // RM100.00
        'currency' => 'RM',
    ]);

    $cart = new CartStub(7500); // RM75.00

    $result = $evaluator->evaluate($cart);

    expect($result)->toBeInstanceOf(FreeShippingResult::class);
    expect($result->applies)->toBeFalse();
    expect($result->nearThreshold)->toBeTrue();
    expect($result->remainingAmount)->toBe(2500); // RM25.00 to go
    expect($result->message)->toContain('RM25.00');
})->skip('Requires actual Cart instance - Cart is final');

it('applies free shipping at exact threshold', function (): void {
    $evaluator = new FreeShippingEvaluator([
        'enabled' => true,
        'threshold' => 10000,
    ]);

    $cart = new CartStub(10000); // Exactly at threshold

    $result = $evaluator->evaluate($cart);

    expect($result->applies)->toBeTrue();
})->skip('Requires actual Cart instance - Cart is final');

// ============================================
// FreeShippingResult Tests
// ============================================

describe('FreeShippingResult', function (): void {
    it('creates result with all properties', function (): void {
        $result = new FreeShippingResult(
            applies: true,
            message: 'Free shipping applied!',
            remainingAmount: null,
            nearThreshold: false,
        );

        expect($result->applies)->toBeTrue();
        expect($result->message)->toBe('Free shipping applied!');
        expect($result->remainingAmount)->toBeNull();
        expect($result->nearThreshold)->toBeFalse();
    });

    it('formats remaining amount as currency', function (): void {
        $result = new FreeShippingResult(
            applies: false,
            remainingAmount: 2500, // RM25.00
        );

        expect($result->getFormattedRemaining())->toBe('25.00');
    });

    it('returns null formatted remaining when no amount', function (): void {
        $result = new FreeShippingResult(
            applies: true,
            remainingAmount: null,
        );

        expect($result->getFormattedRemaining())->toBeNull();
    });
});
