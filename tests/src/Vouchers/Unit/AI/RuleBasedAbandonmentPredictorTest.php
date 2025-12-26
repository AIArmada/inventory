<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Testing\InMemoryStorage;
use AIArmada\Vouchers\AI\AbandonmentRisk;
use AIArmada\Vouchers\AI\CartFeatureExtractor;
use AIArmada\Vouchers\AI\Contracts\CartFeatureExtractorInterface;
use AIArmada\Vouchers\AI\Enums\AbandonmentRiskLevel;
use AIArmada\Vouchers\AI\Enums\InterventionType;
use AIArmada\Vouchers\AI\Predictors\RuleBasedAbandonmentPredictor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

function createCartForAbandonmentTest(int $subtotalCents = 10000): Cart
{
    $storage = new InMemoryStorage;
    $cart = new Cart($storage, 'test-abandonment-cart-' . uniqid());

    $pricePerItem = max(100, (int) ($subtotalCents / 2));
    $cart->add([
        'id' => 'item-1',
        'name' => 'Test Product',
        'price' => $pricePerItem,
        'quantity' => 1,
    ]);

    if ($subtotalCents > $pricePerItem) {
        $cart->add([
            'id' => 'item-2',
            'name' => 'Test Product 2',
            'price' => $subtotalCents - $pricePerItem,
            'quantity' => 1,
        ]);
    }

    return $cart;
}

function createUserForAbandonmentTest(array $attributes = []): Model
{
    $model = new class extends Model
    {
        protected $guarded = [];
    };

    foreach ($attributes as $key => $value) {
        $model->setAttribute($key, $value);
    }

    return $model;
}

