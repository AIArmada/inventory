<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $affiliate_id
 * @property \Illuminate\Support\Carbon $date
 * @property int $clicks
 * @property int $unique_clicks
 * @property int $attributions
 * @property int $conversions
 * @property int $revenue_cents
 * @property int $commission_cents
 * @property int $refunds
 * @property int $refund_amount_cents
 * @property float $conversion_rate
 * @property float $epc_cents
 * @property array<string, mixed>|null $breakdown
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read int $revenue_minor Alias for revenue_cents
 * @property-read int $commission_minor Alias for commission_cents
 * @property-read Affiliate $affiliate
 */
class AffiliateDailyStat extends Model
{
    use HasUuids;

    protected $fillable = [
        'affiliate_id',
        'date',
        'clicks',
        'unique_clicks',
        'attributions',
        'conversions',
        'revenue_cents',
        'commission_cents',
        'refunds',
        'refund_amount_cents',
        'conversion_rate',
        'epc_cents',
        'breakdown',
    ];

    protected $casts = [
        'date' => 'date',
        'clicks' => 'integer',
        'unique_clicks' => 'integer',
        'attributions' => 'integer',
        'conversions' => 'integer',
        'revenue_cents' => 'integer',
        'commission_cents' => 'integer',
        'refunds' => 'integer',
        'refund_amount_cents' => 'integer',
        'conversion_rate' => 'float',
        'epc_cents' => 'float',
        'breakdown' => 'array',
    ];

    public function getTable(): string
    {
        return config('affiliates.table_names.daily_stats', 'affiliate_daily_stats');
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    /**
     * Alias for revenue_cents.
     *
     * @return Attribute<int, never>
     */
    protected function revenueMinor(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->revenue_cents,
        );
    }

    /**
     * Alias for commission_cents.
     *
     * @return Attribute<int, never>
     */
    protected function commissionMinor(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->commission_cents,
        );
    }
}
