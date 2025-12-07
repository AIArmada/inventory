<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $affiliate_payout_id
 * @property string|null $from_status
 * @property string $to_status
 * @property array<string, mixed>|null $metadata
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read string $status Alias for to_status
 * @property-read AffiliatePayout $payout
 */
class AffiliatePayoutEvent extends Model
{
    use HasUuids;

    protected $fillable = [
        'affiliate_payout_id',
        'from_status',
        'to_status',
        'metadata',
        'notes',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function getTable(): string
    {
        return config('affiliates.table_names.payout_events', parent::getTable());
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(AffiliatePayout::class, 'affiliate_payout_id');
    }

    /**
     * Alias for to_status.
     *
     * @return Attribute<string, never>
     */
    protected function status(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->to_status,
        );
    }
}
