<?php

declare(strict_types=1);

namespace AIArmada\Docs\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Docs\Enums\DocType;
use AIArmada\Docs\Enums\ResetFrequency;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Document sequence configuration for generating unique document numbers.
 *
 * @property string $id
 * @property string $name
 * @property DocType $doc_type
 * @property string $prefix
 * @property string $format
 * @property ResetFrequency $reset_frequency
 * @property int $start_number
 * @property int $increment
 * @property int $padding
 * @property bool $is_active
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read Collection<int, SequenceNumber> $numbers
 */
final class DocSequence extends Model
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'docs.owner';

    protected $fillable = [
        'name',
        'doc_type',
        'prefix',
        'format',
        'reset_frequency',
        'start_number',
        'increment',
        'padding',
        'is_active',
        'owner_type',
        'owner_id',
    ];

    public function getTable(): string
    {
        return config('docs.database.tables.doc_sequences', 'docs_sequences');
    }

    /**
     * @return HasMany<SequenceNumber, $this>
     */
    public function numbers(): HasMany
    {
        return $this->hasMany(SequenceNumber::class);
    }

    /**
     * Get the current period key based on reset frequency.
     */
    public function getCurrentPeriodKey(): string
    {
        return $this->reset_frequency->getCurrentPeriodKey();
    }

    /**
     * Generate the next document number for this sequence.
     */
    public function generateNumber(): string
    {
        $periodKey = $this->getCurrentPeriodKey();

        // Get or create sequence number record for this period
        $sequenceNumber = $this->numbers()
            ->where('period_key', $periodKey)
            ->lockForUpdate()
            ->first();

        if (! $sequenceNumber) {
            $ownerAttributes = [];
            if (config('docs.owner.enabled', false)) {
                $ownerAttributes = [
                    'owner_type' => $this->owner_type,
                    'owner_id' => $this->owner_id,
                ];
            }

            $sequenceNumber = $this->numbers()->create(array_merge([
                'period_key' => $periodKey,
                'last_number' => $this->start_number - $this->increment,
            ], $ownerAttributes));
        }

        // Increment and save
        $nextNumber = $sequenceNumber->last_number + $this->increment;
        $sequenceNumber->update(['last_number' => $nextNumber]);

        return $this->formatNumber($nextNumber);
    }

    /**
     * Format a number according to the sequence format.
     */
    public function formatNumber(int $number): string
    {
        $format = $this->format;
        $paddedNumber = mb_str_pad((string) $number, $this->padding, '0', STR_PAD_LEFT);

        // Replace tokens
        $replacements = [
            '{PREFIX}' => $this->prefix,
            '{NUMBER}' => $paddedNumber,
            '{YYYY}' => CarbonImmutable::now()->format('Y'),
            '{YY}' => CarbonImmutable::now()->format('y'),
            '{MM}' => CarbonImmutable::now()->format('m'),
            '{DD}' => CarbonImmutable::now()->format('d'),
            '{YYMM}' => CarbonImmutable::now()->format('ym'),
            '{YYMMDD}' => CarbonImmutable::now()->format('ymd'),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $format);
    }

    /**
     * Preview what the next number would look like.
     */
    public function previewNextNumber(): string
    {
        $periodKey = $this->getCurrentPeriodKey();

        $sequenceNumber = $this->numbers()
            ->where('period_key', $periodKey)
            ->first();

        $nextNumber = $sequenceNumber
            ? $sequenceNumber->last_number + $this->increment
            : $this->start_number;

        return $this->formatNumber($nextNumber);
    }

    protected static function booted(): void
    {
        self::deleting(function (DocSequence $sequence): void {
            $sequence->numbers()->delete();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'doc_type' => DocType::class,
            'reset_frequency' => ResetFrequency::class,
            'start_number' => 'integer',
            'increment' => 'integer',
            'padding' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
