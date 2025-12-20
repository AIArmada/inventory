<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\AI;

use AIArmada\Vouchers\AI\Enums\AbandonmentRiskLevel;
use AIArmada\Vouchers\AI\Enums\InterventionType;
use Carbon\CarbonInterface;

/**
 * Value object representing abandonment risk assessment.
 *
 * @property-read float $riskScore Risk score (0.0 to 1.0)
 * @property-read AbandonmentRiskLevel $riskLevel Categorized risk level
 * @property-read array<string, mixed> $riskFactors Contributing risk factors
 * @property-read CarbonInterface|null $predictedAbandonmentTime When abandonment is likely
 * @property-read InterventionType $suggestedIntervention Recommended action
 */
final readonly class AbandonmentRisk
{
    /**
     * @param  array<string, mixed>  $riskFactors
     */
    public function __construct(
        public float $riskScore,
        public AbandonmentRiskLevel $riskLevel,
        public array $riskFactors = [],
        public ?CarbonInterface $predictedAbandonmentTime = null,
        public InterventionType $suggestedIntervention = InterventionType::None,
    ) {}

    /**
     * Create a low-risk assessment.
     */
    public static function low(float $score = 0.1): self
    {
        return new self(
            riskScore: $score,
            riskLevel: AbandonmentRiskLevel::Low,
            riskFactors: ['assessment' => 'low_risk'],
            suggestedIntervention: InterventionType::None,
        );
    }

    /**
     * Create a high-risk assessment.
     *
     * @param  array<string, mixed>  $factors
     */
    public static function high(float $score = 0.75, array $factors = []): self
    {
        return new self(
            riskScore: $score,
            riskLevel: AbandonmentRiskLevel::High,
            riskFactors: array_merge(['assessment' => 'high_risk'], $factors),
            suggestedIntervention: InterventionType::DiscountOffer,
        );
    }

    /**
     * Create a critical-risk assessment requiring immediate action.
     *
     * @param  array<string, mixed>  $factors
     */
    public static function critical(array $factors = []): self
    {
        return new self(
            riskScore: 0.9,
            riskLevel: AbandonmentRiskLevel::Critical,
            riskFactors: array_merge(['assessment' => 'critical_risk'], $factors),
            predictedAbandonmentTime: now()->addMinutes(5),
            suggestedIntervention: InterventionType::ExitPopup,
        );
    }

    /**
     * Check if immediate action is required.
     */
    public function requiresImmediateAction(): bool
    {
        return $this->riskLevel->requiresImmediateAction();
    }

    /**
     * Check if a discount should be offered.
     */
    public function shouldOfferDiscount(): bool
    {
        return $this->suggestedIntervention->requiresDiscount();
    }

    /**
     * Get minutes until predicted abandonment.
     */
    public function getMinutesUntilAbandonment(): ?int
    {
        if ($this->predictedAbandonmentTime === null) {
            return null;
        }

        $minutes = now()->diffInMinutes($this->predictedAbandonmentTime, false);

        return max(0, (int) $minutes);
    }

    /**
     * Check if abandonment is imminent (within 10 minutes).
     */
    public function isImminent(): bool
    {
        $minutes = $this->getMinutesUntilAbandonment();

        return $minutes !== null && $minutes <= 10;
    }

    /**
     * Get priority score for queue ordering.
     */
    public function getPriorityScore(): int
    {
        $base = $this->riskLevel->getUrgencyWeight();
        $timeBonus = $this->isImminent() ? 10 : 0;

        return $base + $timeBonus;
    }

    /**
     * Get the top risk factors.
     *
     * @return array<string, mixed>
     */
    public function getTopRiskFactors(int $limit = 3): array
    {
        return array_slice($this->riskFactors, 0, $limit, true);
    }

    /**
     * Get a summary of the risk assessment.
     */
    public function getSummary(): string
    {
        $pct = round($this->riskScore * 100);

        return "{$this->riskLevel->getLabel()}: {$pct}% risk score";
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'risk_score' => $this->riskScore,
            'risk_level' => $this->riskLevel->value,
            'risk_factors' => $this->riskFactors,
            'predicted_abandonment_time' => $this->predictedAbandonmentTime?->toIso8601String(),
            'suggested_intervention' => $this->suggestedIntervention->value,
            'requires_immediate_action' => $this->requiresImmediateAction(),
            'minutes_until_abandonment' => $this->getMinutesUntilAbandonment(),
        ];
    }
}
