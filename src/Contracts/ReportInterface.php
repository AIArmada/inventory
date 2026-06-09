<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Contracts;

interface ReportInterface
{
    public function type(): string;

    public function name(): string;

    /**
     * @return array<string, mixed>
     */
    public function generate(array $filters = []): array;
}
