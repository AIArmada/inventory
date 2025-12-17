<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Cart\Security\Fraud\FraudContext;
use AIArmada\Cart\Security\Fraud\FraudSignal;
use AIArmada\Cart\Security\Fraud\FraudSignalCollector;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

describe('FraudSignalCollector Integration', function (): void {
    beforeEach(function (): void {
        $this->collector = new FraudSignalCollector;
        $this->cartManager = app(CartManagerInterface::class);
    });

    describe('collect', function (): void {
        it('collects signals from fraud analysis', function (): void {
            $cart = $this->cartManager
                ->setIdentifier('collector-test-' . uniqid())
                ->setInstance('default')
                ->getCart();
            $cart->add('item-1', 'Test Product', 50.00, 1);

            $context = new FraudContext(
                cart: $cart,
                userId: 'collector-user-1',
                ipAddress: '127.0.0.1',
                userAgent: 'Mozilla/5.0 (Test)',
                sessionId: 'test-session-' . uniqid(),
                timestamp: CarbonImmutable::now()
            );

            $signals = new Collection([
                new FraudSignal('test_signal', 'test_detector', 25, 'Test fraud signal'),
            ]);

            // Should not throw
            $this->collector->collect($context, $signals);

            expect(true)->toBeTrue();
        });

        it('handles empty signal collection', function (): void {
            $cart = $this->cartManager
                ->setIdentifier('empty-signals-' . uniqid())
                ->setInstance('default')
                ->getCart();
            $cart->add('item-1', 'Test Product', 50.00, 1);

            $context = new FraudContext(
                cart: $cart,
                userId: 'collector-user-2',
                ipAddress: '127.0.0.1',
                userAgent: 'Mozilla/5.0 (Test)',
                sessionId: 'test-session-' . uniqid(),
                timestamp: CarbonImmutable::now()
            );

            $this->collector->collect($context, new Collection);

            expect(true)->toBeTrue();
        });
    });

    describe('getRecentSignalsForUser', function (): void {
        it('returns empty array for new user', function (): void {
            $result = $this->collector->getRecentSignalsForUser('non-existent-user-' . uniqid());

            expect($result)->toBeArray();
        });

        it('returns signals with limit', function (): void {
            $userId = 'signal-user-' . uniqid();

            $result = $this->collector->getRecentSignalsForUser($userId, 50);

            expect($result)->toBeArray();
        });

        it('handles null user gracefully', function (): void {
            $result = $this->collector->getRecentSignalsForUser(null);

            expect($result)->toBeArray();
        });
    });

    describe('getRecentSignalsForIp', function (): void {
        it('returns empty array for new IP', function (): void {
            $result = $this->collector->getRecentSignalsForIp('10.0.0.' . rand(1, 255));

            expect($result)->toBeArray();
        });

        it('handles null IP gracefully', function (): void {
            $result = $this->collector->getRecentSignalsForIp(null);

            expect($result)->toBeArray();
        });
    });

    describe('getSignalCountForUser', function (): void {
        it('returns zero for new user', function (): void {
            $count = $this->collector->getSignalCountForUser('new-user-' . uniqid());

            expect($count)->toBe(0);
        });

        it('respects time window', function (): void {
            $count = $this->collector->getSignalCountForUser('test-user', 30);

            expect($count)->toBeInt();
        });

        it('handles null user gracefully', function (): void {
            $count = $this->collector->getSignalCountForUser(null);

            expect($count)->toBe(0);
        });
    });

    describe('getSignalCountForIp', function (): void {
        it('returns zero for new IP', function (): void {
            $count = $this->collector->getSignalCountForIp('172.16.0.' . rand(1, 255));

            expect($count)->toBe(0);
        });

        it('handles null IP gracefully', function (): void {
            $count = $this->collector->getSignalCountForIp(null);

            expect($count)->toBe(0);
        });
    });

    describe('getAggregatedScoreForUser', function (): void {
        it('returns zero for user with no signals', function (): void {
            $score = $this->collector->getAggregatedScoreForUser('no-signals-user-' . uniqid());

            expect($score)->toBe(0);
        });

        it('handles null user gracefully', function (): void {
            $score = $this->collector->getAggregatedScoreForUser(null);

            expect($score)->toBe(0);
        });
    });

    describe('isUserFlagged', function (): void {
        it('returns false for unflagged user', function (): void {
            $isFlagged = $this->collector->isUserFlagged('unflagged-user-' . uniqid());

            expect($isFlagged)->toBeFalse();
        });

        it('handles null user gracefully', function (): void {
            $isFlagged = $this->collector->isUserFlagged(null);

            expect($isFlagged)->toBeFalse();
        });
    });

    describe('flagUser', function (): void {
        it('flags user for review', function (): void {
            $userId = 'flag-test-user-' . uniqid();

            $this->collector->flagUser($userId, 'Suspicious activity detected');

            $isFlagged = $this->collector->isUserFlagged($userId);
            expect($isFlagged)->toBeTrue();
        });

        it('handles null user gracefully', function (): void {
            // Should not throw
            $this->collector->flagUser(null, 'Test reason');

            expect(true)->toBeTrue();
        });
    });

    describe('clearUserSignals', function (): void {
        it('clears signals for user', function (): void {
            $userId = 'clear-user-' . uniqid();

            // First flag and then clear
            $this->collector->flagUser($userId, 'Test');
            $this->collector->clearUserSignals($userId);

            $isFlagged = $this->collector->isUserFlagged($userId);
            expect($isFlagged)->toBeFalse();
        });

        it('handles null user gracefully', function (): void {
            $this->collector->clearUserSignals(null);

            expect(true)->toBeTrue();
        });
    });

    describe('getStatistics', function (): void {
        it('returns statistics array', function (): void {
            $stats = $this->collector->getStatistics();

            expect($stats)->toBeArray();
            expect($stats)->toHaveKey('total_signals');
        });

        it('respects time window', function (): void {
            $stats = $this->collector->getStatistics(48);

            expect($stats)->toBeArray();
        });
    });
});
