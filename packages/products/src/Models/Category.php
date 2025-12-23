<?php

declare(strict_types=1);

namespace AIArmada\Products\Models;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string|null $parent_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int $position
 * @property bool $is_visible
 * @property bool $is_featured
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Category|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Category> $children
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Product> $products
 */
class Category extends Model implements HasMedia
{
    use HasFactory;
    use HasOwner {
        scopeForOwner as baseScopeForOwner;
    }
    use HasOwnerScopeConfig;
    use HasSlug;
    use HasUuids;
    use InteractsWithMedia;

    protected static string $ownerScopeConfigKey = 'products.features.owner';

    protected $guarded = ['id'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'position' => 'integer',
        'is_visible' => 'boolean',
        'is_featured' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'position' => 0,
        'is_visible' => true,
        'is_featured' => false,
    ];

    public function getTable(): string
    {
        $tables = config('products.database.tables', []);
        $prefix = config('products.database.table_prefix', 'product_');

        return $tables['categories'] ?? $prefix . 'categories';
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForOwner(Builder $query, ?Model $owner = null, bool $includeGlobal = false): Builder
    {
        $ownerToScope = $owner;

        if (func_num_args() < 2) {
            $ownerToScope = OwnerContext::CURRENT;
        }

        $includeGlobalToScope = $includeGlobal;

        if (func_num_args() < 3) {
            $includeGlobalToScope = (bool) config('products.features.owner.include_global', false);
        }

        /** @var Builder<Category> $scoped */
        $scoped = $this->baseScopeForOwner($query, $ownerToScope, $includeGlobalToScope);

        return $scoped;
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Get the parent category.
     *
     * @return BelongsTo<Category, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Get the child categories.
     *
     * @return HasMany<Category, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }

    /**
     * Get all descendant categories recursively.
     *
     * @return HasMany<Category, $this>
     */
    public function descendants(): HasMany
    {
        return $this->children()->with('descendants');
    }

    /**
     * Get the products in this category.
     *
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        $relation = $this->belongsToMany(
            Product::class,
            config('products.database.tables.category_product', 'category_product'),
            'category_id',
            'product_id'
        )->withTimestamps();

        $this->applyOwnerScopeToProductsQuery($relation->getQuery());

        return $relation;
    }

    /**
     * Apply owner scoping so category relationships never leak cross-tenant products.
     *
     * @param  Builder<Product>  $query
     */
    protected function applyOwnerScopeToProductsQuery(Builder $query): void
    {
        if (! (bool) config('products.features.owner.enabled', true)) {
            return;
        }

        $query->withoutOwnerScope();

        if ($this->owner_type === null || $this->owner_id === null) {
            $query->whereNull('owner_type')->whereNull('owner_id');

            return;
        }

        $ownerType = $this->owner_type;
        $ownerId = $this->owner_id;
        $includeGlobal = (bool) config('products.features.owner.include_global', false);

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

    // =========================================================================
    // SPATIE MEDIALIBRARY
    // =========================================================================

    public function registerMediaCollections(): void
    {
        /** @var array{mimes?:array<int,string>} $hero */
        $hero = config('products.media.collections.hero', []);
        /** @var array{mimes?:array<int,string>} $icon */
        $icon = config('products.media.collections.icon', []);
        /** @var array{mimes?:array<int,string>} $banner */
        $banner = config('products.media.collections.banner', []);

        $this->addMediaCollection('hero')
            ->singleFile()
            ->acceptsMimeTypes($hero['mimes'] ?? ['image/jpeg', 'image/png', 'image/webp']);

        $this->addMediaCollection('icon')
            ->singleFile()
            ->acceptsMimeTypes($icon['mimes'] ?? ['image/jpeg', 'image/png', 'image/webp']);

        $this->addMediaCollection('banner')
            ->singleFile()
            ->acceptsMimeTypes($banner['mimes'] ?? ['image/jpeg', 'image/png', 'image/webp']);
    }

    // =========================================================================
    // SPATIE SLUGGABLE
    // =========================================================================

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->slugsShouldBeNoLongerThan((int) config('products.seo.slug_max_length', 100));
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    // =========================================================================
    // HIERARCHY HELPERS
    // =========================================================================

    /**
     * Check if this is a root category.
     */
    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    /**
     * Check if this category has children.
     */
    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    /**
     * Get all ancestors (parents, grandparents, etc.).
     */
    public function getAncestors(): Collection
    {
        $ancestors = collect();
        $category = $this;

        while ($category->parent !== null) {
            $ancestors->push($category->parent);
            $category = $category->parent;
        }

        return $ancestors->reverse();
    }

    /**
     * Get the depth of this category in the tree.
     */
    public function getDepth(): int
    {
        return $this->getAncestors()->count();
    }

    /**
     * Get the full path of category names.
     * e.g., "Electronics > Phones > Smartphones"
     */
    public function getFullPath(string $separator = ' > '): string
    {
        $path = $this->getAncestors()
            ->pluck('name')
            ->push($this->name);

        return $path->implode($separator);
    }

    /**
     * Get the full slug path.
     * e.g., "electronics/phones/smartphones"
     */
    public function getFullSlug(): string
    {
        $path = $this->getAncestors()
            ->pluck('slug')
            ->push($this->slug);

        return $path->implode('/');
    }

    /**
     * Get a nested tree of all descendants.
     */
    public function getNestedTree(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'children' => $this->children->map(fn ($child) => $child->getNestedTree())->toArray(),
        ];
    }

    // =========================================================================
    // PRODUCT HELPERS
    // =========================================================================

    /**
     * Get total product count including descendants.
     */
    public function getProductCount(bool $includeDescendants = true): int
    {
        $count = $this->products()->count();

        if ($includeDescendants) {
            foreach ($this->children as $child) {
                $count += $child->getProductCount(true);
            }
        }

        return $count;
    }

    /**
     * Get all products including descendants.
     */
    public function getAllProducts(): Collection
    {
        $products = $this->products()->get();

        foreach ($this->children()->get() as $child) {
            $products = $products->merge($child->getAllProducts());
        }

        return $products->unique('id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
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
        static::creating(function (Category $category): void {
            if (! (bool) config('products.features.owner.enabled', true)) {
                return;
            }

            $hasOwnerType = $category->owner_type !== null;
            $hasOwnerId = $category->owner_id !== null;

            if ($hasOwnerType !== $hasOwnerId) {
                throw new InvalidArgumentException('Invalid owner columns: owner_type and owner_id must be both set or both null.');
            }

            $owner = OwnerContext::resolve();

            if ($owner !== null && $hasOwnerType && ! $category->belongsToOwner($owner)) {
                throw new InvalidArgumentException('Cross-tenant write blocked: category owner does not match the current owner context.');
            }

            if (! (bool) config('products.features.owner.auto_assign_on_create', true)) {
                return;
            }

            if ($category->owner_type !== null || $category->owner_id !== null) {
                return;
            }

            if ($owner === null) {
                return;
            }

            $category->assignOwner($owner);
        });

        static::deleting(function (Category $category): void {
            // Nullify parent_id for children
            $category->children()->update(['parent_id' => null]);
            // Detach from products pivot
            $category->products()->detach();
        });
    }
}
