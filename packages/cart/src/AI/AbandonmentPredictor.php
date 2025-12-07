<?php

declare(strict_types=1);

namespace AIArmada\Cart\AI;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Storage\StorageInterface;
use DateTimeImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * AI-powered cart abandonment predictor.
 *
 * Uses machine learning signals and heuristics to predict
 * the likelihood of cart abandonment and recommend interventions.
 */
final class AbandonmentPredictor
{
    /**
     * Risk thresholds for abandonment probability.
     */
    public const RISK_LOW = 0.3;

    public const RISK_MEDIUM = 0.6;

    public const RISK_HIGH = 0.8;

    /**
     * Feature weights for prediction model.
     *
     * @var array<string, float>
     */
    private array $featureWeights = [
        'time_since_activity' => 0.25,
        'cart_value' => 0.15,
        'item_count' => 0.10,
        'user_history' => 0.20,
        'session_behavior' => 0.15,
        'device_type' => 0.05,
        'time_of_day' => 0.05,
        'checkout_progress' => 0.05,
    ];

    /**
     * @var array<string, mixed>
     */
    private array $configuration;

    private ?StorageInterface $storage;

    public function __construct(?StorageInterface $storage = null)
    {
        $this->storage = $storage;
        $this->configuration = config('cart.ai', []);
        $this->configuration = config('cart.ai.abandonment', [
            'enabled' => true,
            'inactivity_threshold_minutes' => 15,
            'high_value_threshold_cents' => 50000,
            'cache_predictions_seconds' => 300,
        ]);
    }

    /**
     * Predict abandonment probability for a cart.
     */
    public function predict(Cart $cart, ?string $userId = null): AbandonmentPrediction
    {
        $cacheKey = "cart:abandonment:{$cart->getId()}";

        return Cache::remember(
            $cacheKey,
            $this->configuration['cache_predictions_seconds'] ?? 300,
            fn () => $this->calculatePrediction($cart, $userId)
        );
    }

    /**
     * Batch predict for multiple carts.
     *
     * @param  Collection<int, Cart>  $carts
     * @return Collection<string, AbandonmentPrediction>
     */
    public function predictBatch(Collection $carts): Collection
    {
        return $carts->mapWithKeys(fn (Cart $cart) => [
            $cart->getId() => $this->predict($cart),
        ]);
    }

    /**
     * Get carts at high risk of abandonment.
     *
     * @return Collection<int, array{cart_id: string, identifier: string, probability: float, risk_level: string, last_activity: string|null, cart_total: int|float}>
     */
    public function getHighRiskCarts(int $limit = 50): Collection
    {
        $cartsTable = config('cart.database.table', 'carts');
        $inactivityThreshold = $this->configuration['inactivity_threshold_minutes'] ?? 15;

        $carts = DB::table($cartsTable)
            ->whereNotNull('last_activity_at')
            ->whereNull('checkout_abandoned_at')
            ->whereNull('recovered_at')
            ->where('last_activity_at', '<', now()->subMinutes($inactivityThreshold))
            ->where('last_activity_at', '>', now()->subHours(24))
            ->orderBy('last_activity_at', 'asc')
            ->limit($limit)
            ->get();

        return $carts->map(function ($cartRecord) {
            $features = $this->extractFeaturesFromRecord($cartRecord);
            $probability = $this->calculateProbability($features);

            return [
                'cart_id' => $cartRecord->id,
                'identifier' => $cartRecord->identifier,
                'probability' => $probability,
                'risk_level' => $this->getRiskLevel($probability),
                'last_activity' => $cartRecord->last_activity_at,
                'cart_total' => $cartRecord->total ?? 0,
            ];
        })->filter(fn ($cart) => $cart['probability'] >= self::RISK_MEDIUM);
    }

    /**
     * Train the model with historical data.
     *
     * @param  array<array{features: array<string, float>, abandoned: bool}>  $trainingData
     */
    public function train(array $trainingData): void
    {
        if (empty($trainingData)) {
            return;
        }

        $abandonedCount = 0;
        $completedCount = 0;
        $featureSums = array_fill_keys(array_keys($this->featureWeights), ['abandoned' => 0.0, 'completed' => 0.0]);

        foreach ($trainingData as $sample) {
            if ($sample['abandoned']) {
                $abandonedCount++;
                foreach ($sample['features'] as $feature => $value) {
                    if (isset($featureSums[$feature])) {
                        $featureSums[$feature]['abandoned'] += $value;
                    }
                }
            } else {
                $completedCount++;
                foreach ($sample['features'] as $feature => $value) {
                    if (isset($featureSums[$feature])) {
                        $featureSums[$feature]['completed'] += $value;
                    }
                }
            }
        }

        if ($abandonedCount > 0 && $completedCount > 0) {
            foreach ($featureSums as $feature => $sums) {
                $abandonedAvg = $sums['abandoned'] / $abandonedCount;
                $completedAvg = $sums['completed'] / $completedCount;

                if ($abandonedAvg + $completedAvg > 0) {
                    $this->featureWeights[$feature] = $abandonedAvg / ($abandonedAvg + $completedAvg);
                }
            }

            $this->normalizeWeights();
            $this->saveWeights();
        }
    }

