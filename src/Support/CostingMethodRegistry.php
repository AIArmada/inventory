<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Support;

use AIArmada\Inventory\Contracts\CostingMethodInterface;
use AIArmada\Inventory\Enums\CostingMethod;
use InvalidArgumentException;

final class CostingMethodRegistry
{
    /** @var array<string, CostingMethodInterface> */
    private array $methods = [];

    public function register(CostingMethodInterface $method): void
    {
        $this->methods[$method->supports()->value] = $method;
    }

    public function get(CostingMethod $method): CostingMethodInterface
    {
        $key = $method->value;

        if (! isset($this->methods[$key])) {
            throw new InvalidArgumentException("Costing method [{$method->value}] is not registered.");
        }

        return $this->methods[$key];
    }

    public function has(CostingMethod $method): bool
    {
        return isset($this->methods[$method->value]);
    }

    /**
     * @return array<string, CostingMethodInterface>
     */
    public function all(): array
    {
        return $this->methods;
    }
}
