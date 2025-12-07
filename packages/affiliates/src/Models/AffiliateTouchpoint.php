<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $affiliate_attribution_id
 * @property string $affiliate_id
 * @property string $affiliate_code
 * @property string|null $source
 * @property string|null $medium
 * @property string|null $campaign
 * @property string|null $term
 * @property string|null $content
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $touched_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read string|null $ip_address IP address from the parent attribution
 * @property-read AffiliateAttribution $attribution
 * @property-read Affiliate $affiliate
 */
class AffiliateTouchpoint extends Model
{
    use HasUuids;

    protected $fillable = [
        'affiliate_attribution_id',
        'affiliate_id',
        'affiliate_code',
        'source',
        'medium',
        'campaign',
        'term',
        'content',
        'metadata',
        'touched_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'touched_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return config('affiliates.table_names.touchpoints', parent::getTable());
    }

    /**
     * @return BelongsTo<AffiliateAttribution, $this>
     */
    public function attribution(): BelongsTo
    {
        return $this->belongsTo(AffiliateAttribution::class, 'affiliate_attribution_id');
    }

    /**
     * @return BelongsTo<Affiliate, $this>
     */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    /**
     * Get the IP address from the parent attribution.
     *
     * @return Attribute<string|null, never>
     */
    protected function ipAddress(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->attribution?->ip_address,
        );
    }
}
