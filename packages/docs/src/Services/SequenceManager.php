<?php

declare(strict_types=1);

namespace AIArmada\Docs\Services;

use AIArmada\Docs\Enums\DocType;
use AIArmada\Docs\Enums\ResetFrequency;
use AIArmada\Docs\Models\DocSequence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Manages document sequence number generation with atomic operations.
 */
final class SequenceManager
{
    /**
     * Generate the next document number for a given type.
     *
     * @param  DocType|string  $docType  The document type
     * @param  Model|null  $owner  Optional owner for multi-tenant sequences
     */
    public function generate(DocType | string $docType, ?Model $owner = null): string
    {
        $type = $docType instanceof DocType ? $docType->value : $docType;

        return DB::transaction(function () use ($type, $owner): string {
            $sequence = $this->getActiveSequence($type, $owner);

            if (! $sequence) {
                $sequence = $this->createDefaultSequence($type, $owner);
            }

            return $sequence->generateNumber();
        });
    }

    /**
     * Get the active sequence for a document type.
     */
    public function getActiveSequence(string $docType, ?Model $owner = null): ?DocSequence
    {
        $query = DocSequence::query()
            ->where('doc_type', $docType)
            ->where('is_active', true);

        if ($owner) {
            $query->where('owner_type', $owner->getMorphClass())
                ->where('owner_id', $owner->getKey());
        } else {
            $query->whereNull('owner_type');
        }

        return $query->first();
    }

    /**
     * Create a default sequence for a document type.
     */
    public function createDefaultSequence(string $docType, ?Model $owner = null): DocSequence
    {
        $typeConfig = config("docs.types.{$docType}", []);
        $numberingConfig = $typeConfig['numbering'] ?? [];

        $prefix = $numberingConfig['prefix']
            ?? DocType::tryFrom($docType)?->defaultPrefix()
            ?? mb_strtoupper(mb_substr($docType, 0, 3));

        $data = [
            'name' => ucfirst(str_replace('_', ' ', $docType)) . ' Sequence',
            'doc_type' => $docType,
            'prefix' => $prefix,
            'format' => config('docs.numbering.format.default', '{PREFIX}-{YYMM}-{NUMBER}'),
            'reset_frequency' => ResetFrequency::Yearly,
            'start_number' => 1,
            'increment' => 1,
            'padding' => config('docs.numbering.format.suffix_length', 6),
            'is_active' => true,
        ];

        if ($owner) {
            $data['owner_type'] = $owner->getMorphClass();
            $data['owner_id'] = $owner->getKey();
        }

        return DocSequence::create($data);
    }

    /**
     * Preview what the next number would be without generating it.
     */
    public function preview(DocType | string $docType, ?Model $owner = null): string
    {
        $type = $docType instanceof DocType ? $docType->value : $docType;
        $sequence = $this->getActiveSequence($type, $owner);

        if (! $sequence) {
            // Create a temporary preview
            $prefix = DocType::tryFrom($type)?->defaultPrefix() ?? mb_strtoupper(mb_substr($type, 0, 3));
            $padding = config('docs.numbering.format.suffix_length', 6);
            $number = mb_str_pad('1', $padding, '0', STR_PAD_LEFT);

            return $prefix . '-' . now()->format('ym') . '-' . $number;
        }

        return $sequence->previewNextNumber();
    }

    /**
     * Reserve a specific number for a sequence (useful for manual entry).
     */
    public function reserve(DocType | string $docType, int $number, ?Model $owner = null): bool
    {
        $type = $docType instanceof DocType ? $docType->value : $docType;

        return DB::transaction(function () use ($type, $number, $owner): bool {
            $sequence = $this->getActiveSequence($type, $owner);

            if (! $sequence) {
                $sequence = $this->createDefaultSequence($type, $owner);
            }

            $periodKey = $sequence->getCurrentPeriodKey();

            $sequenceNumber = $sequence->numbers()
                ->where('period_key', $periodKey)
                ->lockForUpdate()
                ->first();

            if (! $sequenceNumber) {
                $sequence->numbers()->create([
                    'period_key' => $periodKey,
                    'last_number' => $number,
                ]);

                return true;
            }

            // Only update if the reserved number is higher
            if ($number > $sequenceNumber->last_number) {
                $sequenceNumber->update(['last_number' => $number]);
            }

            return true;
        });
    }

    /**
     * Parse a document number to extract components.
     *
     * @return array{prefix: string, period: string|null, number: int|null}
     */
    public function parse(string $docNumber): array
    {
        // Common patterns: PREFIX-YYMM-000001, PREFIX-000001, PREFIX/YY/000001
        $parts = preg_split('/[-\/]/', $docNumber);

        if (! $parts || count($parts) < 2) {
            return ['prefix' => $docNumber, 'period' => null, 'number' => null];
        }

        $prefix = $parts[0];
        $lastPart = $parts[count($parts) - 1];
        $number = is_numeric($lastPart) ? (int) $lastPart : null;

        $period = count($parts) > 2 ? $parts[1] : null;

        return [
            'prefix' => $prefix,
            'period' => $period,
            'number' => $number,
        ];
    }
}
