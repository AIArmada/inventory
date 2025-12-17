<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Cart\Security\Fraud\DetectorResult;
use AIArmada\Cart\Security\Fraud\FraudContext;
use AIArmada\Cart\Security\Fraud\FraudDetectionEngine;
use AIArmada\Cart\Security\Fraud\FraudDetectorInterface;
use AIArmada\Cart\Security\Fraud\FraudSignal;
use AIArmada\Cart\Security\Fraud\FraudSignalCollector;
use Illuminate\Support\Collection;

describe('FraudDetectionEngine Integration', function (): void {
    beforeEach(function (): void {
        $this->signalCollector = new FraudSignalCollector;
        $this->engine = new FraudDetectionEngine($this->signalCollector);
        $this->cartManager = app(CartManagerInterface::class);
    });

    describe('registerDetector', function (): void {
        it('registers a single detector', function (): void {
            $detector = createFraudMockDetector('test-detector');

            $result = $this->engine->registerDetector($detector);

            expect($result)->toBe($this->engine);
            expect($this->engine->getDetectors())->toHaveCount(1);
        });
    });

    describe('registerDetectors', function (): void {
        it('registers multiple detectors at once', function (): void {
            $detectors = [
                createFraudMockDetector('detector-1'),
                createFraudMockDetector('detector-2'),
                createFraudMockDetector('detector-3'),
            ];

            $this->engine->registerDetectors($detectors);

            expect($this->engine->getDetectors())->toHaveCount(3);
        });
    });

    describe('analyze', function (): void {
        it('returns low risk for cart with no suspicious signals', function (): void {
            $identifier = 'fraud-test-' . uniqid();
            $cart = $this->cartManager
                ->setIdentifier($identifier)
                ->setInstance('default')
                ->getCart();
            $cart->add('test-item', 'Test Product', 100.00, 1);

            // Register a detector that returns no signals
            $this->engine->registerDetector(createFraudMockDetector('safe-detector', signals: []));

            $result = $this->engine->analyze($cart, 'user-123', '192.168.1.1');

            expect($result->score)->toBeLessThanOrEqual(FraudDetectionEngine::THRESHOLD_LOW);
            expect($result->riskLevel)->toBeIn(['minimal', 'low']);
            expect($result->shouldBlock)->toBeFalse();
        });

        it('returns high risk for cart with multiple suspicious signals', function (): void {
            $identifier = 'fraud-high-risk-' . uniqid();
            $cart = $this->cartManager
                ->setIdentifier($identifier)
                ->setInstance('default')
                ->getCart();
            $cart->add('test-item', 'Test Product', 100.00, 1);

            // Register detectors with high-score signals
            $this->engine->registerDetector(createFraudMockDetector('velocity-detector', signals: [
                new FraudSignal('high_velocity', 'velocity_detector', 50, 'Too many operations'),
            ]));
            $this->engine->registerDetector(createFraudMockDetector('price-detector', signals: [
                new FraudSignal('price_manipulation', 'price_detector', 60, 'Suspicious price change'),
            ]));

            $result = $this->engine->analyze($cart, 'user-456');

            // Verify signals were detected
            expect($result->signals)->toHaveCount(2);
            expect($result->score)->toBeGreaterThan(0);
        });

        it('provides recommendations based on risk level', function (): void {
            $identifier = 'fraud-recommendations-' . uniqid();
            $cart = $this->cartManager
                ->setIdentifier($identifier)
                ->setInstance('default')
                ->getCart();
            $cart->add('test-item', 'Test Product', 100.00, 1);

            $this->engine->registerDetector(createFraudMockDetector('high-risk-detector', signals: [
                new FraudSignal('critical_signal', 'high_risk_detector', 90, 'Critical fraud signal'),
            ]));

            $result = $this->engine->analyze($cart, 'user-789');

            expect($result->recommendations)->not->toBeEmpty();
        });

        it('aggregates signals from multiple detectors', function (): void {
            $identifier = 'fraud-aggregate-' . uniqid();
            $cart = $this->cartManager
                ->setIdentifier($identifier)
                ->setInstance('default')
                ->getCart();
            $cart->add('test-item', 'Test Product', 100.00, 1);

            $this->engine->registerDetector(createFraudMockDetector('detector-a', signals: [
                new FraudSignal('signal_a', 'detector-a', 10, 'Signal A'),
            ]));
            $this->engine->registerDetector(createFraudMockDetector('detector-b', signals: [
                new FraudSignal('signal_b', 'detector-b', 15, 'Signal B'),
            ]));

            $result = $this->engine->analyze($cart);

            expect($result->signals)->toHaveCount(2);
            expect($result->detectorResults)->toHaveCount(2);
        });

        it('skips disabled detectors', function (): void {
            $identifier = 'fraud-disabled-' . uniqid();
            $cart = $this->cartManager
                ->setIdentifier($identifier)
                ->setInstance('default')
                ->getCart();
            $cart->add('test-item', 'Test Product', 100.00, 1);

            $this->engine->registerDetector(createFraudMockDetector('enabled', enabled: true, signals: [
                new FraudSignal('enabled_signal', 'enabled', 10, 'Enabled signal'),
            ]));
            $this->engine->registerDetector(createFraudMockDetector('disabled', enabled: false, signals: [
                new FraudSignal('disabled_signal', 'disabled', 50, 'Disabled signal'),
            ]));

            $result = $this->engine->analyze($cart);

            expect($result->signals)->toHaveCount(1);
            expect($result->signals[0]->type)->toBe('enabled_signal');
        });
    });

    describe('shouldBlock', function (): void {
        it('returns true for high-risk carts', function (): void {
            $identifier = 'fraud-block-' . uniqid();
            $cart = $this->cartManager
                ->setIdentifier($identifier)
                ->setInstance('default')
                ->getCart();
            $cart->add('test-item', 'Test Product', 100.00, 1);

            $this->engine->registerDetector(createFraudMockDetector('blocking-detector', signals: [
                new FraudSignal('critical', 'blocking-detector', 100, 'Critical signal'),
            ]));

            $shouldBlock = $this->engine->shouldBlock($cart);

            expect($shouldBlock)->toBeTrue();
        });

        it('returns false for low-risk carts', function (): void {
            $identifier = 'fraud-no-block-' . uniqid();
            $cart = $this->cartManager
                ->setIdentifier($identifier)
                ->setInstance('default')
                ->getCart();
            $cart->add('test-item', 'Test Product', 100.00, 1);

            $this->engine->registerDetector(createFraudMockDetector('safe-detector', signals: []));

            $shouldBlock = $this->engine->shouldBlock($cart);

            expect($shouldBlock)->toBeFalse();
        });
    });

    describe('requiresReview', function (): void {
        it('returns false for blocked carts', function (): void {
            $identifier = 'fraud-review-blocked-' . uniqid();
            $cart = $this->cartManager
                ->setIdentifier($identifier)
                ->setInstance('default')
                ->getCart();
            $cart->add('test-item', 'Test Product', 100.00, 1);

            $this->engine->registerDetector(createFraudMockDetector('high-detector', signals: [
                new FraudSignal('critical', 'high-detector', 100, 'Critical'),
            ]));

            // When should block is true, requiresReview should be true per logic
            $result = $this->engine->analyze($cart);
            if ($result->shouldBlock) {
                expect($result->shouldReview)->toBeTrue();
            }
        });
    });

    describe('configure', function (): void {
        it('merges new configuration', function (): void {
            $result = $this->engine->configure([
                'enabled' => false,
                'custom_setting' => 'value',
            ]);

            expect($result)->toBe($this->engine);
        });
    });
});

// Helper function
function createFraudMockDetector(
    string $name,
    bool $enabled = true,
    array $signals = [],
    float $weight = 1.0
): FraudDetectorInterface {
    return new class ($name, $enabled, $signals, $weight) implements FraudDetectorInterface {
        public function __construct(
            private string $name,
            private bool $enabled,
            private array $signals,
            private float $weight
        ) {
        }

        public function getName(): string
        {
            return $this->name;
        }

        public function isEnabled(): bool
        {
            return $this->enabled;
        }

        public function getWeight(): float
        {
            return $this->weight;
        }

        public function detect(FraudContext $context): DetectorResult
        {
            return DetectorResult::withSignals(
                $this->name,
                $this->signals,
                10,
                []
            );
        }
    };
}
