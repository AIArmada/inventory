<?php

declare(strict_types=1);

namespace AIArmada\Shipping\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\Shipping\Enums\ReturnReason;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $rma_number
 * @property int|null $original_shipment_id
 * @property string|null $order_reference
 * @property int|null $customer_id
 * @property string $status
 * @property string $type
 * @property string $reason
 * @property string|null $reason_details
 * @property int|null $approved_by
 * @property \Illuminate\Support\Carbon|null $approved_at
 * @property \Illuminate\Support\Carbon|null $received_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property array|null $metadata
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read Shipment|null $originalShipment
 * @property-read Shipment|null $returnShipment
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ReturnAuthorizationItem> $items
 */
class ReturnAuthorization extends Model
{
    use HasOwner;
    use SoftDeletes;

    protected $table = 'return_authorizations';

    protected $fillable = [
        'owner_id',
        'owner_type',
        'rma_number',
        'original_shipment_id',
        'order_reference',
        'customer_id',
        'status',
        'type',
        'reason',
        'reason_details',
        'approved_by',
        'approved_at',
        'received_at',
        'completed_at',
        'expires_at',
        'metadata',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    public static function generateRmaNumber(): string
    {
        return 'RMA-'.mb_strtoupper(uniqid());
    }

    // ─────────────────────────────────────────────────────────────
    // RELATIONSHIPS
    // ─────────────────────────────────────────────────────────────

    /**
     * @return BelongsTo<Shipment, ReturnAuthorization>
     */
    public function originalShipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'original_shipment_id');
    }

    /**
     * @return HasOne<Shipment>
     */
    public function returnShipment(): HasOne
    {
        return $this->hasOne(Shipment::class, 'shippable_id')
            ->where('shippable_type', static::class);
    }

    /**
     * @return HasMany<ReturnAuthorizationItem>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ReturnAuthorizationItem::class);
    }

    // ─────────────────────────────────────────────────────────────
    // SCOPES
    // ─────────────────────────────────────────────────────────────

    /**
     * @param  Builder<ReturnAuthorization>  $query
     * @return Builder<ReturnAuthorization>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * @param  Builder<ReturnAuthorization>  $query
     * @return Builder<ReturnAuthorization>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    // ─────────────────────────────────────────────────────────────
    // STATUS HELPERS
    // ─────────────────────────────────────────────────────────────

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

    public function isReceived(): bool
    {
        return $this->status === 'received';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isExpired(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        return $this->expires_at->isPast() && $this->isPending();
    }

    public function getReasonEnum(): ?ReturnReason
    {
        return ReturnReason::tryFrom($this->reason);
    }

    protected static function booted(): void
    {
        static::creating(function (ReturnAuthorization $rma): void {
            if (empty($rma->rma_number)) {
                $rma->rma_number = static::generateRmaNumber();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'received_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