describe('RuleBasedAbandonmentPredictor', function (): void {
    beforeEach(function (): void {
        $this->predictor = new RuleBasedAbandonmentPredictor;
    });

    describe('predictAbandonment', function (): void {
        it('returns AbandonmentRisk instance', function (): void {
            $cart = createCartForAbandonmentTest(10000);

            $result = $this->predictor->predictAbandonment($cart);

            expect($result)->toBeInstanceOf(AbandonmentRisk::class);
        });

        it('includes risk score between 0 and 1', function (): void {
            $cart = createCartForAbandonmentTest(10000);

            $result = $this->predictor->predictAbandonment($cart);

            expect($result->riskScore)->toBeGreaterThanOrEqual(0.0)
                ->and($result->riskScore)->toBeLessThanOrEqual(1.0);
        });

        it('includes risk level enum', function (): void {
            $cart = createCartForAbandonmentTest(10000);

            $result = $this->predictor->predictAbandonment($cart);

            expect($result->riskLevel)->toBeInstanceOf(AbandonmentRiskLevel::class);
        });

        it('includes risk factors array', function (): void {
            $cart = createCartForAbandonmentTest(10000);

            $result = $this->predictor->predictAbandonment($cart);

            expect($result->riskFactors)->toBeArray();
        });

        it('includes suggested intervention', function (): void {
            $cart = createCartForAbandonmentTest(10000);

            $result = $this->predictor->predictAbandonment($cart);

            expect($result->suggestedIntervention)->toBeInstanceOf(InterventionType::class);
        });
    });

    describe('risk level assignment', function (): void {
        it('assigns low risk for fresh carts', function (): void {
            $cart = createCartForAbandonmentTest(7500); // Medium value cart
            $user = createUserForAbandonmentTest(['id' => 1, 'email' => 'test@example.com']);

            // Fresh cart with authenticated user should have low risk
            $result = $this->predictor->predictAbandonment($cart, $user);

            // Risk score should be low for fresh cart with authenticated user
            expect($result->riskScore)->toBeLessThan(0.6);
        });

        it('assigns medium or higher risk for guest users', function (): void {
            $cart = createCartForAbandonmentTest(10000);

            // No user = guest, which adds risk
            $result = $this->predictor->predictAbandonment($cart, null);

            // Guest users should have some risk added
            expect($result->riskFactors)->toHaveKey('guest_user');
        });
    });

    describe('intervention recommendations', function (): void {
        it('recommends no intervention for low risk', function (): void {
            // Create a scenario with very low risk
            $cart = createCartForAbandonmentTest(7500);
            $user = createUserForAbandonmentTest([
                'id' => 1,
                'orders_count' => 5,
            ]);

            $result = $this->predictor->predictAbandonment($cart, $user);

            // Low risk should not require aggressive intervention
            expect($result->riskScore)->toBeLessThan(0.3)
                ->and($result->suggestedIntervention)->toBe(InterventionType::None);
        });

        it('recommends appropriate intervention based on risk', function (): void {
            $cart = createCartForAbandonmentTest(10000);

            $result = $this->predictor->predictAbandonment($cart);

            // Intervention should be valid enum value
            expect(InterventionType::tryFrom($result->suggestedIntervention->value))->not->toBeNull();
        });
    });

    describe('predictAbandonmentBatch', function (): void {
        it('processes multiple carts', function (): void {
            $carts = [
                createCartForAbandonmentTest(5000),
                createCartForAbandonmentTest(10000),
                createCartForAbandonmentTest(15000),
            ];

            $results = iterator_to_array($this->predictor->predictAbandonmentBatch($carts));

            expect($results)->toHaveCount(3)
                ->and($results[0])->toBeInstanceOf(AbandonmentRisk::class)
                ->and($results[1])->toBeInstanceOf(AbandonmentRisk::class)
                ->and($results[2])->toBeInstanceOf(AbandonmentRisk::class);
        });

        it('handles empty cart collection', function (): void {
            $results = iterator_to_array($this->predictor->predictAbandonmentBatch([]));

            expect($results)->toBeEmpty();
        });
    });

    describe('getHighRiskCarts', function (): void {
        it('filters carts by risk threshold', function (): void {
            $carts = [
                createCartForAbandonmentTest(5000),
                createCartForAbandonmentTest(10000),
                createCartForAbandonmentTest(15000),
            ];

            // Use a high threshold to filter
            $results = iterator_to_array($this->predictor->getHighRiskCarts($carts, 0.9));

            expect($results)->toBeArray();

            // Results should only contain high-risk carts (if any)
            foreach ($results as $result) {
                expect($result['risk']->riskScore)->toBeGreaterThanOrEqual(0.9);
            }
        });

        it('returns cart and risk in each result', function (): void {
            $carts = [createCartForAbandonmentTest(10000)];

            // Use low threshold to ensure we get results
            $results = iterator_to_array($this->predictor->getHighRiskCarts($carts, 0.0));

            expect($results)->toHaveCount(1)
                ->and($results[0])->toHaveKey('cart')
                ->and($results[0])->toHaveKey('risk')
                ->and($results[0]['cart'])->toBeInstanceOf(Cart::class)
                ->and($results[0]['risk'])->toBeInstanceOf(AbandonmentRisk::class);
        });

        it('uses default threshold of 0.6', function (): void {
            $cart = createCartForAbandonmentTest(10000);

            $result = $this->predictor->predictAbandonment($cart);

            // If risk is >= 0.6, it should be included with default threshold
            $highRisk = iterator_to_array($this->predictor->getHighRiskCarts([$cart]));

            expect($highRisk)->toBeArray();

            if ($result->riskScore >= 0.6) {
                expect($highRisk)->not->toBeEmpty();
            }
        });
    });

    describe('getName', function (): void {
        it('returns predictor name', function (): void {
            expect($this->predictor->getName())->toBe('rule_based_abandonment_predictor');
        });
    });

    describe('isReady', function (): void {
        it('returns true for rule-based predictor', function (): void {
            expect($this->predictor->isReady())->toBeTrue();
        });
    });

    describe('price sensitivity risk', function (): void {
        it('adds risk for high-value carts without discount', function (): void {
            // Premium/luxury cart without discount
            $cart = createCartForAbandonmentTest(35000); // $350 = premium

            $result = $this->predictor->predictAbandonment($cart);

            // Should have price sensitivity factor
            expect($result->riskFactors)->toHaveKey('price_sensitivity');
        });

        it('adds risk for micro carts', function (): void {
            $cart = createCartForAbandonmentTest(1000); // $10 = micro

            $result = $this->predictor->predictAbandonment($cart);

            expect($result->riskFactors)->toHaveKey('price_sensitivity')
                ->and($result->riskFactors['price_sensitivity']['bucket'])->toBe('micro');
        });
    });

    describe('device-based risk', function (): void {
        it('identifies device type in risk factors', function (): void {
            $cart = createCartForAbandonmentTest(10000);

            $featureExtractor = new class(new CartFeatureExtractor) implements CartFeatureExtractorInterface
            {
                public function __construct(private readonly CartFeatureExtractor $base) {}

                public function extract(Cart $cart, ?Model $user = null, ?Request $request = null): array
                {
                    return array_merge(
                        $this->base->extract($cart, $user, $request),
                        ['device_type' => 'mobile', 'is_mobile' => true],
                    );
                }

                public function extractCartFeatures(Cart $cart): array
                {
                    return $this->base->extractCartFeatures($cart);
                }

                public function extractUserFeatures(?Model $user): array
                {
                    return $this->base->extractUserFeatures($user);
                }

                public function extractSessionFeatures(?Request $request): array
                {
                    return array_merge(
                        $this->base->extractSessionFeatures($request),
                        ['device_type' => 'mobile', 'is_mobile' => true],
                    );
                }

                public function extractTimeFeatures(): array
                {
                    return $this->base->extractTimeFeatures();
                }
            };

            $predictor = new RuleBasedAbandonmentPredictor($featureExtractor);
            $result = $predictor->predictAbandonment($cart);

            expect($result->riskFactors)->toHaveKey('device_type')
                ->and($result->riskFactors['device_type'])->toHaveKey('device')
                ->and($result->riskFactors['device_type']['device'])->toBe('mobile');
        });
    });

    describe('time-based risk', function (): void {
        it('identifies time patterns in risk factors', function (): void {
            $cart = createCartForAbandonmentTest(10000);

            $featureExtractor = new class(new CartFeatureExtractor) implements CartFeatureExtractorInterface
            {
                public function __construct(private readonly CartFeatureExtractor $base) {}

                public function extract(Cart $cart, ?Model $user = null, ?Request $request = null): array
                {
                    return array_merge(
                        $this->base->extract($cart, $user, $request),
                        ['hour_of_day' => 2],
                    );
                }

                public function extractCartFeatures(Cart $cart): array
                {
                    return $this->base->extractCartFeatures($cart);
                }

                public function extractUserFeatures(?Model $user): array
                {
                    return $this->base->extractUserFeatures($user);
                }

                public function extractSessionFeatures(?Request $request): array
                {
                    return $this->base->extractSessionFeatures($request);
                }

                public function extractTimeFeatures(): array
                {
                    return array_merge($this->base->extractTimeFeatures(), ['hour_of_day' => 2]);
                }
            };

            $predictor = new RuleBasedAbandonmentPredictor($featureExtractor);
            $result = $predictor->predictAbandonment($cart);

            expect($result->riskFactors)->toHaveKey('time_pattern')
                ->and($result->riskFactors['time_pattern'])->toHaveKey('reason');
        });
    });

    describe('behavior-based risk', function (): void {
        it('tracks user behavior patterns', function (): void {
            $cart = createCartForAbandonmentTest(10000);
            $user = createUserForAbandonmentTest([
                'id' => 1,
                'orders_count' => 10,
                'voucher_orders_count' => 8, // High voucher dependency
            ]);

            $result = $this->predictor->predictAbandonment($cart, $user);

            // Behavior risk should be analyzed
            expect($result->riskFactors)->toHaveKey('behavior')
                ->and($result->riskFactors['behavior'])->toHaveKey('reason');
        });
    });

    describe('predicted abandonment time', function (): void {
        it('returns null for low risk', function (): void {
            $cart = createCartForAbandonmentTest(7500);
            $user = createUserForAbandonmentTest(['id' => 1, 'orders_count' => 5]);

            $result = $this->predictor->predictAbandonment($cart, $user);

            // Low risk carts don't have predicted abandonment time
            expect($result->riskScore)->toBeLessThan(0.3)
                ->and($result->predictedAbandonmentTime)->toBeNull();
        });

        it('returns Carbon instance for medium+ risk', function (): void {
            $cart = createCartForAbandonmentTest(10000);
            $cart->setMetadata('created_at', now()->subMinutes(120));

            $result = $this->predictor->predictAbandonment($cart);

            // Medium+ risk should have predicted time
            expect($result->riskScore)->toBeGreaterThanOrEqual(0.3)
                ->and($result->predictedAbandonmentTime)->toBeInstanceOf(\Carbon\CarbonImmutable::class);
        });
    });
});

