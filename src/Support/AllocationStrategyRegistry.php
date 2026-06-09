<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Support;

use AIArmada\Inventory\Strategies\AllocationStrategyInterface;
use InvalidArgumentException;

final class AllocationStrategyRegistry
{
    /** @var array<string, AllocationStrategyInterface> */
    private array $strategies = [];

    public function register(AllocationStrategyInterface $strategy): void
    {
        $this->strategies[$strategy->name()] = $strategy;
    }

    public function get(string $name): AllocationStrategyInterface
    {
        if (! isset($this->strategies[$name])) {
            throw new InvalidArgumentException("Allocation strategy [{$name}] is not registered.");
        }

        return $this->strategies[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->strategies[$name]);
    }

    /**
     * @return array<string, AllocationStrategyInterface>
     */
    public function all(): array
    {
        return $this->strategies;
    }
}
