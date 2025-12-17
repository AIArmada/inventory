<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Security\Fraud\DetectorResult;
use AIArmada\Cart\Security\Fraud\FraudAnalysisResult;
use AIArmada\Cart\Security\Fraud\FraudContext;
use AIArmada\Cart\Security\Fraud\FraudSignal;
use AIArmada\Cart\Testing\InMemoryStorage;

describe('FraudSignal', function (): void {
    it('can be instantiated with all parameters', function (): void {
        $signal = new FraudSignal(
            type: 'price_manipulation',
            detector: 'PriceDetector',
            score: 75,
            message: 'Price was modified suspiciously',
            recommendation: 'Review transaction',
            metadata: ['original_price' => 1000, 'modified_price' => 100]
        );

        expect($signal->type)->toBe('price_manipulation')
            ->and($signal->detector)->toBe('PriceDetector')
            ->and($signal->score)->toBe(75)
            ->and($signal->message)->toBe('Price was modified suspiciously')
            ->and($signal->recommendation)->toBe('Review transaction')
            ->and($signal->metadata)->toHaveKey('original_price');
    });

    it('can be created with high factory method', function (): void {
        $signal = FraudSignal::high(
            type: 'critical_fraud',
            detector: 'CriticalDetector',
            message: 'Critical fraud detected'
        );

        expect($signal->score)->toBe(80)
            ->and($signal->type)->toBe('critical_fraud');
    });

    it('can be created with medium factory method', function (): void {
        $signal = FraudSignal::medium(
            type: 'suspicious_activity',
            detector: 'ActivityDetector',
            message: 'Suspicious activity detected'
        );

        expect($signal->score)->toBe(50)
            ->and($signal->type)->toBe('suspicious_activity');
    });

    it('can be created with low factory method', function (): void {
        $signal = FraudSignal::low(
            type: 'minor_concern',
            detector: 'MinorDetector',
            message: 'Minor concern detected'
        );

        expect($signal->score)->toBe(25)
            ->and($signal->type)->toBe('minor_concern');
    });

    it('converts to array', function (): void {
        $signal = new FraudSignal(
            type: 'test_signal',
            detector: 'TestDetector',
            score: 60,
            message: 'Test message',
            recommendation: 'Test recommendation',
            metadata: ['test' => 'data']
        );

        $array = $signal->toArray();

        expect($array)->toBeArray()
            ->and($array)->toHaveKeys(['type', 'detector', 'score', 'message', 'recommendation', 'metadata'])
            ->and($array['type'])->toBe('test_signal')
            ->and($array['score'])->toBe(60);
    });

    it('has nullable recommendation', function (): void {
        $signal = new FraudSignal(
            type: 'no_recommendation',
            detector: 'Detector',
            score: 30,
            message: 'Message without recommendation'
        );

        expect($signal->recommendation)->toBeNull();
    });

    it('has empty metadata by default', function (): void {
        $signal = new FraudSignal(
            type: 'minimal',
            detector: 'Detector',
            score: 10,
            message: 'Minimal signal'
        );

        expect($signal->metadata)->toBeEmpty();
    });
});

