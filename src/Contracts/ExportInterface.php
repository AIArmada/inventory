<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Contracts;

interface ExportInterface
{
    public function type(): string;

    /**
     * @return array<int, string>
     */
    public function getHeaders(): array;

    /**
     * @return iterable<int, array<int, mixed>>
     */
    public function getRows(array $filters = []): iterable;

    public function getFilename(): string;
}
