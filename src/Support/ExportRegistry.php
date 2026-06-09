<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Support;

use AIArmada\Inventory\Contracts\ExportInterface;
use InvalidArgumentException;

final class ExportRegistry
{
    /** @var array<string, ExportInterface> */
    private array $exports = [];

    public function register(ExportInterface $export): void
    {
        $this->exports[$export->type()] = $export;
    }

    public function get(string $type): ExportInterface
    {
        if (! isset($this->exports[$type])) {
            throw new InvalidArgumentException("Export [{$type}] is not registered.");
        }

        return $this->exports[$type];
    }

    public function has(string $type): bool
    {
        return isset($this->exports[$type]);
    }

    /**
     * @return array<string, ExportInterface>
     */
    public function all(): array
    {
        return $this->exports;
    }
}
