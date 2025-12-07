<?php

declare(strict_types=1);

namespace AIArmada\Cart\AI;

use DateTimeInterface;

/**
 * Represents a cart recovery strategy.
 */
final readonly class RecoveryStrategy
{
    /**
     * @param  string  $id  Unique strategy identifier
     * @param  string  $name  Human-readable name
     * @param  string  $type  Strategy type: email, push, popup, sms
     * @param  int  $delayMinutes  Delay before executing strategy
     * @param  array<string, mixed>  $parameters  Strategy-specific parameters
     * @param  int  $priority  Priority (1 = highest)
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $type,
        public int $delayMinutes,
        public array $parameters = [],
        public int $priority = 1
    ) {}

    /**
     * Check if this is an immediate strategy.
     */
    public function isImmediate(): bool
    {
        return $this->delayMinutes === 0;
    }

    /**
     * Get the scheduled execution time.
     */
    public function getScheduledTime(): DateTimeInterface
    {
        return now()->addMinutes($this->delayMinutes);
    }

    /**
     * Check if strategy includes a discount.
     */
    public function hasDiscount(): bool
    {
        return isset($this->parameters['discount_percentage'])
            || isset($this->parameters['discount_amount'])
            || ($this->parameters['dynamic_discount'] ?? false);
    }

    /**
     * Get discount percentage if available.
     */
    public function getDiscountPercentage(): ?int
    {
        return $this->parameters['discount_percentage'] ?? null;
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'delay_minutes' => $this->delayMinutes,
            'parameters' => $this->parameters,
            'priority' => $this->priority,
            'is_immediate' => $this->isImmediate(),
            'has_discount' => $this->hasDiscount(),
        ];
    }
}
