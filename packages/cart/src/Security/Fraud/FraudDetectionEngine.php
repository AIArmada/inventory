<?php

declare(strict_types=1);

namespace AIArmada\Cart\Security\Fraud;

use AIArmada\Cart\Cart;
use Illuminate\Support\Collection;

/**
 * Central fraud detection engine that orchestrates multiple detectors.
 *
 * Analyzes carts for potential fraud signals using pluggable detector strategies.
 * Returns aggregated risk scores and detailed signals for review.
 */
final class FraudDetectionEngine
{
    /**
     * Risk score thresholds.
     */
    public const THRESHOLD_LOW = 30;

    public const THRESHOLD_MEDIUM = 60;

    public const THRESHOLD_HIGH = 80;

    /**
     * @var array<FraudDetectorInterface>
     */
    private array $detectors = [];

    /**
     * @var array<string, mixed>
     */
    private array $configuration = [];

    public function __construct(
        private readonly FraudSignalCollector $signalCollector
    ) {
        $this->loadConfiguration();
    }

    /**
     * Register a fraud detector.
     */
    public function registerDetector(FraudDetectorInterface $detector): self
    {
        $this->detectors[] = $detector;

        return $this;
    }

    /**
     * Register multiple detectors at once.
     *
     * @param  array<FraudDetectorInterface>  $detectors
     */
    public function registerDetectors(array $detectors): self
    {
        foreach ($detectors as $detector) {
            $this->registerDetector($detector);
        }

        return $this;
    }

    /**
     * Analyze a cart for fraud signals.
     */
    public function analyze(Cart $cart, ?string $userId = null, ?string $ipAddress = null): FraudAnalysisResult
    {
        $context = new FraudContext(
            cart: $cart,
            userId: $userId,
            ipAddress: $ipAddress ?? request()->ip(),
            userAgent: request()->userAgent(),
            sessionId: session()->getId(),
            timestamp: now()
        );

        $signals = new Collection;
        $totalScore = 0;
        $detectorResults = [];

        foreach ($this->detectors as $detector) {
            if (! $detector->isEnabled()) {
                continue;
            }

            $result = $detector->detect($context);
            $detectorResults[$detector->getName()] = $result;

            foreach ($result->signals as $signal) {
                $signals->push($signal);
                $totalScore += $signal->score * $detector->getWeight();
            }
        }

        $normalizedScore = $this->normalizeScore($totalScore);
        $riskLevel = $this->determineRiskLevel($normalizedScore);

        $this->signalCollector->collect($context, $signals);

        return new FraudAnalysisResult(
            score: $normalizedScore,
            riskLevel: $riskLevel,
            signals: $signals->toArray(),
            detectorResults: $detectorResults,
            shouldBlock: $normalizedScore >= self::THRESHOLD_HIGH,
            shouldReview: $normalizedScore >= self::THRESHOLD_MEDIUM,
            recommendations: $this->generateRecommendations($signals, $riskLevel)
        );
    }

    /**
     * Quick check if a cart should be blocked.
     */
    public function shouldBlock(Cart $cart, ?string $userId = null): bool
    {
        return $this->analyze($cart, $userId)->shouldBlock;
    }

    /**
     * Quick check if a cart requires manual review.
     */
    public function requiresReview(Cart $cart, ?string $userId = null): bool
    {
        $result = $this->analyze($cart, $userId);

        return $result->shouldReview && ! $result->shouldBlock;
    }

    /**
     * Get registered detectors.
     *
     * @return array<FraudDetectorInterface>
     */
    public function getDetectors(): array
    {
        return $this->detectors;
    }

    /**
     * Configure the engine.
     *
     * @param  array<string, mixed>  $configuration
     */
    public function configure(array $configuration): self
    {
        $this->configuration = array_merge($this->configuration, $configuration);

        return $this;
    }

    /**
     * Normalize the raw score to 0-100 range.
     */
    private function normalizeScore(float $rawScore): int
    {
        $maxPossibleScore = count($this->detectors) * 100;

        if ($maxPossibleScore === 0) {
            return 0;
        }

        return (int) min(100, ($rawScore / $maxPossibleScore) * 100);
    }

    /**
     * Determine risk level from score.
     */
    private function determineRiskLevel(int $score): string
    {
        if ($score >= self::THRESHOLD_HIGH) {
            return 'high';
        }

        if ($score >= self::THRESHOLD_MEDIUM) {
            return 'medium';
        }

        if ($score >= self::THRESHOLD_LOW) {
            return 'low';
        }

        return 'minimal';
    }

    /**
     * Generate recommendations based on detected signals.
     *
     * @return array<string>
     */
    private function generateRecommendations(Collection $signals, string $riskLevel): array
    {
        $recommendations = [];

        if ($riskLevel === 'high') {
            $recommendations[] = 'Block transaction and flag account for review';
            $recommendations[] = 'Consider requiring additional verification';
        }

        if ($riskLevel === 'medium') {
            $recommendations[] = 'Review transaction before processing';
            $recommendations[] = 'Consider requiring phone verification';
        }

        foreach ($signals as $signal) {
            if (isset($signal->recommendation)) {
                $recommendations[] = $signal->recommendation;
            }
        }

        return array_unique($recommendations);
    }

    /**
     * Load configuration from config file.
     */
    private function loadConfiguration(): void
    {
        $this->configuration = config('cart.fraud', [
            'enabled' => true,
            'block_threshold' => self::THRESHOLD_HIGH,
            'review_threshold' => self::THRESHOLD_MEDIUM,
        ]);
    }
}
