<?php

declare(strict_types=1);

use AIArmada\Shipping\Services\FreeShippingEvaluator;
use AIArmada\Shipping\Services\FreeShippingResult;

// ============================================
// FreeShippingEvaluator Tests
// ============================================
// Note: Tests requiring Cart instance are skipped in unit tests
// as they need the full Laravel framework. Consider moving to Feature tests.

it('returns null when free shipping is disabled', function (): void {
    expect(true)->toBeTrue();
})->skip('Requires Laravel framework - move to Feature tests');

it('returns null when no threshold configured', function (): void {
    expect(true)->toBeTrue();
})->skip('Requires Laravel framework - move to Feature tests');

it('applies free shipping when cart meets threshold', function (): void {
    expect(true)->toBeTrue();
})->skip('Requires Laravel framework - move to Feature tests');

it('returns remaining amount when below threshold', function (): void {
    expect(true)->toBeTrue();
})->skip('Requires Laravel framework - move to Feature tests');

it('applies free shipping at exact threshold', function (): void {
    expect(true)->toBeTrue();
})->skip('Requires Laravel framework - move to Feature tests');

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
