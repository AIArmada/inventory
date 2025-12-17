<?php

declare(strict_types=1);

namespace AIArmada\Products\Models;

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Traits\HasOwner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string $type
 * @property array<string, mixed>|null $conditions
 * @property int $position
 * @property bool $is_visible
 * @property bool $is_featured
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property \Illuminate\Support\Carbon|null $unpublished_at
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Product> $products
 */
class Collection extends Model implements HasMedia
{
    use HasFactory;
    use HasOwner {
        scopeForOwner as baseScopeForOwner;
    }
    use HasSlug;
    use HasUuids;
    use InteractsWithMedia;

    protected $guarded = ['id'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'type' => 'string', // manual or automatic
        'conditions' => 'array',
        'is_visible' => 'boolean',
        'is_featured' => 'boolean',
        'position' => 'integer',
        'published_at' => 'datetime',
        'unpublished_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'manual',
        'is_visible' => true,
        'is_featured' => false,
        'position' => 0,
    ];

    public function getTable(): string
    {
        return config('products.tables.collections', 'product_collections');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForOwner(Builder $query, ?Model $owner = null, bool $includeGlobal = true): Builder
    {
        if (! (bool) config('products.owner.enabled', true)) {
            return $query;
        }

        if ($owner === null && app()->bound(OwnerResolverInterface::class)) {
            $owner = app(OwnerResolverInterface::class)->resolve();
        }

        $includeGlobal = $includeGlobal && (bool) config('products.owner.include_global', true);

        /** @var Builder<Collection> $scoped */
        $scoped = $this->baseScopeForOwner($query, $owner, $includeGlobal);

        return $scoped;
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Get the products in this collection (for manual collections).
     *
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        $relation = $this->belongsToMany(
            Product::class,
            config('products.tables.collection_product', 'collection_product'),
            'collection_id',
            'product_id'
        )->withTimestamps()->withPivot('position');

        $this->applyOwnerScopeToProductsQuery($relation->getQuery());

        return $relation;
    }

    // =========================================================================
    // SPATIE MEDIALIBRARY
    // =========================================================================

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('hero')
            ->singleFile();

        $this->addMediaCollection('banner')
            ->singleFile();
    }

    // =========================================================================
    // SPATIE SLUGGABLE
    // =========================================================================

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    // =========================================================================
    // TYPE HELPERS
    // =========================================================================

    /**
     * Check if this is a manual collection.
     */
    public function isManual(): bool
    {
        return $this->type === 'manual';
    }

    /**
     * Check if this is an automatic (rule-based) collection.
     */
    public function isAutomatic(): bool
    {
        return $this->type === 'automatic';
    }

    // =========================================================================
    // AUTOMATIC COLLECTION LOGIC
    // =========================================================================

    /**
     * Get products matching the automatic collection conditions.
     */
    public function getMatchingProducts(): \Illuminate\Support\Collection
    {
        if ($this->isManual()) {
            $relation = $this->products();
            $this->applyOwnerScopeToProductsQuery($relation->getQuery());

            return $relation->get();
        }

        // Build query from conditions
        $query = Product::query()->active();
        $this->applyOwnerScopeToProductsQuery($query);

        if (! empty($this->conditions)) {
            $this->applyConditions($query, $this->conditions);
        }

        return $query->get();
    }

    /**
     * Rebuild the product list for an automatic collection.
     */
    public function rebuildProductList(): void
    {
        if (! $this->isAutomatic()) {
            return;
        }

        $matchingProducts = $this->getMatchingProducts();

        // Sync to match current rule results (existing pivot positions are preserved)
        $this->products()->sync($matchingProducts->pluck('id'));
    }

    // =========================================================================
    // SCHEDULING HELPERS
    // =========================================================================

    /**
     * Check if the collection is currently published.
     */
    public function isPublished(): bool
    {
        $now = now();

        if ($this->published_at && $now->lt($this->published_at)) {
            return false;
        }

        if ($this->unpublished_at && $now->gte($this->unpublished_at)) {
            return false;
        }

        return $this->is_visible;
    }

    /**
     * Check if the collection is scheduled for future.
     */
    public function isScheduled(): bool
    {
        return $this->published_at && now()->lt($this->published_at);
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopePublished($query)
    {
        $now = now();

        return $query
            ->where('is_visible', true)
            ->where(function ($q) use ($now): void {
                $q->whereNull('published_at')
                    ->orWhere('published_at', '<=', $now);
            })
            ->where(function ($q) use ($now): void {
                $q->whereNull('unpublished_at')
                    ->orWhere('unpublished_at', '>', $now);
            });
    }

    public function scopeManual($query)
    {
        return $query->where('type', 'manual');
    }

    public function scopeAutomatic($query)
    {
        return $query->where('type', 'automatic');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('position');
    }

    // =========================================================================
    // BOOT
    // =========================================================================

    protected static function booted(): void
    {
        static::creating(function (Collection $collection): void {
            if (! (bool) config('products.owner.enabled', true)) {
                return;
            }

            if (! (bool) config('products.owner.auto_assign_on_create', true)) {
                return;
            }

            if ($collection->owner_type !== null || $collection->owner_id !== null) {
                return;
            }

            if (! app()->bound(OwnerResolverInterface::class)) {
                return;
            }

            $owner = app(OwnerResolverInterface::class)->resolve();

            if ($owner === null) {
                return;
            }

            $collection->assignOwner($owner);
        });

        static::deleting(function (Collection $collection): void {
            $collection->products()->detach();
        });
    }

    /**
     * Apply rule conditions to a query.
     *
     * @param  Builder<Product>  $query
     * @param  array<string, mixed>  $conditions
     */
    protected function applyConditions(Builder $query, array $conditions): void
    {
        foreach ($conditions as $condition) {
            $field = $condition['field'] ?? null;
            $operator = $condition['operator'] ?? '=';
            $value = $condition['value'] ?? null;

            if (! $field || $value === null) {
                continue;
            }

            match ($field) {
                'price_min' => $query->where('price', '>=', $value),
                'price_max' => $query->where('price', '<=', $value),
                'type' => $query->where('type', $value),
                'category' => $query->whereHas('categories', fn ($q) => $q->where('category_id', $value)),
                'tag' => $query->whereHas('tags', fn ($q) => $q->where('name->en', $value)->orWhere('name', $value)),
                'is_featured' => $query->where('is_featured', (bool) $value),
                default => $query->where($field, $operator, $value),
            };
        }
    }

    /**
     * Apply owner scoping so collections never leak cross-tenant products.
     *
     * @param  Builder<Product>  $query
     */
    protected function applyOwnerScopeToProductsQuery(Builder $query): void
    {
        if (! (bool) config('products.owner.enabled', true)) {
            return;
        }

        if ($this->owner_type === null || $this->owner_id === null) {
            $query->whereNull('owner_type')->whereNull('owner_id');

            return;
        }

        $ownerType = $this->owner_type;
        $ownerId = $this->owner_id;
        $includeGlobal = (bool) config('products.owner.include_global', true);

        $query->where(function (Builder $builder) use ($ownerType, $ownerId, $includeGlobal): void {
            $builder->where('owner_type', $ownerType)
                ->where('owner_id', $ownerId);

            if ($includeGlobal) {
                $builder->orWhere(function (Builder $inner): void {
                    $inner->whereNull('owner_type')->whereNull('owner_id');
                });
            }
        });
    }
}
