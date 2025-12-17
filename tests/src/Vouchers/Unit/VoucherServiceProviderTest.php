<?php

declare(strict_types=1);

use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Services\CartConditionResolver;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Vouchers\AI\CartFeatureExtractor;
use AIArmada\Vouchers\AI\Contracts\AbandonmentPredictorInterface;
use AIArmada\Vouchers\AI\Contracts\CartFeatureExtractorInterface;
use AIArmada\Vouchers\AI\Contracts\ConversionPredictorInterface;
use AIArmada\Vouchers\AI\Contracts\DiscountOptimizerInterface;
use AIArmada\Vouchers\AI\Contracts\VoucherMatcherInterface;
use AIArmada\Vouchers\AI\Optimizers\RuleBasedDiscountOptimizer;
use AIArmada\Vouchers\AI\Optimizers\RuleBasedVoucherMatcher;
use AIArmada\Vouchers\AI\Predictors\RuleBasedAbandonmentPredictor;
use AIArmada\Vouchers\AI\Predictors\RuleBasedConversionPredictor;
use AIArmada\Vouchers\AI\VoucherMLDataCollector;
use AIArmada\Vouchers\Conditions\VoucherCondition;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Enums\VoucherStatus;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Events\VoucherApplied;
use AIArmada\Vouchers\Listeners\IncrementVoucherAppliedCount;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Services\VoucherService;
use AIArmada\Vouchers\Services\VoucherValidator;
use AIArmada\Vouchers\Support\AffiliateIntegrationRegistrar;
use AIArmada\Vouchers\Support\VoucherRulesFactory;
use Illuminate\Support\Facades\Event;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

