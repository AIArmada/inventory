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
 * Represents quantity-based tiered pricing.
 *
 * @property string $id
 * @property string|null $price_list_id
 * @property string|null $tierable_id
 * @property string|null $tierable_type
 * @property int $min_quantity
 * @property int|null $max_quantity
 * @property int $amount
 * @property string|null $discount_type
 * @property int|null $discount_value
 * @property string $currency
 * @property-read PriceList|null $priceList
 */
class PriceTier extends Model
{
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;
    use LogsActivity;

    protected static string $ownerScopeConfigKey = 'pricing.features.owner';

    protected $fillable = [
        'price_list_id',
        'tierable_id',
        'tierable_type',
        'min_quantity',
        'max_quantity',
        'amount',
        'discount_type',
        'discount_value',
        'currency',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'min_quantity' => 'integer',
        'max_quantity' => 'integer',
        'amount' => 'integer',
        'discount_value' => 'integer',
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
        return config('pricing.database.tables.price_tiers', 'price_tiers');
    }

    protected static function booted(): void
    {
        static::saving(function (self $tier): void {
            if (! PricingOwnerScope::isEnabled()) {
                return;
            }

            $owner = PricingOwnerScope::resolveOwner();

            if ($owner === null) {
                if ($tier->owner_type !== null || $tier->owner_id !== null) {
                    throw new AuthorizationException('Cannot write owned price tiers without an owner context.');
                }
            } else {
                if ($tier->owner_type === null && $tier->owner_id === null) {
                    $tier->assignOwner($owner);
                }

                if (! $tier->belongsToOwner($owner)) {
                    throw new AuthorizationException('Cannot write price tiers outside the current owner scope.');
                }
            }

            if ($tier->price_list_id !== null) {
                $priceListQuery = PricingOwnerScope::applyToOwnedQuery(PriceList::query());

                $priceListExists = $priceListQuery
                    ->whereKey($tier->price_list_id)
                    ->exists();

                if (! $priceListExists) {
                    throw new AuthorizationException('Price list is not accessible in the current owner scope.');
                }
            }

            $type = $tier->tierable_type;

            if ($owner !== null && is_string($type) && class_exists($type) && is_a($type, Model::class, true)) {
                $usesHasOwner = in_array(HasOwner::class, class_uses_recursive($type), true);

                if ($usesHasOwner) {
                    /** @var Builder<Model> $query */
                    $query = $type::query();

                    $exists = PricingOwnerScope::applyToOwnedQuery($query)
                        ->whereKey($tier->tierable_id)
                        ->exists();

                    if (! $exists) {
                        throw new AuthorizationException('Tierable entity is not accessible in the current owner scope.');
                    }
                }
            }
        });
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * The tierable item (Product, Variant, etc.).
     *
     * @return MorphTo<Model, $this>
     */
    public function tierable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The price list this tier belongs to (optional).
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

    public function scopeForQuantity($query, int $quantity)
    {
        return $query
            ->where('min_quantity', '<=', $quantity)
            ->where(function ($q) use ($quantity): void {
                $q->whereNull('max_quantity')
                    ->orWhere('max_quantity', '>=', $quantity);
            });
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('min_quantity', 'asc');
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Check if quantity falls within this tier.
     */
    public function appliesTo(int $quantity): bool
    {
        if ($quantity < $this->min_quantity) {
            return false;
        }

        if ($this->max_quantity !== null && $quantity > $this->max_quantity) {
            return false;
        }

        return true;
    }

    /**
     * Get the tier description (e.g., "10-49 units").
     */
    public function getDescription(): string
    {
        if ($this->max_quantity === null) {
            return "{$this->min_quantity}+ units";
        }

        return "{$this->min_quantity}-{$this->max_quantity} units";
    }

    /**
     * Get the discount description (e.g., "10% off").
     */
    public function getDiscountDescription(): ?string
    {
        if (! $this->discount_type || ! $this->discount_value) {
            return null;
        }

        return match ($this->discount_type) {
            'percentage' => "{$this->discount_value}% off",
            'fixed' => 'RM ' . number_format($this->discount_value / 100, 2) . ' off',
            default => null,
        };
    }

    // =========================================================================
    // ACTIVITY LOG
    // =========================================================================

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['min_quantity', 'max_quantity', 'amount', 'discount_type', 'discount_value'])
            ->logOnlyDirty()
            ->useLogName('pricing');
    }
}
