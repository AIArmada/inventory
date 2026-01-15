<?php

declare(strict_types=1);

namespace AIArmada\Promotions\Models;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Promotions\Enums\PromotionType;
use AIArmada\Promotions\Support\PromotionsOwnerScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Represents an automatic promotional discount campaign.
 *
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $name
 * @property string|null $code
 * @property string|null $description
 * @property PromotionType $type
 * @property int $discount_value
 * @property int $priority
 * @property bool $is_stackable
 * @property bool $is_active
 * @property int|null $usage_limit
 * @property int $usage_count
 * @property int|null $per_customer_limit
 * @property int|null $min_purchase_amount
 * @property int|null $min_quantity
 * @property array<string, mixed>|null $conditions
 * @property \Illuminate\Support\Carbon|null $starts_at
 * @property \Illuminate\Support\Carbon|null $ends_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Promotion extends Model
{
    use HasOwner {
        scopeForOwner as baseScopeForOwner;
    }
    use HasOwnerScopeConfig;
    use HasUuids;
    use LogsActivity;

    protected static string $ownerScopeConfigKey = 'promotions.features.owner';

    /** @var list<string> */
    protected $fillable = [
        'name',
        'code',
        'description',
        'type',
        'discount_value',
        'priority',
        'is_stackable',
        'is_active',
        'usage_limit',
        'per_customer_limit',
        'min_purchase_amount',
        'min_quantity',
        'conditions',
        'starts_at',
        'ends_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'type' => PromotionType::class,
        'discount_value' => 'integer',
        'priority' => 'integer',
        'is_stackable' => 'boolean',
        'is_active' => 'boolean',
        'usage_limit' => 'integer',
        'usage_count' => 'integer',
        'per_customer_limit' => 'integer',
        'min_purchase_amount' => 'integer',
        'min_quantity' => 'integer',
        'conditions' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'type' => 'percentage',
        'priority' => 0,
        'is_stackable' => false,
        'is_active' => true,
        'usage_count' => 0,
    ];

    public function getTable(): string
    {
        return (string) config('promotions.database.tables.promotions', 'promotions');
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Products included in this promotion.
     *
     * @return MorphToMany<Model, $this>
     */
    public function products(): MorphToMany
    {
        $productClass = '\\AIArmada\\Products\\Models\\Product';

        if (! class_exists($productClass)) {
            throw new RuntimeException('Products package is not installed.');
        }

        return $this->morphedByMany(
            $productClass,
            'promotionable',
            (string) config('promotions.database.tables.promotionables', 'promotionables')
        );
    }

    /**
     * Categories included in this promotion.
     *
     * @return MorphToMany<Model, $this>
     */
    public function categories(): MorphToMany
    {
        $categoryClass = '\\AIArmada\\Products\\Models\\Category';

        if (! class_exists($categoryClass)) {
            throw new RuntimeException('Products package is not installed.');
        }

        return $this->morphedByMany(
            $categoryClass,
            'promotionable',
            (string) config('promotions.database.tables.promotionables', 'promotionables')
        );
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * Scope to active promotions.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        $now = now();

        return $query->where('is_active', true)
            ->where(function (Builder $q) use ($now): void {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function (Builder $q) use ($now): void {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->where(function (Builder $q): void {
                $q->whereNull('usage_limit')
                    ->orWhereColumn('usage_count', '<', 'usage_limit');
            });
    }

    /**
     * Scope to automatic (no-code) promotions.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeAutomatic(Builder $query): Builder
    {
        return $query->whereNull('code');
    }

    /**
     * Scope to promotions requiring a code.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithCode(Builder $query): Builder
    {
        return $query->whereNotNull('code');
    }

    /**
     * Scope query to the specified owner.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForOwner(Builder $query, ?EloquentModel $owner = null, bool $includeGlobal = true): Builder
    {
        if (! PromotionsOwnerScope::isEnabled()) {
            return $query;
        }

        $includeGlobal = $includeGlobal && PromotionsOwnerScope::includeGlobal();

        $ownerToScope = $owner;

        if (func_num_args() < 2) {
            $ownerToScope = OwnerContext::CURRENT;
        }

        /** @var Builder<static> $scoped */
        $scoped = $this->baseScopeForOwner($query, $ownerToScope, $includeGlobal);

        return $scoped;
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

        if ($this->usage_limit !== null && $this->usage_count >= $this->usage_limit) {
            return false;
        }

        return true;
    }

    /**
     * Check if this is an automatic (no-code) promotion.
     */
    public function isAutomatic(): bool
    {
        return $this->code === null;
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
            ->logOnly(['name', 'code', 'type', 'discount_value', 'priority', 'is_stackable', 'usage_limit', 'is_active', 'starts_at', 'ends_at'])
            ->logOnlyDirty()
            ->useLogName('promotions');
    }

    // =========================================================================
    // BOOT
    // =========================================================================

    protected static function booted(): void
    {
        static::saving(function (Promotion $promotion): void {
            if (! PromotionsOwnerScope::isEnabled()) {
                return;
            }

            $owner = PromotionsOwnerScope::resolveOwner();

            if ($owner === null) {
                if ($promotion->owner_type !== null || $promotion->owner_id !== null) {
                    throw new AuthorizationException('Cannot write owned promotions without an owner context.');
                }

                return;
            }

            if ($promotion->owner_type === null && $promotion->owner_id === null) {
                $promotion->assignOwner($owner);
            }

            if (! $promotion->belongsToOwner($owner)) {
                throw new AuthorizationException('Cannot write promotions outside the current owner scope.');
            }
        });

        static::deleting(function (Promotion $promotion): void {
            if (PromotionsOwnerScope::isEnabled()) {
                $owner = PromotionsOwnerScope::resolveOwner();

                if ($owner === null) {
                    if ($promotion->owner_type !== null || $promotion->owner_id !== null) {
                        throw new AuthorizationException('Cannot delete owned promotions without an owner context.');
                    }
                } elseif (! $promotion->belongsToOwner($owner)) {
                    throw new AuthorizationException('Cannot delete promotions outside the current owner scope.');
                }
            }

            DB::table((string) config('promotions.database.tables.promotionables', 'promotionables'))
                ->where('promotion_id', $promotion->id)
                ->delete();
        });
    }
}
