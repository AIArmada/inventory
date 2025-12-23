<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Pricing\Support\PricingOwnerScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Represents a price for a specific product/variant in a price list.
 *
 * @property string $id
 * @property string $price_list_id
 * @property string $priceable_id
 * @property string $priceable_type
 * @property int $amount
 * @property int|null $compare_amount
 * @property string $currency
 * @property int $min_quantity
 * @property \Illuminate\Support\Carbon|null $starts_at
 * @property \Illuminate\Support\Carbon|null $ends_at
 */
class Price extends Model
{
    use HasOwner {
        scopeForOwner as baseScopeForOwner;
    }
    use HasOwnerScopeConfig;
    use HasUuids;
    use LogsActivity;

    protected static string $ownerScopeConfigKey = 'pricing.features.owner';

    protected $fillable = [
        'price_list_id',
        'priceable_id',
        'priceable_type',
        'amount',
        'compare_amount',
        'currency',
        'min_quantity',
        'starts_at',
        'ends_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'integer',
        'compare_amount' => 'integer',
        'min_quantity' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'min_quantity' => 1,
        'currency' => 'MYR',
    ];

    public function getTable(): string
    {
        return config('pricing.database.tables.prices', 'prices');
    }

    protected static function booted(): void
    {
        static::saving(function (self $price): void {
            if (! PricingOwnerScope::isEnabled()) {
                return;
            }

            $owner = PricingOwnerScope::resolveOwner();

            if ($owner === null) {
                if ($price->owner_type !== null || $price->owner_id !== null) {
                    throw new AuthorizationException('Cannot write owned prices without an owner context.');
                }
            } else {
                if ($price->owner_type === null && $price->owner_id === null) {
                    $price->assignOwner($owner);
                }

                if (! $price->belongsToOwner($owner)) {
                    throw new AuthorizationException('Cannot write prices outside the current owner scope.');
                }
            }

            $priceListQuery = PricingOwnerScope::applyToOwnedQuery(PriceList::query());

            $priceListExists = $priceListQuery
                ->whereKey($price->price_list_id)
                ->exists();

            if (! $priceListExists) {
                throw new AuthorizationException('Price list is not accessible in the current owner scope.');
            }

            $type = $price->priceable_type;

            if ($owner !== null && is_string($type) && class_exists($type) && is_a($type, Model::class, true)) {
                $usesHasOwner = in_array(HasOwner::class, class_uses_recursive($type), true);

                if ($usesHasOwner) {
                    /** @var Builder<Model> $query */
                    $query = $type::query();

                    $exists = PricingOwnerScope::applyToOwnedQuery($query)
                        ->whereKey($price->priceable_id)
                        ->exists();

                    if (! $exists) {
                        throw new AuthorizationException('Priceable entity is not accessible in the current owner scope.');
                    }
                }
            }
        });
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * The priceable item (Product, Variant, etc.).
     */
    public function priceable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The price list this price belongs to.
     *
     * @return BelongsTo<PriceList, $this>
     */
    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeActive($query)
    {
        $now = now();

        return $query
            ->where(function ($q) use ($now): void {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now): void {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            });
    }

    public function scopeForQuantity($query, int $quantity)
    {
        return $query->where('min_quantity', '<=', $quantity);
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    public function isActive(): bool
    {
        $now = now();

        if ($this->starts_at && $this->starts_at > $now) {
            return false;
        }

        if ($this->ends_at && $this->ends_at < $now) {
            return false;
        }

        return true;
    }

    public function hasDiscount(): bool
    {
        return $this->compare_amount !== null && $this->compare_amount > $this->amount;
    }

    public function getDiscountPercentage(): ?float
    {
        if (! $this->hasDiscount()) {
            return null;
        }

        return round((($this->compare_amount - $this->amount) / $this->compare_amount) * 100, 1);
    }

    public function getFormattedAmount(): string
    {
        $symbol = match ($this->currency) {
            'MYR' => 'RM',
            'USD' => '$',
            'SGD' => 'S$',
            default => $this->currency . ' ',
        };

        return $symbol . number_format($this->amount / 100, 2);
    }

    // =========================================================================
    // ACTIVITY LOG
    // =========================================================================

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['amount', 'compare_amount', 'min_quantity', 'starts_at', 'ends_at'])
            ->logOnlyDirty()
            ->useLogName('pricing');
    }
}
