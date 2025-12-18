<?php

declare(strict_types=1);

namespace AIArmada\Tax\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Represents a tax exemption for a customer or entity.
 *
 * @property string $id
 * @property string|null $exemptable_id
 * @property string|null $exemptable_type
 * @property string|null $tax_zone_id
 * @property string $reason
 * @property string|null $certificate_number
 * @property string|null $document_path
 * @property string $status
 * @property string|null $rejection_reason
 * @property \Illuminate\Support\Carbon|null $verified_at
 * @property string|null $verified_by
 * @property \Illuminate\Support\Carbon|null $starts_at
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property-read TaxZone|null $taxZone
 * @property-read Model|null $exemptable
 */
class TaxExemption extends Model
{
    use HasUuids;
    use LogsActivity;

    protected $fillable = [
        'exemptable_id',
        'exemptable_type',
        'tax_zone_id',
        'reason',
        'certificate_number',
        'document_path',
        'status',
        'rejection_reason',
        'verified_at',
        'verified_by',
        'starts_at',
        'expires_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'verified_at' => 'datetime',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    public function getTable(): string
    {
        return (string) config('tax.database.tables.tax_exemptions', 'tax_exemptions');
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * The exemptable entity (Customer, User, etc.).
     *
     * @return MorphTo<Model, $this>
     */
    public function exemptable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The tax zone this exemption applies to (null = all zones).
     *
     * @return BelongsTo<TaxZone, $this>
     */
    public function taxZone(): BelongsTo
    {
        return $this->belongsTo(TaxZone::class, 'tax_zone_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        $now = now();

        return $query->where('status', 'approved')
            ->where(function ($q) use ($now): void {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', $now);
            })
            ->where(function ($q) use ($now): void {
                $q->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', $now);
            });
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopePending(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeApproved(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope to exemptions for a specific zone (or all zones).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeForZone(\Illuminate\Database\Eloquent\Builder $query, ?string $zoneId): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where(function (\Illuminate\Database\Eloquent\Builder $builder) use ($zoneId): void {
            $builder->whereNull('tax_zone_id');

            if ($zoneId !== null) {
                $builder->orWhere('tax_zone_id', $zoneId);
            }
        });
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    public function isActive(): bool
    {
        if ($this->status !== 'approved') {
            return false;
        }

        if ($this->starts_at && $this->starts_at > now()) {
            return false;
        }

        if ($this->expires_at && $this->expires_at < now()) {
            return false;
        }

        return true;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at < now();
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

    /**
     * Check if exemption applies to a specific zone.
     */
    public function appliesToZone(?string $zoneId): bool
    {
        // If no zone specified on exemption, it applies to all
        if ($this->tax_zone_id === null) {
            return true;
        }

        return $this->tax_zone_id === $zoneId;
    }

    public function approve(): self
    {
        $this->status = 'approved';
        $this->verified_at = now();
        $this->save();

        return $this;
    }

    public function reject(string $reason): self
    {
        $this->status = 'rejected';
        $this->rejection_reason = $reason;
        $this->save();

        return $this;
    }

    // =========================================================================
    // ACTIVITY LOG
    // =========================================================================

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['reason', 'status', 'verified_at', 'starts_at', 'expires_at'])
            ->logOnlyDirty()
            ->useLogName('tax');
    }
}
