<?php

declare(strict_types=1);

namespace AIArmada\Docs\Numbering\Strategies;

use AIArmada\Docs\Numbering\Contracts\DocumentNumberStrategy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Configurable uniqid-based number strategy.
 *
 * Allows per-doc-type overrides via `config('docs.types.<type>.numbering.format')`:
 * - prefix (default: INV/RCP/TKT/DOC)
 * - year_format (default: y)
 * - separator (default: -)
 * - suffix_length (default: 6)
 */
final class DefaultNumberStrategy implements DocumentNumberStrategy
{
    public function generate(string $docType): string
    {
        $globalFormat = config('docs.numbering.format', []);
        $typeConfig = config("docs.types.{$docType}.numbering", []);

        $format = array_replace($globalFormat, $typeConfig['format'] ?? []);

        $prefix = $typeConfig['prefix']
            ?? $format['prefix']
            ?? match ($docType) {
                'invoice' => 'INV',
                'receipt' => 'RCP',
                default => 'DOC',
            };

        $yearFormat = $format['year_format'] ?? 'y';
        $separator = array_key_exists('separator', $format) ? (string) $format['separator'] : '-';
        $suffixLength = (int) ($format['suffix_length'] ?? 6);

        $year = CarbonImmutable::now()->format($yearFormat);
        $suffix = $this->generateSuffix($suffixLength);

        return sprintf('%s%s%s%s', $prefix, $year, $separator, $suffix);
    }

    protected function generateSuffix(int $length): string
    {
        $length = max(1, min($length, 12));

        // uniqid ensures unique base, substr for deterministic length
        $random = Str::upper(Str::substr(uniqid('', true) . Str::random(8), -$length));

        return $random;
    }
}
