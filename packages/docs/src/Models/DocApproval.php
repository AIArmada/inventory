<?php

declare(strict_types=1);

namespace AIArmada\Docs\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as FoundationUser;
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
 * @property-read Model $requestedBy
 * @property-read Model|null $assignedTo
 */
final class DocApproval extends Model
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'docs.owner';

    protected $fillable = [
        'doc_id',
        'owner_type',
        'owner_id',
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

    /**
     * @return BelongsTo<Model, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo($this->resolveUserModelClass(), 'requested_by');
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo($this->resolveUserModelClass(), 'assigned_to');
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
            'approved_at' => CarbonImmutable::now(),
            'comments' => $comments,
        ]);
    }

    public function reject(?string $comments = null): void
    {
        $this->update([
            'status' => 'rejected',
            'rejected_at' => CarbonImmutable::now(),
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

    /**
     * @return class-string<Model>
     */
    private function resolveUserModelClass(): string
    {
        $configured = config('auth.providers.users.model');

        if (is_string($configured) && class_exists($configured)) {
            /** @var class-string<Model> $configured */
            return $configured;
        }

        return FoundationUser::class;
    }
}
