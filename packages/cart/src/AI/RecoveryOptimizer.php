<?php

declare(strict_types=1);

namespace AIArmada\Cart\AI;

use AIArmada\Cart\Cart;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * AI-powered recovery strategy optimizer.
 *
 * Analyzes historical recovery data to optimize intervention strategies
 * and maximize cart recovery rates.
 */
final class RecoveryOptimizer
{
    /**
     * @var array<string, mixed>
     */
    private array $configuration;

    /**
     * @var array<string, array<string, float>>
     */
    private array $strategyEffectiveness = [];

    public function __construct()
    {
        $this->configuration = config('cart.ai.recovery', [
            'enabled' => true,
            'min_samples_for_optimization' => 100,
            'learning_rate' => 0.1,
            'exploration_rate' => 0.2,
        ]);

        $this->loadStrategies();
    }

    /**
     * Get the optimal recovery strategy for a cart.
     */
    public function getOptimalStrategy(Cart $cart, AbandonmentPrediction $prediction): RecoveryStrategy
    {
        $context = $this->buildContext($cart, $prediction);
        $strategies = $this->getAvailableStrategies($context);

        if ($this->shouldExplore()) {
            return $this->selectRandomStrategy($strategies);
        }

        return $this->selectBestStrategy($strategies, $context);
    }

    /**
     * Record the outcome of a recovery attempt.
     */
    public function recordOutcome(
        string $cartId,
        string $strategyId,
        bool $recovered,
        ?int $timeToRecoveryMinutes = null,
        ?int $discountUsedCents = null
    ): void {
        $key = "recovery:outcomes:{$strategyId}";
        $outcomes = Cache::get($key, ['success' => 0, 'failure' => 0, 'total_time' => 0, 'total_discount' => 0]);

        if ($recovered) {
            $outcomes['success']++;
            $outcomes['total_time'] += $timeToRecoveryMinutes ?? 0;
            $outcomes['total_discount'] += $discountUsedCents ?? 0;
        } else {
            $outcomes['failure']++;
        }

        Cache::put($key, $outcomes, 86400 * 30);

        $this->updateStrategyEffectiveness($strategyId, $recovered);

        $this->persistOutcome($cartId, $strategyId, $recovered, $timeToRecoveryMinutes, $discountUsedCents);
    }

    /**
     * Get strategy statistics.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getStrategyStatistics(): array
    {
        $stats = [];

        foreach ($this->strategyEffectiveness as $strategyId => $data) {
            $total = ($data['success'] ?? 0) + ($data['failure'] ?? 0);
            $successRate = $total > 0 ? ($data['success'] ?? 0) / $total : 0;

            $stats[$strategyId] = [
                'strategy_id' => $strategyId,
                'success_rate' => round($successRate * 100, 2),
                'total_attempts' => $total,
                'successes' => $data['success'] ?? 0,
                'failures' => $data['failure'] ?? 0,
                'confidence' => $this->calculateConfidence($total),
            ];
        }

        uasort($stats, fn ($a, $b) => $b['success_rate'] <=> $a['success_rate']);

        return $stats;
    }

    /**
     * Optimize strategies based on accumulated data.
     */
    public function optimize(): OptimizationResult
    {
        $minSamples = $this->configuration['min_samples_for_optimization'] ?? 100;
        $learningRate = $this->configuration['learning_rate'] ?? 0.1;

        $improvementsApplied = [];
        $strategiesAnalyzed = 0;

        foreach ($this->strategyEffectiveness as $strategyId => $data) {
            $total = ($data['success'] ?? 0) + ($data['failure'] ?? 0);

            if ($total < $minSamples) {
                continue;
            }

            $strategiesAnalyzed++;
            $successRate = ($data['success'] ?? 0) / $total;

            if ($successRate < 0.1) {
                $improvementsApplied[] = [
                    'strategy' => $strategyId,
                    'action' => 'reduce_usage',
                    'reason' => "Low success rate: {$successRate}",
                ];
            } elseif ($successRate > 0.5) {
                $improvementsApplied[] = [
                    'strategy' => $strategyId,
                    'action' => 'increase_priority',
                    'reason' => "High success rate: {$successRate}",
                ];
            }
        }

        $this->saveStrategies();

        return new OptimizationResult(
            strategiesAnalyzed: $strategiesAnalyzed,
            improvementsApplied: $improvementsApplied,
            optimizedAt: now()
        );
    }