describe('FraudAnalysisResult', function (): void {
    it('can be instantiated', function (): void {
        $result = new FraudAnalysisResult(
            score: 50,
            riskLevel: 'medium',
            signals: [],
            detectorResults: [],
            shouldBlock: false,
            shouldReview: true,
            recommendations: ['Review order']
        );

        expect($result)->toBeInstanceOf(FraudAnalysisResult::class)
            ->and($result->score)->toBe(50)
            ->and($result->riskLevel)->toBe('medium')
            ->and($result->shouldBlock)->toBeFalse()
            ->and($result->shouldReview)->toBeTrue();
    });

    it('isClean returns true when minimal risk and no signals', function (): void {
        $result = new FraudAnalysisResult(
            score: 0,
            riskLevel: 'minimal',
            signals: [],
            detectorResults: [],
            shouldBlock: false,
            shouldReview: false,
            recommendations: []
        );

        expect($result->isClean())->toBeTrue();
    });

    it('isClean returns false when has signals', function (): void {
        $signal = new FraudSignal('test', 'Detector', 10, 'Low signal');
        $result = new FraudAnalysisResult(
            score: 10,
            riskLevel: 'minimal',
            signals: [$signal],
            detectorResults: [],
            shouldBlock: false,
            shouldReview: false,
            recommendations: []
        );

        expect($result->isClean())->toBeFalse();
    });

    it('groups signals by detector', function (): void {
        $signal1 = new FraudSignal('type1', 'DetectorA', 30, 'Message 1');
        $signal2 = new FraudSignal('type2', 'DetectorA', 40, 'Message 2');
        $signal3 = new FraudSignal('type3', 'DetectorB', 50, 'Message 3');

        $result = new FraudAnalysisResult(
            score: 50,
            riskLevel: 'medium',
            signals: [$signal1, $signal2, $signal3],
            detectorResults: [],
            shouldBlock: false,
            shouldReview: true,
            recommendations: []
        );

        $grouped = $result->getSignalsByDetector();

        expect($grouped)->toHaveKeys(['DetectorA', 'DetectorB'])
            ->and($grouped['DetectorA'])->toHaveCount(2)
            ->and($grouped['DetectorB'])->toHaveCount(1);
    });

    it('filters high score signals', function (): void {
        $lowSignal = new FraudSignal('low', 'Detector', 30, 'Low');
        $highSignal = new FraudSignal('high', 'Detector', 70, 'High');

        $result = new FraudAnalysisResult(
            score: 50,
            riskLevel: 'medium',
            signals: [$lowSignal, $highSignal],
            detectorResults: [],
            shouldBlock: false,
            shouldReview: true,
            recommendations: []
        );

        $highScoreSignals = $result->getHighScoreSignals(50);

        expect($highScoreSignals)->toHaveCount(1)
            ->and($highScoreSignals[1]->type)->toBe('high');
    });

    it('converts to array', function (): void {
        $result = new FraudAnalysisResult(
            score: 75,
            riskLevel: 'high',
            signals: [],
            detectorResults: [],
            shouldBlock: true,
            shouldReview: true,
            recommendations: ['Block transaction']
        );

        $array = $result->toArray();

        expect($array)->toHaveKeys(['score', 'risk_level', 'signal_count', 'should_block', 'should_review', 'recommendations', 'signals'])
            ->and($array['score'])->toBe(75)
            ->and($array['risk_level'])->toBe('high');
    });
});

describe('FraudContext', function (): void {
    beforeEach(function (): void {
        $storage = new InMemoryStorage;
        $this->cart = new Cart($storage, 'test-user');
        $this->cart->add('item-1', 'Product', 2500, 3);
    });

    it('can be instantiated', function (): void {
        $context = new FraudContext(
            cart: $this->cart,
            userId: 'user-123',
            ipAddress: '192.168.1.1',
            userAgent: 'Mozilla/5.0',
            sessionId: 'session-abc',
            timestamp: now()
        );

        expect($context)->toBeInstanceOf(FraudContext::class)
            ->and($context->userId)->toBe('user-123')
            ->and($context->ipAddress)->toBe('192.168.1.1');
    });

    it('returns cart id', function (): void {
        $context = new FraudContext(
            cart: $this->cart,
            userId: 'user-123',
            ipAddress: null,
            userAgent: null,
            sessionId: null,
            timestamp: now()
        );

        expect($context->getCartId())->not->toBeEmpty();
    });

    it('returns cart total', function (): void {
        $context = new FraudContext(
            cart: $this->cart,
            userId: null,
            ipAddress: null,
            userAgent: null,
            sessionId: null,
            timestamp: now()
        );

        expect($context->getCartTotal())->toBe(7500); // 2500 * 3
    });

    it('returns item count', function (): void {
        $context = new FraudContext(
            cart: $this->cart,
            userId: null,
            ipAddress: null,
            userAgent: null,
            sessionId: null,
            timestamp: now()
        );

        expect($context->getItemCount())->toBe(1);
    });

    it('returns total quantity', function (): void {
        $context = new FraudContext(
            cart: $this->cart,
            userId: null,
            ipAddress: null,
            userAgent: null,
            sessionId: null,
            timestamp: now()
        );

        expect($context->getTotalQuantity())->toBe(3);
    });

    it('checks if authenticated', function (): void {
        $authenticatedContext = new FraudContext(
            cart: $this->cart,
            userId: 'user-123',
            ipAddress: null,
            userAgent: null,
            sessionId: null,
            timestamp: now()
        );

        $unauthenticatedContext = new FraudContext(
            cart: $this->cart,
            userId: null,
            ipAddress: null,
            userAgent: null,
            sessionId: null,
            timestamp: now()
        );

        expect($authenticatedContext->isAuthenticated())->toBeTrue()
            ->and($unauthenticatedContext->isAuthenticated())->toBeFalse();
    });

    it('converts to array', function (): void {
        $context = new FraudContext(
            cart: $this->cart,
            userId: 'user-123',
            ipAddress: '10.0.0.1',
            userAgent: 'TestAgent',
            sessionId: 'session-xyz',
            timestamp: now()
        );

        $array = $context->toArray();

        expect($array)->toHaveKeys([
            'cart_id',
            'cart_total',
            'item_count',
            'total_quantity',
            'user_id',
            'ip_address',
            'user_agent',
            'session_id',
            'timestamp',
            'is_authenticated',
        ])
            ->and($array['user_id'])->toBe('user-123')
            ->and($array['is_authenticated'])->toBeTrue();
    });
});

