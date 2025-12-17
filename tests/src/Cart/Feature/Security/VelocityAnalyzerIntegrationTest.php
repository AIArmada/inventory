<?php

declare(strict_types=1);

use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Cart\Security\Fraud\Detectors\VelocityAnalyzer;
use AIArmada\Cart\Security\Fraud\FraudContext;
use Carbon\CarbonImmutable;

describe('VelocityAnalyzer Integration', function (): void {
    beforeEach(function (): void {
        $this->analyzer = new VelocityAnalyzer;
        $this->cartManager = app(CartManagerInterface::class);
    });

    describe('basic methods', function (): void {
        it('returns correct name', function (): void {
            expect($this->analyzer->getName())->toBe('velocity_analyzer');
        });

        it('is enabled by default', function (): void {
            expect($this->analyzer->isEnabled())->toBeTrue();
        });

        it('has correct weight', function (): void {
            expect($this->analyzer->getWeight())->toBeGreaterThan(0);
        });
    });

    describe('detect', function (): void {
        it('returns result for normal cart activity', function (): void {
            $identifier = 'velocity-test-' . uniqid();
            $cart = $this->cartManager
                ->setIdentifier($identifier)
                ->setInstance('default')
                ->getCart();
            $cart->add('item-1', 'Test Product', 50.00, 1);

            $context = new FraudContext(
                cart: $cart,
                userId: 'test-user-' . uniqid(),
                ipAddress: '127.0.0.1',
                userAgent: 'Mozilla/5.0 (Test)',
                sessionId: 'test-session-' . uniqid(),
                timestamp: CarbonImmutable::now()
            );

            $result = $this->analyzer->detect($context);

            expect($result)->toHaveProperty('detector');
            expect($result)->toHaveProperty('signals');
        });
    });

    describe('recordFailedCheckout', function (): void {
        it('records failed checkout for IP tracking', function (): void {
            $ipAddress = '192.168.1.' . rand(1, 255);

            // Should not throw
            $this->analyzer->recordFailedCheckout($ipAddress);

            expect(true)->toBeTrue();
        });
    });

    describe('recordCheckoutAttempt', function (): void {
        it('records checkout attempt for user tracking', function (): void {
            $userId = 'user-' . uniqid();

            // Should not throw
            $this->analyzer->recordCheckoutAttempt($userId);

            expect(true)->toBeTrue();
        });
    });
});