    /**
     * Get current model weights.
     *
     * @return array<string, float>
     */
    public function getWeights(): array
    {
        return $this->featureWeights;
    }

    /**
     * Load weights from cache/storage.
     */
    public function loadWeights(): void
    {
        $savedWeights = Cache::get('cart:abandonment_model:weights');

        if ($savedWeights && is_array($savedWeights)) {
            $this->featureWeights = array_merge($this->featureWeights, $savedWeights);
        }
    }

    /**
     * Calculate prediction for a cart.
     */
    private function calculatePrediction(Cart $cart, ?string $userId): AbandonmentPrediction
    {
        $features = $this->extractFeatures($cart, $userId);
        $probability = $this->calculateProbability($features);
        $riskLevel = $this->getRiskLevel($probability);
        $interventions = $this->recommendInterventions($features, $probability);

        return new AbandonmentPrediction(
            cartId: $cart->getId(),
            probability: $probability,
            riskLevel: $riskLevel,
            features: $features,
            interventions: $interventions,
            predictedAt: now()
        );
    }

    /**
     * Extract features from a cart.
     *
     * @return array<string, float>
     */
    private function extractFeatures(Cart $cart, ?string $userId): array
    {
        $lastActivity = null;

        if ($this->storage) {
            $lastActivityStr = $this->storage->getLastActivityAt($cart->getIdentifier(), $cart->instance());
            $lastActivity = $lastActivityStr ? new DateTimeImmutable($lastActivityStr) : null;
        }

        $minutesSinceActivity = $lastActivity
            ? now()->diffInMinutes($lastActivity)
            : 0;

        $inactivityThreshold = $this->configuration['inactivity_threshold_minutes'] ?? 15;
        $highValueThreshold = $this->configuration['high_value_threshold_cents'] ?? 50000;

        return [
            'time_since_activity' => min(1.0, $minutesSinceActivity / 60),
            'cart_value' => min(1.0, $cart->getRawTotal() / $highValueThreshold),
            'item_count' => min(1.0, $cart->countItems() / 10),
            'user_history' => $userId ? $this->getUserHistoryScore($userId) : 0.5,
            'session_behavior' => $this->getSessionBehaviorScore($cart->getIdentifier()),
            'device_type' => $this->getDeviceTypeScore(),
            'time_of_day' => $this->getTimeOfDayScore(),
            'checkout_progress' => $this->getCheckoutProgressScore($cart),
        ];
    }

    /**
     * Extract features from a database record.
     *
     * @return array<string, float>
     */
    private function extractFeaturesFromRecord(object $record): array
    {
        $lastActivity = $record->last_activity_at ? strtotime($record->last_activity_at) : time();
        $minutesSinceActivity = (time() - $lastActivity) / 60;

        $highValueThreshold = $this->configuration['high_value_threshold_cents'] ?? 50000;
        $cartTotal = $record->total ?? 0;

        return [
            'time_since_activity' => min(1.0, $minutesSinceActivity / 60),
            'cart_value' => min(1.0, $cartTotal / $highValueThreshold),
            'item_count' => 0.3,
            'user_history' => $record->user_id ? 0.4 : 0.6,
            'session_behavior' => 0.5,
            'device_type' => 0.5,
            'time_of_day' => $this->getTimeOfDayScore(),
            'checkout_progress' => $record->checkout_started_at ? 0.3 : 0.7,
        ];
    }

    /**
     * Calculate abandonment probability from features.
     *
     * @param  array<string, float>  $features
     */
    private function calculateProbability(array $features): float
    {
        $score = 0.0;

        foreach ($this->featureWeights as $feature => $weight) {
            $featureValue = $features[$feature] ?? 0.5;
            $score += $featureValue * $weight;
        }

        return min(1.0, max(0.0, $score));
    }

    /**
     * Get risk level from probability.
     */
    private function getRiskLevel(float $probability): string
    {
        if ($probability >= self::RISK_HIGH) {
            return 'high';
        }

        if ($probability >= self::RISK_MEDIUM) {
            return 'medium';
        }

        if ($probability >= self::RISK_LOW) {
            return 'low';
        }

        return 'minimal';
    }

