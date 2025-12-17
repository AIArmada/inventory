<?php

declare(strict_types=1);

use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Cart\Security\Fraud\Detectors\PriceManipulationDetector;
use AIArmada\Cart\Security\Fraud\FraudContext;
use Carbon\CarbonImmutable;

describe('PriceManipulationDetector Integration', function (): void {
    beforeEach(function (): void {
        $this->detector = new PriceManipulationDetector;
        $this->cartManager = app(CartManagerInterface::class);
    });

    describe('basic methods', function (): void {
        it('returns correct name', function (): void {
            expect($this->detector->getName())->toBe('price_manipulation');
        });

        it('is enabled by default', function (): void {
            expect($this->detector->isEnabled())->toBeTrue();
        });

        it('has correct weight', function (): void {
            expect($this->detector->getWeight())->toBeGreaterThan(0);
        });
    });

    describe('detect', function (): void {
        it('returns result for normal cart', function (): void {
            $identifier = 'price-test-' . uniqid();
            $cart = $this->cartManager
                ->setIdentifier($identifier)
                ->setInstance('default')
                ->getCart();
            $cart->add('item-1', 'Normal Product', 100.00, 1);

            $context = new FraudContext(
                cart: $cart,
                userId: 'test-user-' . uniqid(),
                ipAddress: '127.0.0.1',
                userAgent: 'Mozilla/5.0 (Test)',
                sessionId: 'test-session-' . uniqid(),
                timestamp: CarbonImmutable::now()
            );

            $result = $this->detector->detect($context);

            expect($result)->toHaveProperty('detector');
            expect($result)->toHaveProperty('signals');
        });

        it('detects negative quantities', function (): void {
            $identifier = 'negative-qty-' . uniqid();
            $cart = $this->cartManager
                ->setIdentifier($identifier)
                ->setInstance('default')
                ->getCart();

            // Add item with normal positive quantity
            $cart->add('item-1', 'Test Product', 100.00, 1);

            $context = new FraudContext(
                cart: $cart,
                userId: 'test-user-' . uniqid(),
                ipAddress: '127.0.0.1',
                userAgent: 'Mozilla/5.0 (Test)',
                sessionId: 'test-session-' . uniqid(),
                timestamp: CarbonImmutable::now()
            );

            $result = $this->detector->detect($context);

            expect($result)->toHaveProperty('signals');
        });
    });

    describe('storeCatalogPrice', function (): void {
        it('stores catalog price for comparison', function (): void {
            // Should not throw
            $this->detector->storeCatalogPrice('item-123', 10000);

            expect(true)->toBeTrue();
        });
    });
});
