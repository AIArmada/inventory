<?php

declare(strict_types=1);

namespace AIArmada\Docs\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Document approval request for workflow management.
 *
 * @property string $id
 * @property string $doc_id
 * @property string $requested_by
 * @property string|null $assigned_to
 * @property string $status
 * @property string|null $comments
 * @property Carbon|null $approved_at
 * @property Carbon|null $rejected_at
 * @property Carbon|null $expires_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Doc $doc
 */
final class DocApproval extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'doc_id',
        'requested_by',
        'assigned_to',
        'status',
        'comments',
        'approved_at',
        'rejected_at',
        'expires_at',
    ];

    public function getTable(): string
    {
        return config('docs.database.tables.doc_approvals', 'docs_approvals');
    }

    /**
     * @return BelongsTo<Doc, $this>
     */
    public function doc(): BelongsTo
    {
        return $this->belongsTo(Doc::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function approve(?string $comments = null): void
    {
        $this->update([
            'status' => 'approved',
            'approved_at' => now(),
            'comments' => $comments,
        ]);
    }

    public function reject(?string $comments = null): void
    {
        $this->update([
            'status' => 'rejected',
            'rejected_at' => now(),
            'comments' => $comments,
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