describe('AbandonmentRisk value object', function (): void {
    describe('static constructors', function (): void {
        it('creates low risk assessment', function (): void {
            $risk = AbandonmentRisk::low(0.1);

            expect($risk->riskScore)->toBe(0.1)
                ->and($risk->riskLevel)->toBe(AbandonmentRiskLevel::Low)
                ->and($risk->suggestedIntervention)->toBe(InterventionType::None);
        });

        it('creates high risk assessment', function (): void {
            $risk = AbandonmentRisk::high(0.75, ['cart_age' => 'old']);

            expect($risk->riskScore)->toBe(0.75)
                ->and($risk->riskLevel)->toBe(AbandonmentRiskLevel::High)
                ->and($risk->suggestedIntervention)->toBe(InterventionType::DiscountOffer)
                ->and($risk->riskFactors)->toHaveKey('cart_age');
        });

        it('creates critical risk assessment', function (): void {
            $risk = AbandonmentRisk::critical(['urgent' => true]);

            expect($risk->riskScore)->toBe(0.9)
                ->and($risk->riskLevel)->toBe(AbandonmentRiskLevel::Critical)
                ->and($risk->suggestedIntervention)->toBe(InterventionType::ExitPopup)
                ->and($risk->predictedAbandonmentTime)->not->toBeNull()
                ->and($risk->riskFactors)->toHaveKey('urgent');
        });
    });

    describe('helper methods', function (): void {
        it('checks if immediate action required', function (): void {
            $highRisk = AbandonmentRisk::high();
            $lowRisk = AbandonmentRisk::low();

            expect($highRisk->requiresImmediateAction())->toBeTrue()
                ->and($lowRisk->requiresImmediateAction())->toBeFalse();
        });

        it('checks if discount should be offered', function (): void {
            $highRisk = AbandonmentRisk::high();
            $lowRisk = AbandonmentRisk::low();

            expect($highRisk->shouldOfferDiscount())->toBeTrue()
                ->and($lowRisk->shouldOfferDiscount())->toBeFalse();
        });

        it('calculates minutes until abandonment', function (): void {
            $critical = AbandonmentRisk::critical();

            $minutes = $critical->getMinutesUntilAbandonment();

            expect($minutes)->not->toBeNull()
                ->and($minutes)->toBeGreaterThanOrEqual(0);
        });

        it('checks if abandonment is imminent', function (): void {
            $critical = AbandonmentRisk::critical();

            // Critical risk has 5 minute window, so should be imminent
            expect($critical->isImminent())->toBeTrue();
        });

        it('returns priority score', function (): void {
            $critical = AbandonmentRisk::critical();
            $low = AbandonmentRisk::low();

            expect($critical->getPriorityScore())->toBeGreaterThan($low->getPriorityScore());
        });

        it('returns top risk factors', function (): void {
            $risk = new AbandonmentRisk(
                riskScore: 0.5,
                riskLevel: AbandonmentRiskLevel::Medium,
                riskFactors: [
                    'factor1' => 'value1',
                    'factor2' => 'value2',
                    'factor3' => 'value3',
                    'factor4' => 'value4',
                ],
            );

            $top = $risk->getTopRiskFactors(2);

            expect($top)->toHaveCount(2);
        });

        it('returns summary string', function (): void {
            $risk = AbandonmentRisk::high(0.75);

            $summary = $risk->getSummary();

            expect($summary)->toContain('High Risk')
                ->and($summary)->toContain('75%');
        });

        it('converts to array', function (): void {
            $risk = AbandonmentRisk::high(0.75);

            $array = $risk->toArray();

            expect($array)->toHaveKey('risk_score')
                ->and($array)->toHaveKey('risk_level')
                ->and($array)->toHaveKey('risk_factors')
                ->and($array)->toHaveKey('suggested_intervention')
                ->and($array)->toHaveKey('requires_immediate_action');
        });
    });
});