    /**
     * Get recovery recommendations for a specific cart value range.
     *
     * @return array<string, mixed>
     */
    public function getRecommendationsForValueRange(int $minCents, int $maxCents): array
    {
        $cacheKey = "recovery:recommendations:{$minCents}:{$maxCents}";

        return Cache::remember($cacheKey, 3600, function () use ($minCents, $maxCents) {
            $cartsTable = config('cart.database.table', 'carts');

            $data = DB::table($cartsTable)
                ->whereBetween('total', [$minCents, $maxCents])
                ->whereNotNull('checkout_abandoned_at')
                ->where('checkout_abandoned_at', '>', now()->subDays(30))
                ->selectRaw('
                    COUNT(*) as total_abandoned,
                    SUM(CASE WHEN recovered_at IS NOT NULL THEN 1 ELSE 0 END) as recovered,
                    AVG(recovery_attempts) as avg_attempts
                ')
                ->first();

            $recoveryRate = $data && $data->total_abandoned > 0
                ? $data->recovered / $data->total_abandoned
                : 0;

            return [
                'value_range' => ['min' => $minCents, 'max' => $maxCents],
                'total_abandoned' => $data->total_abandoned ?? 0,
                'recovered' => $data->recovered ?? 0,
                'recovery_rate' => round($recoveryRate * 100, 2),
                'avg_attempts' => round($data->avg_attempts ?? 0, 1),
                'recommended_strategy' => $this->getRecommendedStrategyForValueRange($minCents, $maxCents, $recoveryRate),
            ];
        });
    }

    /**
     * Build context for strategy selection.
     *
     * @return array<string, mixed>
     */
    private function buildContext(Cart $cart, AbandonmentPrediction $prediction): array
    {
        return [
            'cart_value' => $cart->getRawTotal(),
            'item_count' => $cart->countItems(),
            'risk_level' => $prediction->riskLevel,
            'probability' => $prediction->probability,
            'user_authenticated' => $cart->getMetadata('user_id') !== null,
            'time_of_day' => (int) now()->format('H'),
            'day_of_week' => (int) now()->format('N'),
        ];
    }

    /**
     * Get available strategies based on context.
     *
     * @param  array<string, mixed>  $context
     * @return array<RecoveryStrategy>
     */
    private function getAvailableStrategies(array $context): array
    {
        $strategies = [];

        $strategies[] = new RecoveryStrategy(
            id: 'email_reminder',
            name: 'Email Reminder',
            type: 'email',
            delayMinutes: 60,
            parameters: ['template' => 'cart_reminder'],
            priority: 1
        );

        if ($context['cart_value'] > 5000) {
            $strategies[] = new RecoveryStrategy(
                id: 'email_with_discount',
                name: 'Email with Discount',
                type: 'email',
                delayMinutes: 120,
                parameters: ['template' => 'cart_reminder_discount', 'discount_percentage' => 10],
                priority: 2
            );
        }

        if ($context['cart_value'] > 10000) {
            $strategies[] = new RecoveryStrategy(
                id: 'personalized_offer',
                name: 'Personalized Offer',
                type: 'email',
                delayMinutes: 180,
                parameters: ['template' => 'personalized_offer', 'dynamic_discount' => true],
                priority: 3
            );
        }

        if ($context['user_authenticated']) {
            $strategies[] = new RecoveryStrategy(
                id: 'push_notification',
                name: 'Push Notification',
                type: 'push',
                delayMinutes: 30,
                parameters: ['urgency' => 'normal'],
                priority: 1
            );
        }

        if ($context['risk_level'] === 'high') {
            $strategies[] = new RecoveryStrategy(
                id: 'exit_intent_popup',
                name: 'Exit Intent Popup',
                type: 'popup',
                delayMinutes: 0,
                parameters: ['show_discount' => true, 'discount_percentage' => 5],
                priority: 1
            );
        }

        return $strategies;
    }

    /**
     * Check if we should explore (try random strategy).
     */
    private function shouldExplore(): bool
    {
        $explorationRate = $this->configuration['exploration_rate'] ?? 0.2;

        return mt_rand(0, 100) / 100 < $explorationRate;
    }

    /**
     * Select a random strategy for exploration.
     *
     * @param  array<RecoveryStrategy>  $strategies
     */
    private function selectRandomStrategy(array $strategies): RecoveryStrategy
    {
        if (empty($strategies)) {
            return $this->getDefaultStrategy();
        }

        return $strategies[array_rand($strategies)];
    }

    /**
     * Select the best performing strategy.
     *
     * @param  array<RecoveryStrategy>  $strategies
     * @param  array<string, mixed>  $context
     */
    private function selectBestStrategy(array $strategies, array $context): RecoveryStrategy
    {
        if (empty($strategies)) {
            return $this->getDefaultStrategy();
        }

        $bestStrategy = null;
        $bestScore = -1;

        foreach ($strategies as $strategy) {
            $effectiveness = $this->strategyEffectiveness[$strategy->id] ?? ['success' => 0, 'failure' => 0];
            $total = $effectiveness['success'] + $effectiveness['failure'];

            if ($total === 0) {
                $score = 0.5;
            } else {
                $score = $effectiveness['success'] / $total;
            }

            $score *= (1 / $strategy->priority);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestStrategy = $strategy;
            }
        }

        return $bestStrategy ?? $strategies[0];
    }