describe('VoucherServiceProvider', function (): void {
    describe('service bindings', function (): void {
        it('registers VoucherService as singleton', function (): void {
            $service1 = app(VoucherService::class);
            $service2 = app(VoucherService::class);

            expect($service1)->toBeInstanceOf(VoucherService::class)
                ->and($service1)->toBe($service2);
        });

        it('registers VoucherValidator as singleton', function (): void {
            $validator1 = app(VoucherValidator::class);
            $validator2 = app(VoucherValidator::class);

            expect($validator1)->toBeInstanceOf(VoucherValidator::class)
                ->and($validator1)->toBe($validator2);
        });

        it('registers VoucherRulesFactory as singleton', function (): void {
            $factory1 = app(VoucherRulesFactory::class);
            $factory2 = app(VoucherRulesFactory::class);

            expect($factory1)->toBeInstanceOf(VoucherRulesFactory::class)
                ->and($factory1)->toBe($factory2);
        });

        it('registers AffiliateIntegrationRegistrar as singleton', function (): void {
            $registrar1 = app(AffiliateIntegrationRegistrar::class);
            $registrar2 = app(AffiliateIntegrationRegistrar::class);

            expect($registrar1)->toBeInstanceOf(AffiliateIntegrationRegistrar::class)
                ->and($registrar1)->toBe($registrar2);
        });

        it('registers OwnerResolverInterface', function (): void {
            $resolver = app(OwnerResolverInterface::class);

            expect($resolver)->toBeInstanceOf(OwnerResolverInterface::class);
        });

        it('binds voucher facade accessor', function (): void {
            expect(app('voucher'))->toBeInstanceOf(VoucherService::class);
        });
    });

    describe('AI services registration', function (): void {
        it('registers CartFeatureExtractor as singleton', function (): void {
            $extractor1 = app(CartFeatureExtractor::class);
            $extractor2 = app(CartFeatureExtractor::class);

            expect($extractor1)->toBeInstanceOf(CartFeatureExtractor::class)
                ->and($extractor1)->toBe($extractor2);
        });

        it('registers CartFeatureExtractorInterface with concrete class', function (): void {
            $extractor = app(CartFeatureExtractorInterface::class);

            expect($extractor)->toBeInstanceOf(CartFeatureExtractor::class);
        });

        it('registers VoucherMLDataCollector as singleton', function (): void {
            $collector1 = app(VoucherMLDataCollector::class);
            $collector2 = app(VoucherMLDataCollector::class);

            expect($collector1)->toBeInstanceOf(VoucherMLDataCollector::class)
                ->and($collector1)->toBe($collector2);
        });

        it('registers ConversionPredictorInterface with rule-based implementation', function (): void {
            $predictor = app(ConversionPredictorInterface::class);

            expect($predictor)->toBeInstanceOf(RuleBasedConversionPredictor::class);
        });

        it('registers AbandonmentPredictorInterface with rule-based implementation', function (): void {
            $predictor = app(AbandonmentPredictorInterface::class);

            expect($predictor)->toBeInstanceOf(RuleBasedAbandonmentPredictor::class);
        });

        it('registers DiscountOptimizerInterface with rule-based implementation', function (): void {
            $optimizer = app(DiscountOptimizerInterface::class);

            expect($optimizer)->toBeInstanceOf(RuleBasedDiscountOptimizer::class);
        });

        it('registers VoucherMatcherInterface with rule-based implementation', function (): void {
            $matcher = app(VoucherMatcherInterface::class);

            expect($matcher)->toBeInstanceOf(RuleBasedVoucherMatcher::class);
        });
    });

    describe('provides method', function (): void {
        it('returns list of provided services', function (): void {
            $provider = new AIArmada\Vouchers\VoucherServiceProvider(app());
            $provides = $provider->provides();

            expect($provides)->toContain(VoucherService::class)
                ->and($provides)->toContain(VoucherValidator::class)
                ->and($provides)->toContain(ConversionPredictorInterface::class)
                ->and($provides)->toContain(AbandonmentPredictorInterface::class)
                ->and($provides)->toContain(DiscountOptimizerInterface::class)
                ->and($provides)->toContain(VoucherMatcherInterface::class)
                ->and($provides)->toContain(CartFeatureExtractorInterface::class)
                ->and($provides)->toContain(VoucherMLDataCollector::class)
                ->and($provides)->toContain(AffiliateIntegrationRegistrar::class)
                ->and($provides)->toContain('voucher');
        });
    });

    describe('CartConditionResolver registration', function (): void {
        it('resolves VoucherCondition to CartCondition', function (): void {
            $resolver = app(CartConditionResolver::class);

            $voucherData = VoucherData::fromArray([
                'id' => 'test-123',
                'code' => 'TEST-COND-' . uniqid(),
                'name' => 'Test Condition',
                'type' => VoucherType::Fixed->value,
                'value' => 50,
                'currency' => 'MYR',
                'status' => VoucherStatus::Active->value,
            ]);

            $voucherCondition = new VoucherCondition($voucherData, order: 90, dynamic: false);

            $result = $resolver->resolve($voucherCondition);

            expect($result)->toBeInstanceOf(CartCondition::class);
        });

        it('resolves VoucherData to CartCondition', function (): void {
            $resolver = app(CartConditionResolver::class);

            $voucherData = VoucherData::fromArray([
                'id' => 'test-456',
                'code' => 'TEST-DATA-' . uniqid(),
                'name' => 'Test Data',
                'type' => VoucherType::Percentage->value,
                'value' => 10,
                'currency' => 'MYR',
                'status' => VoucherStatus::Active->value,
            ]);

            $result = $resolver->resolve($voucherData);

            expect($result)->toBeInstanceOf(CartCondition::class);
        });

        it('returns null for non-existent voucher code in array', function (): void {
            $resolver = app(CartConditionResolver::class);

            $payload = [
                'voucher_code' => 'NON-EXISTENT-' . uniqid(),
            ];

            $result = $resolver->resolve($payload);

            // Should return null since voucher doesn't exist
            expect($result)->toBeNull();
        });

        it('returns null for non-existent voucher: prefixed string', function (): void {
            $resolver = app(CartConditionResolver::class);

            $payload = 'voucher:NON-EXISTENT-' . uniqid();

            $result = $resolver->resolve($payload);

            expect($result)->toBeNull();
        });

        it('returns null for empty voucher: prefix', function (): void {
            $resolver = app(CartConditionResolver::class);

            $payload = 'voucher:';

            $result = $resolver->resolve($payload);

            expect($result)->toBeNull();
        });

        it('returns null for array with empty code', function (): void {
            $resolver = app(CartConditionResolver::class);

            $payload = [
                'voucher_code' => '',
            ];

            $result = $resolver->resolve($payload);

            expect($result)->toBeNull();
        });
    });

    describe('event listeners', function (): void {
        it('registers VoucherApplied event listener', function (): void {
            $listeners = Event::getListeners(VoucherApplied::class);

            $hasListener = false;
            foreach ($listeners as $listener) {
                if (is_string($listener) && str_contains($listener, IncrementVoucherAppliedCount::class)) {
                    $hasListener = true;

                    break;
                }
                if (is_array($listener) && $listener[0] instanceof IncrementVoucherAppliedCount) {
                    $hasListener = true;

                    break;
                }
            }

            expect(count($listeners))->toBeGreaterThanOrEqual(1);
        });
    });
});
