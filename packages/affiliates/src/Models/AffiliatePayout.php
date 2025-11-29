<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AffiliatePayout extends Model
{
    use HasUuids;

    protected $fillable = [
        'reference',
        'status',
        'total_minor',
        'conversion_count',
        'currency',
        'metadata',
        'owner_type',
        'owner_id',
        'scheduled_at',
        'paid_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'scheduled_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return config('affiliates.table_names.payouts', parent::getTable());
    }

    /**
     * @return HasMany<AffiliateConversion, self>
     */
    public function conversions(): HasMany
    {
        return $this->hasMany(AffiliateConversion::class, 'affiliate_payout_id');
    }

    /**
     * @return HasMany<AffiliatePayoutEvent, self>
     */
    public function events(): HasMany
    {
        return $this->hasMany(AffiliatePayoutEvent::class, 'affiliate_payout_id')->latest();
    }
}
