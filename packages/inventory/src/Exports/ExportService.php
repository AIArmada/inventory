<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Exports;

use Generator;
use RuntimeException;

/**
 * Export service for generating CSV and other export formats.
 */
final class ExportService
{
    /**
     * Export to CSV string.
     */
    public function toCsv(ExportableInterface $export): string
    {
        $output = fopen('php://temp', 'r+');

        if ($output === false) {
            throw new RuntimeException('Failed to open temp stream for CSV export');
        }

        fputcsv($output, $export->getHeaders());

        foreach ($export->getRows() as $row) {
            fputcsv($output, $row);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv !== false ? $csv : '';
    }

    /**
     * Export to CSV file.
     */
    public function toCsvFile(ExportableInterface $export, string $path): string
    {
        $fullPath = $path . '/' . $export->getFilename() . '.csv';
        $output = fopen($fullPath, 'w');

        if ($output === false) {
            throw new RuntimeException("Failed to open file for CSV export: {$fullPath}");
        }

        fputcsv($output, $export->getHeaders());

        foreach ($export->getRows() as $row) {
            fputcsv($output, $row);
        }

        fclose($output);

        return $fullPath;
    }

    /**
     * Export to array for JSON serialization.
     *
     * @return array{headers: array<int, string>, rows: array<int, array<int, mixed>>, filename: string}
     */
    public function toArray(ExportableInterface $export): array
    {
        $rows = [];

        foreach ($export->getRows() as $row) {
            $rows[] = $row;
        }

        return [
            'headers' => $export->getHeaders(),
            'rows' => $rows,
            'filename' => $export->getFilename(),
        ];
    }

    /**
     * Stream export as a generator for large datasets.
     *
     * @return Generator<int, string>
     */
    public function stream(ExportableInterface $export): Generator
    {
        $output = fopen('php://temp', 'r+');

        if ($output === false) {
            throw new RuntimeException('Failed to open temp stream');
        }

        fputcsv($output, $export->getHeaders());
        rewind($output);
        yield stream_get_contents($output);
        fclose($output);

        foreach ($export->getRows() as $row) {
            $rowOutput = fopen('php://temp', 'r+');

            if ($rowOutput === false) {
                continue;
            }

            fputcsv($rowOutput, $row);
            rewind($rowOutput);
            yield stream_get_contents($rowOutput);
            fclose($rowOutput);
        }
    }

    /**
     * Get count of rows (useful for progress tracking).
     */
    public function getRowCount(ExportableInterface $export): int
    {
        $count = 0;

        foreach ($export->getRows() as $_) {
            $count++;
        }

        return $count;
    }
}
