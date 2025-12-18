<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models;

use AIArmada\Affiliates\Enums\ConversionStatus;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Traits\HasOwner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $affiliate_id
 * @property string|null $affiliate_attribution_id
 * @property string|null $affiliate_payout_id
 * @property string $affiliate_code
 * @property string|null $cart_identifier
 * @property string|null $cart_instance
 * @property string|null $voucher_code
 * @property string|null $order_reference
 * @property int $subtotal_minor
 * @property int $total_minor
 * @property int $commission_minor
 * @property string $commission_currency
 * @property ConversionStatus $status
 * @property string|null $channel
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $occurred_at
 * @property \Illuminate\Support\Carbon|null $approved_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read string|null $order_id Alias for order_reference
 * @property-read string $currency Alias for commission_currency
 * @property-read Affiliate $affiliate
 * @property-read AffiliateAttribution|null $attribution
 * @property-read AffiliatePayout|null $payout
 */
class AffiliateConversion extends Model
{
    use HasOwner;
    use HasUuids;

    protected $fillable = [
        'affiliate_id',
        'affiliate_code',
        'affiliate_attribution_id',
        'affiliate_payout_id',
        'cart_identifier',
        'cart_instance',
        'voucher_code',
        'order_reference',
        'subtotal_minor',
        'total_minor',
        'commission_minor',
        'commission_currency',
        'status',
        'channel',
        'metadata',
        'owner_type',
        'owner_id',
        'occurred_at',
        'approved_at',
    ];

    public function getTable(): string
    {
        return config('affiliates.table_names.conversions', parent::getTable());
    }

    /**
     * @return BelongsTo<Affiliate, $this>
     */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    /**
     * @return BelongsTo<AffiliateAttribution, $this>
     */
    public function attribution(): BelongsTo
    {
        return $this->belongsTo(AffiliateAttribution::class, 'affiliate_attribution_id');
    }

    /**
     * @return BelongsTo<AffiliatePayout, $this>
     */
    public function payout(): BelongsTo
    {
        return $this->belongsTo(AffiliatePayout::class, 'affiliate_payout_id');
    }

    public function scopeForOwner(Builder $query, ?Model $owner = null, bool $includeGlobal = true): Builder
    {
        if (! config('affiliates.owner.enabled', false)) {
            return $query;
        }

        $owner ??= app(OwnerResolverInterface::class)->resolve();

        if (! $owner) {
            return $query->whereNull('owner_type')->whereNull('owner_id');
        }

        return $query->where(function (Builder $builder) use ($owner, $includeGlobal): void {
            $builder->where('owner_type', $owner->getMorphClass())
                ->where('owner_id', $owner->getKey());

            if ($includeGlobal) {
                $builder->orWhere(function (Builder $inner): void {
                    $inner->whereNull('owner_type')->whereNull('owner_id');
                });
            }
        });
    }

    protected static function booted(): void
    {
        static::creating(function (self $conversion): void {
            if (! config('affiliates.owner.enabled', false)) {
                return;
            }

            if ($conversion->owner_id !== null) {
                return;
            }

            if (! config('affiliates.owner.auto_assign_on_create', true)) {
                return;
            }

            $owner = app(OwnerResolverInterface::class)->resolve();

            if ($owner) {
                $conversion->owner_type = $owner->getMorphClass();
                $conversion->owner_id = $owner->getKey();
            }
        });
    }

    /**
     * Alias for order_reference.
     *
     * @return Attribute<string|null, never>
     */
    protected function orderId(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->order_reference,
        );
    }

    /**
     * Alias for commission_currency.
     *
     * @return Attribute<string, never>
     */
    protected function currency(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->commission_currency,
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'occurred_at' => 'datetime',
            'approved_at' => 'datetime',
            'status' => ConversionStatus::class,
        ];
    }
}
