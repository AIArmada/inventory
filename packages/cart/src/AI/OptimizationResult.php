<?php

declare(strict_types=1);

namespace AIArmada\Cart\AI;

use DateTimeInterface;

/**
 * Result of strategy optimization.
 */
final readonly class OptimizationResult
{
    /**
     * @param  int  $strategiesAnalyzed  Number of strategies analyzed
     * @param  array<array{strategy: string, action: string, reason: string}>  $improvementsApplied
     */
    public function __construct(
        public int $strategiesAnalyzed,
        public array $improvementsApplied,
        public DateTimeInterface $optimizedAt
    ) {}

    /**
     * Check if any improvements were made.
     */
    public function hasImprovements(): bool
    {
        return ! empty($this->improvementsApplied);
    }

    /**
     * Get improvement count.
     */
    public function getImprovementCount(): int
    {
        return count($this->improvementsApplied);
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'strategies_analyzed' => $this->strategiesAnalyzed,
            'improvements_applied' => $this->improvementsApplied,
            'improvement_count' => $this->getImprovementCount(),
            'optimized_at' => $this->optimizedAt->format('Y-m-d H:i:s'),
        ];
    }
}
