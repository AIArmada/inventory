<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Campaigns\Models;

use AIArmada\Vouchers\Campaigns\Enums\CampaignEventType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $id
 * @property string $campaign_id
 * @property string|null $variant_id
 * @property CampaignEventType $event_type
 * @property string|null $voucher_code
 * @property string|null $user_type
 * @property string|null $user_id
 * @property string|null $cart_type
 * @property string|null $cart_id
 * @property string|null $order_type
 * @property string|null $order_id
 * @property string|null $channel
 * @property string|null $source
 * @property string|null $medium
 * @property int|null $value_cents
 * @property int|null $discount_cents
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon $occurred_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Campaign $campaign
 * @property-read CampaignVariant|null $variant
 */
class CampaignEvent extends Model
{
    use HasUuids;

    protected $fillable = [
        'campaign_id',
        'variant_id',
        'event_type',
        'voucher_code',
        'user_type',
        'user_id',
        'cart_type',
        'cart_id',
        'order_type',
        'order_id',
        'channel',
        'source',
        'medium',
        'value_cents',
        'discount_cents',
        'metadata',
        'occurred_at',
    ];

    /**
     * Record an impression event.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function recordImpression(Campaign $campaign, ?CampaignVariant $variant = null, array $attributes = []): static
    {
        return static::recordEvent(CampaignEventType::Impression, $campaign, $variant, $attributes);
    }

    /**
     * Record an application event.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function recordApplication(
        Campaign $campaign,
        string $voucherCode,
        ?CampaignVariant $variant = null,
        array $attributes = []
    ): static {
        return static::recordEvent(
            CampaignEventType::Application,
            $campaign,
            $variant,
            array_merge($attributes, ['voucher_code' => $voucherCode])
        );
    }

    /**
     * Record a conversion event.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function recordConversion(
        Campaign $campaign,
        string $voucherCode,
        int $valueCents,
        int $discountCents,
        ?CampaignVariant $variant = null,
        array $attributes = []
    ): static {
        return static::recordEvent(
            CampaignEventType::Conversion,
            $campaign,
            $variant,
            array_merge($attributes, [
                'voucher_code' => $voucherCode,
                'value_cents' => $valueCents,
                'discount_cents' => $discountCents,
            ])
        );
    }

    /**
     * Record an abandonment event.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function recordAbandonment(
        Campaign $campaign,
        string $voucherCode,
        ?CampaignVariant $variant = null,
        array $attributes = []
    ): static {
        return static::recordEvent(
            CampaignEventType::Abandonment,
            $campaign,
            $variant,
            array_merge($attributes, ['voucher_code' => $voucherCode])
        );
    }

    /**
     * Record a removal event.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function recordRemoval(
        Campaign $campaign,
        string $voucherCode,
        ?CampaignVariant $variant = null,
        array $attributes = []
    ): static {
        return static::recordEvent(
            CampaignEventType::Removal,
            $campaign,
            $variant,
            array_merge($attributes, ['voucher_code' => $voucherCode])
        );
    }

    /**
     * Record an event.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function recordEvent(
        CampaignEventType $type,
        Campaign $campaign,
        ?CampaignVariant $variant = null,
        array $attributes = []
    ): static {
        /** @var static $event */
        $event = static::create(array_merge([
            'campaign_id' => $campaign->id,
            'variant_id' => $variant?->id,
            'event_type' => $type,
            'occurred_at' => Carbon::now(),
        ], $attributes));

        // Update variant metrics if applicable
        if ($variant !== null && $type->incrementsMetric()) {
            $metricField = $type->variantMetric();
            if ($metricField !== null) {
                $variant->increment($metricField);
            }

            // Handle conversion revenue and discount
            if ($type === CampaignEventType::Conversion) {
                if (isset($attributes['value_cents'])) {
                    $variant->increment('revenue_cents', (int) $attributes['value_cents']);
                }
                if (isset($attributes['discount_cents'])) {
                    $variant->increment('discount_cents', (int) $attributes['discount_cents']);
                }
            }
        }

        return $event;
    }

    public function getTable(): string
    {
        /** @var array<string, string> $tables */
        $tables = config('vouchers.database.tables', []);
        $prefix = (string) config('vouchers.database.table_prefix', '');

        return $tables['campaign_events'] ?? $prefix.'voucher_campaign_events';
    }

    /**
     * @return BelongsTo<Campaign, CampaignEvent>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    /**
     * @return BelongsTo<CampaignVariant, CampaignEvent>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(CampaignVariant::class, 'variant_id');
    }

    public function user(): MorphTo
    {
        return $this->morphTo();
    }

    public function cart(): MorphTo
    {
        return $this->morphTo();
    }

    public function order(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope to filter by event type.
     *
     * @param  Builder<CampaignEvent>  $query
     * @return Builder<CampaignEvent>
     */
    public function scopeOfType(Builder $query, CampaignEventType $type): Builder
    {
        return $query->where('event_type', $type->value);
    }

    /**
     * Scope to filter impressions.
     *
     * @param  Builder<CampaignEvent>  $query
     * @return Builder<CampaignEvent>
     */
    public function scopeImpressions(Builder $query): Builder
    {
        return $query->ofType(CampaignEventType::Impression);
    }

    /**
     * Scope to filter applications.
     *
     * @param  Builder<CampaignEvent>  $query
     * @return Builder<CampaignEvent>
     */
    public function scopeApplications(Builder $query): Builder
    {
        return $query->ofType(CampaignEventType::Application);
    }

    /**
     * Scope to filter conversions.
     *
     * @param  Builder<CampaignEvent>  $query
     * @return Builder<CampaignEvent>
     */
    public function scopeConversions(Builder $query): Builder
    {
        return $query->ofType(CampaignEventType::Conversion);
    }

    /**
     * Scope to filter by date range.
     *
     * @param  Builder<CampaignEvent>  $query
     * @return Builder<CampaignEvent>
     */
    public function scopeOccurredBetween(Builder $query, Carbon $start, Carbon $end): Builder
    {
        return $query->whereBetween('occurred_at', [$start, $end]);
    }

    /**
     * Scope to filter by channel.
     *
     * @param  Builder<CampaignEvent>  $query
     * @return Builder<CampaignEvent>
     */
    public function scopeFromChannel(Builder $query, string $channel): Builder
    {
        return $query->where('channel', $channel);
    }

    /**
     * Scope to filter by variant.
     *
     * @param  Builder<CampaignEvent>  $query
     * @return Builder<CampaignEvent>
     */
    public function scopeForVariant(Builder $query, CampaignVariant $variant): Builder
    {
        return $query->where('variant_id', $variant->id);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'event_type' => CampaignEventType::class,
            'value_cents' => 'integer',
            'discount_cents' => 'integer',
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
