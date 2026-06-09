<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Support;

use AIArmada\Inventory\Contracts\ReportInterface;
use InvalidArgumentException;

final class ReportRegistry
{
    /** @var array<string, ReportInterface> */
    private array $reports = [];

    public function register(ReportInterface $report): void
    {
        $this->reports[$report->type()] = $report;
    }

    public function get(string $type): ReportInterface
    {
        if (! isset($this->reports[$type])) {
            throw new InvalidArgumentException("Report [{$type}] is not registered.");
        }

        return $this->reports[$type];
    }

    public function has(string $type): bool
    {
        return isset($this->reports[$type]);
    }

    /**
     * @return array<string, ReportInterface>
     */
    public function all(): array
    {
        return $this->reports;
    }
}