    /**
     * Get default fallback strategy.
     */
    private function getDefaultStrategy(): RecoveryStrategy
    {
        return new RecoveryStrategy(
            id: 'default_email',
            name: 'Default Email Reminder',
            type: 'email',
            delayMinutes: 60,
            parameters: ['template' => 'cart_reminder'],
            priority: 5
        );
    }

    /**
     * Update strategy effectiveness based on outcome.
     */
    private function updateStrategyEffectiveness(string $strategyId, bool $success): void
    {
        if (! isset($this->strategyEffectiveness[$strategyId])) {
            $this->strategyEffectiveness[$strategyId] = ['success' => 0, 'failure' => 0];
        }

        if ($success) {
            $this->strategyEffectiveness[$strategyId]['success']++;
        } else {
            $this->strategyEffectiveness[$strategyId]['failure']++;
        }
    }

    /**
     * Calculate confidence based on sample size.
     */
    private function calculateConfidence(int $sampleSize): string
    {
        if ($sampleSize >= 1000) {
            return 'high';
        }

        if ($sampleSize >= 100) {
            return 'medium';
        }

        if ($sampleSize >= 10) {
            return 'low';
        }

        return 'insufficient';
    }

    /**
     * Get recommended strategy for value range.
     */
    private function getRecommendedStrategyForValueRange(int $minCents, int $maxCents, float $currentRecoveryRate): string
    {
        if ($maxCents > 50000) {
            return $currentRecoveryRate < 0.2 ? 'personalized_offer' : 'email_with_discount';
        }

        if ($maxCents > 10000) {
            return 'email_with_discount';
        }

        return 'email_reminder';
    }

    /**
     * Persist outcome to database.
     */
    private function persistOutcome(
        string $cartId,
        string $strategyId,
        bool $recovered,
        ?int $timeToRecoveryMinutes,
        ?int $discountUsedCents
    ): void {
        try {
            DB::table('cart_recovery_outcomes')->insert([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'cart_id' => $cartId,
                'strategy_id' => $strategyId,
                'recovered' => $recovered,
                'time_to_recovery_minutes' => $timeToRecoveryMinutes,
                'discount_used_cents' => $discountUsedCents,
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            // Table may not exist - silently ignore
        }
    }

    /**
     * Load strategy effectiveness from cache.
     */
    private function loadStrategies(): void
    {
        $saved = Cache::get('recovery:strategy_effectiveness', []);

        if (is_array($saved)) {
            $this->strategyEffectiveness = $saved;
        }
    }

    /**
     * Save strategy effectiveness to cache.
     */
    private function saveStrategies(): void
    {
        Cache::put('recovery:strategy_effectiveness', $this->strategyEffectiveness, 86400 * 30);
    }
}
