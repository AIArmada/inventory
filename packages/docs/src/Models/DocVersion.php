<?php

declare(strict_types=1);

namespace AIArmada\Docs\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Document version for tracking changes over time.
 *
 * @property string $id
 * @property string $doc_id
 * @property int $version_number
 * @property array<string, mixed> $snapshot
 * @property string|null $change_summary
 * @property string|null $changed_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Doc $doc
 */
final class DocVersion extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'doc_id',
        'version_number',
        'snapshot',
        'change_summary',
        'changed_by',
    ];

    public function getTable(): string
    {
        return config('docs.database.tables.doc_versions', 'docs_versions');
    }

    /**
     * @return BelongsTo<Doc, $this>
     */
    public function doc(): BelongsTo
    {
        return $this->belongsTo(Doc::class);
    }

    /**
     * Restore this version to the document.
     */
    public function restore(): void
    {
        $this->doc->update($this->snapshot);
    }

    /**
     * Get the diff between this version and another.
     *
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public function diff(?self $other = null): array
    {
        $otherSnapshot = $other?->snapshot ?? [];
        $diff = [];

        foreach ($this->snapshot as $key => $value) {
            if (! array_key_exists($key, $otherSnapshot) || $otherSnapshot[$key] !== $value) {
                $diff[$key] = [
                    'old' => $otherSnapshot[$key] ?? null,
                    'new' => $value,
                ];
            }
        }

        return $diff;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'snapshot' => 'array',
        ];
    }
}