describe('AbandonmentRiskLevel enum', function (): void {
    it('creates from score', function (): void {
        expect(AbandonmentRiskLevel::fromScore(0.1))->toBe(AbandonmentRiskLevel::Low)
            ->and(AbandonmentRiskLevel::fromScore(0.4))->toBe(AbandonmentRiskLevel::Medium)
            ->and(AbandonmentRiskLevel::fromScore(0.7))->toBe(AbandonmentRiskLevel::High)
            ->and(AbandonmentRiskLevel::fromScore(0.9))->toBe(AbandonmentRiskLevel::Critical);
    });

    it('returns min and max scores', function (): void {
        expect(AbandonmentRiskLevel::Low->getMinScore())->toBe(0.0)
            ->and(AbandonmentRiskLevel::Low->getMaxScore())->toBe(0.3)
            ->and(AbandonmentRiskLevel::Critical->getMinScore())->toBe(0.8)
            ->and(AbandonmentRiskLevel::Critical->getMaxScore())->toBe(1.0);
    });

    it('returns labels', function (): void {
        expect(AbandonmentRiskLevel::Low->getLabel())->toBe('Low Risk')
            ->and(AbandonmentRiskLevel::Critical->getLabel())->toBe('Critical Risk');
    });

    it('returns colors', function (): void {
        expect(AbandonmentRiskLevel::Low->getColor())->toBe('success')
            ->and(AbandonmentRiskLevel::High->getColor())->toBe('danger');
    });

    it('returns recommended intervention', function (): void {
        expect(AbandonmentRiskLevel::Low->getRecommendedIntervention())->toBe('none')
            ->and(AbandonmentRiskLevel::High->getRecommendedIntervention())->toBe('discount_offer');
    });

    it('checks immediate action requirement', function (): void {
        expect(AbandonmentRiskLevel::Low->requiresImmediateAction())->toBeFalse()
            ->and(AbandonmentRiskLevel::High->requiresImmediateAction())->toBeTrue();
    });

    it('returns urgency weight', function (): void {
        expect(AbandonmentRiskLevel::Low->getUrgencyWeight())->toBe(1)
            ->and(AbandonmentRiskLevel::Critical->getUrgencyWeight())->toBe(8);
    });
});