    /**
     * Recommend interventions based on features and probability.
     *
     * @param  array<string, float>  $features
     * @return array<Intervention>
     */
    private function recommendInterventions(array $features, float $probability): array
    {
        $interventions = [];

        if ($probability >= self::RISK_HIGH) {
            if ($features['cart_value'] > 0.5) {
                $interventions[] = new Intervention(
                    type: 'discount',
                    priority: 1,
                    message: 'Offer a time-limited discount to encourage completion',
                    parameters: ['discount_percentage' => 10, 'valid_minutes' => 30]
                );
            }

            $interventions[] = new Intervention(
                type: 'email',
                priority: 2,
                message: 'Send abandonment recovery email',
                parameters: ['delay_minutes' => 60, 'template' => 'cart_recovery_urgent']
            );
        }

        if ($probability >= self::RISK_MEDIUM && $features['checkout_progress'] > 0.5) {
            $interventions[] = new Intervention(
                type: 'exit_intent',
                priority: 1,
                message: 'Show exit intent popup with incentive',
                parameters: ['popup_type' => 'exit_intent', 'show_discount' => true]
            );
        }

        if ($features['time_since_activity'] > 0.5) {
            $interventions[] = new Intervention(
                type: 'push_notification',
                priority: 3,
                message: 'Send push notification reminder',
                parameters: ['delay_minutes' => 15]
            );
        }

        usort($interventions, fn ($a, $b) => $a->priority <=> $b->priority);

        return $interventions;
    }

    /**
     * Get user history score (higher = more likely to abandon).
     */
    private function getUserHistoryScore(string $userId): float
    {
        $cacheKey = "user:abandonment_rate:{$userId}";

        return Cache::remember($cacheKey, 3600, function () use ($userId) {
            $cartsTable = config('cart.database.table', 'carts');

            $stats = DB::table($cartsTable)
                ->where('user_id', $userId)
                ->selectRaw('COUNT(*) as total')
                ->selectRaw('SUM(CASE WHEN checkout_abandoned_at IS NOT NULL THEN 1 ELSE 0 END) as abandoned')
                ->first();

            if (! $stats || $stats->total === 0) {
                return 0.5;
            }

            return $stats->abandoned / $stats->total;
        });
    }

    /**
     * Get session behavior score.
     */
    private function getSessionBehaviorScore(string $cartId): float
    {
        $key = "cart:session_behavior:{$cartId}";
        $behavior = Cache::get($key, []);

        if (empty($behavior)) {
            return 0.5;
        }

        $pageViews = $behavior['page_views'] ?? 0;
        $timeOnSite = $behavior['time_on_site_seconds'] ?? 0;
        $addRemoveRatio = $behavior['add_remove_ratio'] ?? 1.0;

        $score = 0.5;

        if ($pageViews < 3) {
            $score += 0.2;
        }

        if ($timeOnSite < 120) {
            $score += 0.1;
        }

        if ($addRemoveRatio < 0.5) {
            $score += 0.2;
        }

        return min(1.0, $score);
    }

    /**
     * Get device type score (mobile has higher abandonment).
     */
    private function getDeviceTypeScore(): float
    {
        $userAgent = request()->userAgent() ?? '';

        if (preg_match('/mobile|android|iphone|ipad/i', $userAgent)) {
            return 0.7;
        }

        if (preg_match('/tablet/i', $userAgent)) {
            return 0.5;
        }

        return 0.3;
    }

    /**
     * Get time of day score.
     */
    private function getTimeOfDayScore(): float
    {
        $hour = (int) now()->format('H');

        if ($hour >= 22 || $hour < 6) {
            return 0.7;
        }

        if ($hour >= 9 && $hour <= 17) {
            return 0.4;
        }

        return 0.5;
    }

    /**
     * Get checkout progress score.
     */
    private function getCheckoutProgressScore(Cart $cart): float
    {
        $checkoutStep = $cart->getMetadata('checkout_step');

        if ($checkoutStep === null) {
            return 0.8;
        }

        $steps = ['cart' => 0.8, 'shipping' => 0.6, 'payment' => 0.4, 'review' => 0.2];

        return $steps[$checkoutStep] ?? 0.5;
    }

    /**
     * Normalize weights to sum to 1.
     */
    private function normalizeWeights(): void
    {
        $sum = array_sum($this->featureWeights);

        if ($sum > 0) {
            foreach ($this->featureWeights as $feature => $weight) {
                $this->featureWeights[$feature] = $weight / $sum;
            }
        }
    }

    /**
     * Save weights to cache/storage.
     */
    private function saveWeights(): void
    {
        Cache::put('cart:abandonment_model:weights', $this->featureWeights, 86400 * 30);
    }
}
