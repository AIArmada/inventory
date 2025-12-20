<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models;

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Traits\HasOwner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $affiliate_id
 * @property string $affiliate_code
 * @property string|null $cart_identifier
 * @property string $cart_instance
 * @property string|null $cookie_value
 * @property string|null $voucher_code
 * @property string|null $source
 * @property string|null $medium
 * @property string|null $campaign
 * @property string|null $term
 * @property string|null $content
 * @property string|null $landing_url
 * @property string|null $referrer_url
 * @property string|null $user_agent
 * @property string|null $ip_address
 * @property string|null $user_id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property array<string, mixed>|null $metadata
 * @property \Carbon\CarbonInterface|null $first_seen_at
 * @property \Carbon\CarbonInterface|null $last_seen_at
 * @property \Carbon\CarbonInterface|null $last_cookie_seen_at
 * @property \Carbon\CarbonInterface|null $expires_at
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 * @property-read Affiliate $affiliate
 * @property-read Collection<int, AffiliateConversion> $conversions
 * @property-read Collection<int, AffiliateTouchpoint> $touchpoints
 */
class AffiliateAttribution extends Model
{
    use HasOwner;
    use HasUuids;

    protected $fillable = [
        'affiliate_id',
        'affiliate_code',
        'cart_identifier',
        'cart_instance',
        'cookie_value',
        'voucher_code',
        'source',
        'medium',
        'campaign',
        'term',
        'content',
        'landing_url',
        'referrer_url',
        'user_agent',
        'ip_address',
        'user_id',
        'metadata',
        'owner_type',
        'owner_id',
        'first_seen_at',
        'last_seen_at',
        'last_cookie_seen_at',
        'expires_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'last_cookie_seen_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return config('affiliates.database.tables.attributions', parent::getTable());
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    /**
     * @return HasMany<AffiliateConversion, self>
     */
    public function conversions(): HasMany
    {
        return $this->hasMany(AffiliateConversion::class, 'affiliate_attribution_id');
    }

    /**
     * @return HasMany<AffiliateTouchpoint, self>
     */
    public function touchpoints(): HasMany
    {
        return $this->hasMany(AffiliateTouchpoint::class, 'affiliate_attribution_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where(function (Builder $builder): void {
            $builder
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
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

    public function refreshLastSeen(): void
    {
        $this->last_seen_at = now();

        if ($this->isDirty('last_seen_at')) {
            $this->save();
        }
    }

    protected static function booted(): void
    {
        static::creating(function (self $attribution): void {
            if (! config('affiliates.owner.enabled', false)) {
                return;
            }

            if ($attribution->owner_id !== null) {
                return;
            }

            if (! config('affiliates.owner.auto_assign_on_create', true)) {
                return;
            }

            $owner = app(OwnerResolverInterface::class)->resolve();

            if ($owner) {
                $attribution->owner_type = $owner->getMorphClass();
                $attribution->owner_id = $owner->getKey();
            }
        });

        static::deleting(function (self $attribution): void {
            $attribution->touchpoints()->delete();
            $attribution->conversions()->update(['affiliate_attribution_id' => null]);
        });
    }
}