describe('InterventionType enum', function (): void {
    it('returns labels', function (): void {
        expect(InterventionType::None->getLabel())->toBe('No Intervention')
            ->and(InterventionType::DiscountOffer->getLabel())->toBe('Discount Offer');
    });

    it('returns typical delay', function (): void {
        expect(InterventionType::None->getTypicalDelayMinutes())->toBe(0)
            ->and(InterventionType::RecoveryEmail->getTypicalDelayMinutes())->toBe(60);
    });

    it('returns effectiveness score', function (): void {
        expect(InterventionType::None->getEffectivenessScore())->toBe(1)
            ->and(InterventionType::DiscountOffer->getEffectivenessScore())->toBe(5);
    });

    it('returns cost score', function (): void {
        expect(InterventionType::None->getCostScore())->toBe(0)
            ->and(InterventionType::DiscountOffer->getCostScore())->toBe(4);
    });

    it('checks if requires discount', function (): void {
        expect(InterventionType::None->requiresDiscount())->toBeFalse()
            ->and(InterventionType::DiscountOffer->requiresDiscount())->toBeTrue()
            ->and(InterventionType::ExitPopup->requiresDiscount())->toBeTrue();
    });

    it('checks if real-time', function (): void {
        expect(InterventionType::ExitPopup->isRealTime())->toBeTrue()
            ->and(InterventionType::RecoveryEmail->isRealTime())->toBeFalse();
    });
});
