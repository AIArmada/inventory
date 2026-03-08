<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Exports;

/**
 * Contract for exportable inventory data.
 */
interface ExportableInterface
{
    /**
     * Get the export headers.
     *
     * @return array<int, string>
     */
    public function getHeaders(): array;

    /**
     * Get the export data rows.
     *
     * @return iterable<int, array<int, mixed>>
     */
    public function getRows(): iterable;

    /**
     * Get the export filename (without extension).
     */
    public function getFilename(): string;
}