describe('DetectorResult', function (): void {
    it('can be instantiated', function (): void {
        $result = new DetectorResult(
            detector: 'TestDetector',
            signals: [],
            passed: true,
            executionTimeMs: 50,
            debugInfo: ['key' => 'value']
        );

        expect($result)->toBeInstanceOf(DetectorResult::class)
            ->and($result->detector)->toBe('TestDetector')
            ->and($result->passed)->toBeTrue()
            ->and($result->executionTimeMs)->toBe(50);
    });

    it('creates passing result with factory', function (): void {
        $result = DetectorResult::pass('CleanDetector', 25);

        expect($result->detector)->toBe('CleanDetector')
            ->and($result->passed)->toBeTrue()
            ->and($result->signals)->toBeEmpty()
            ->and($result->executionTimeMs)->toBe(25);
    });

    it('creates result with signals using factory', function (): void {
        $signal = new FraudSignal('test', 'Detector', 40, 'Test message');
        $result = DetectorResult::withSignals(
            detector: 'SignalDetector',
            signals: [$signal],
            executionTimeMs: 100,
            debugInfo: ['reason' => 'suspicious']
        );

        expect($result->detector)->toBe('SignalDetector')
            ->and($result->passed)->toBeFalse()
            ->and($result->signals)->toHaveCount(1)
            ->and($result->debugInfo)->toHaveKey('reason');
    });

    it('calculates total score', function (): void {
        $signal1 = new FraudSignal('type1', 'Detector', 30, 'Message 1');
        $signal2 = new FraudSignal('type2', 'Detector', 50, 'Message 2');

        $result = DetectorResult::withSignals('ScoreDetector', [$signal1, $signal2]);

        expect($result->getTotalScore())->toBe(80);
    });

    it('returns zero total score for empty signals', function (): void {
        $result = DetectorResult::pass('EmptyDetector');

        expect($result->getTotalScore())->toBe(0);
    });

    it('gets highest severity signal', function (): void {
        $lowSignal = new FraudSignal('low', 'Detector', 20, 'Low');
        $highSignal = new FraudSignal('high', 'Detector', 80, 'High');
        $medSignal = new FraudSignal('med', 'Detector', 50, 'Med');

        $result = DetectorResult::withSignals('SeverityDetector', [$lowSignal, $highSignal, $medSignal]);

        $highest = $result->getHighestSeveritySignal();

        expect($highest)->not->toBeNull()
            ->and($highest->type)->toBe('high')
            ->and($highest->score)->toBe(80);
    });

    it('returns null for highest severity when no signals', function (): void {
        $result = DetectorResult::pass('EmptyDetector');

        expect($result->getHighestSeveritySignal())->toBeNull();
    });

    it('converts to array', function (): void {
        $signal = new FraudSignal('test', 'Detector', 40, 'Test');
        $result = DetectorResult::withSignals('ArrayDetector', [$signal], 75);

        $array = $result->toArray();

        expect($array)->toHaveKeys(['detector', 'passed', 'signal_count', 'total_score', 'execution_time_ms', 'signals'])
            ->and($array['detector'])->toBe('ArrayDetector')
            ->and($array['signal_count'])->toBe(1)
            ->and($array['total_score'])->toBe(40);
    });
});
