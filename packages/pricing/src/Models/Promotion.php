<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\Pricing\Enums\PromotionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Represents a promotional pricing campaign.
 *
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $name
 * @property string|null $description
 * @property PromotionType $type
 * @property int $discount_value
 * @property int $priority
 * @property bool $is_stackable
 * @property bool $is_active
 * @property int|null $usage_limit
 * @property int $usage_count
 * @property array|null $conditions
 * @property \Illuminate\Support\Carbon|null $starts_at
 * @property \Illuminate\Support\Carbon|null $ends_at
 */
class Promotion extends Model
{
    use HasOwner;
    use HasUuids;
    use LogsActivity;
    use SoftDeletes;

    protected $guarded = ['id'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'type' => PromotionType::class,
        'discount_value' => 'integer',
        'priority' => 'integer',
        'is_stackable' => 'boolean',
        'is_active' => 'boolean',
        'usage_limit' => 'integer',
        'usage_count' => 'integer',
        'conditions' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'percentage',
        'priority' => 0,
        'is_stackable' => false,
        'is_active' => true,
        'usage_count' => 0,
    ];

    public function getTable(): string
    {
        return config('pricing.tables.promotions', 'promotions');
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Products included in this promotion.
     *
     * @return MorphToMany<\AIArmada\Products\Models\Product, $this>
     */
    public function products(): MorphToMany
    {
        return $this->morphedByMany(
            config('products.model', \AIArmada\Products\Models\Product::class),
            'promotionable',
            'promotionables'
        );
    }

    /**
     * Categories included in this promotion.
     *
     * @return MorphToMany<\AIArmada\Products\Models\Category, $this>
     */
    public function categories(): MorphToMany
    {
        return $this->morphedByMany(
            config('products.models.category', \AIArmada\Products\Models\Category::class),
            'promotionable',
            'promotionables'
        );
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q): void {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q): void {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->where(function ($q): void {
                $q->whereNull('usage_limit')
                    ->orWhereColumn('usage_count', '<', 'usage_limit');
            });
    }

    /**
     * Scope query to the specified owner.
     *
     * @param  Builder<static>  $query
     * @param  EloquentModel|null  $owner  The owner to scope to
     * @param  bool  $includeGlobal  Whether to include global (ownerless) records
     * @return Builder<static>
     */
    public function scopeForOwner(Builder $query, ?EloquentModel $owner, bool $includeGlobal = true): Builder
    {
        if (! config('pricing.owner.enabled', false)) {
            return $query;
        }

        if (! $owner) {
            return $includeGlobal
                ? $query->whereNull('owner_id')
                : $query->whereNull('owner_type')->whereNull('owner_id');
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

    // =========================================================================
    // HELPERS
    // =========================================================================

    public function isActive(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now = now();

        if ($this->starts_at && $this->starts_at > $now) {
            return false;
        }

        if ($this->ends_at && $this->ends_at < $now) {
            return false;
        }

        if ($this->usage_limit && $this->usage_count >= $this->usage_limit) {
            return false;
        }

        return true;
    }

    /**
     * Calculate the discount amount for a given price.
     */
    public function calculateDiscount(int $priceInCents): int
    {
        return match ($this->type) {
            PromotionType::Percentage => (int) round($priceInCents * ($this->discount_value / 100)),
            PromotionType::Fixed => min($this->discount_value, $priceInCents),
            PromotionType::BuyXGetY => 0, // Handled separately
        };
    }

    /**
     * Increment the usage counter.
     */
    public function incrementUsage(): self
    {
        $this->increment('usage_count');

        return $this;
    }

    public function hasRemainingUsage(): bool
    {
        if ($this->usage_limit === null) {
            return true;
        }

        return $this->usage_count < $this->usage_limit;
    }

    // =========================================================================
    // ACTIVITY LOG
    // =========================================================================

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'type', 'discount_value', 'is_active', 'starts_at', 'ends_at'])
            ->logOnlyDirty()
            ->useLogName('pricing');
    }
}
