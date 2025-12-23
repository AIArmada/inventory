<?php

declare(strict_types=1);

namespace AIArmada\Products\Models;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Pricing\Contracts\Priceable as PricingPriceable;
use AIArmada\Products\Contracts\Buyable;
use AIArmada\Products\Contracts\Inventoryable;
use AIArmada\Products\Contracts\Priceable;
use AIArmada\Products\Database\Factories\ProductFactory;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Enums\ProductType;
use AIArmada\Products\Enums\ProductVisibility;
use AIArmada\Products\Traits\HasAttributes;
use Akaunting\Money\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Spatie\Tags\HasTags;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $short_description
 * @property string|null $sku
 * @property string|null $barcode
 * @property ProductType $type
 * @property ProductStatus $status
 * @property ProductVisibility $visibility
 * @property int $price
 * @property int|null $compare_price
 * @property int|null $cost
 * @property string $currency
 * @property float|null $weight
 * @property float|null $length
 * @property float|null $width
 * @property float|null $height
 * @property string $weight_unit
 * @property string $dimension_unit
 * @property bool $is_featured
 * @property bool $is_taxable
 * @property bool $requires_shipping
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property string|null $tax_class
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Variant> $variants
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Option> $options
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Category> $categories
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Collection> $collections
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AttributeValue> $attributeValues
 */
class Product extends Model implements Buyable, HasMedia, Inventoryable, Priceable, PricingPriceable
{
    use HasAttributes;
    use HasFactory;
    use HasOwner {
        scopeForOwner as baseScopeForOwner;
    }
    use HasOwnerScopeConfig;
    use HasSlug;
    use HasTags;
    use HasUuids;
    use InteractsWithMedia;

    protected static string $ownerScopeConfigKey = 'products.features.owner';

    protected $guarded = ['id'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'type' => ProductType::class,
        'status' => ProductStatus::class,
        'visibility' => ProductVisibility::class,
        'price' => 'integer',
        'compare_price' => 'integer',
        'cost' => 'integer',
        'weight' => 'decimal:2',
        'length' => 'decimal:2',
        'width' => 'decimal:2',
        'height' => 'decimal:2',
        'is_featured' => 'boolean',
        'is_taxable' => 'boolean',
        'requires_shipping' => 'boolean',
        'metadata' => 'array',
        'published_at' => 'datetime',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'simple',
        'status' => 'draft',
        'visibility' => 'catalog_search',
        'is_featured' => false,
        'is_taxable' => true,
        'requires_shipping' => true,
    ];

    public function getTable(): string
    {
        $tables = config('products.database.tables', []);

        return $tables['products'] ?? 'products';
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

        /** @var Builder<Product> $scoped */
        $scoped = $this->baseScopeForOwner($query, $ownerToScope, $includeGlobalToScope);

        return $scoped;
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Get the product's variants.
     *
     * @return HasMany<Variant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(Variant::class, 'product_id');
    }

    /**
     * Get the product's options.
     *
     * @return HasMany<Option, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(Option::class, 'product_id');
    }

    /**
     * Get the categories the product belongs to.
     *
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            Category::class,
            config('products.database.tables.category_product', 'category_product'),
            'product_id',
            'category_id'
        )->withTimestamps();
    }

    /**
     * Get the collections the product belongs to.
     *
     * @return BelongsToMany<Collection, $this>
     */
    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(
            Collection::class,
            config('products.database.tables.collection_product', 'collection_product'),
            'product_id',
            'collection_id'
        )->withTimestamps();
    }

    // =========================================================================
    // SPATIE MEDIALIBRARY
    // =========================================================================

    public function registerMediaCollections(): void
    {
        /** @var array{limit?:int, mimes?:array<int,string>} $gallery */
        $gallery = config('products.media.collections.gallery', []);
        /** @var array{limit?:int, mimes?:array<int,string>} $hero */
        $hero = config('products.media.collections.hero', []);
        /** @var array{limit?:int, mimes?:array<int,string>} $videos */
        $videos = config('products.media.collections.videos', []);
        /** @var array{limit?:int, mimes?:array<int,string>} $documents */
        $documents = config('products.media.collections.documents', []);

        $galleryCollection = $this->addMediaCollection('gallery')
            ->acceptsMimeTypes($gallery['mimes'] ?? ['image/jpeg', 'image/png', 'image/webp'])
            ->useFallbackUrl('/images/product-placeholder.jpg')
            ->useFallbackPath(public_path('/images/product-placeholder.jpg'));

        if (isset($gallery['limit']) && (int) $gallery['limit'] > 0) {
            $galleryCollection->onlyKeepLatest((int) $gallery['limit']);
        }

        $this->addMediaCollection('hero')
            ->singleFile()
            ->acceptsMimeTypes($hero['mimes'] ?? ['image/jpeg', 'image/png', 'image/webp']);

        $videosCollection = $this->addMediaCollection('videos')
            ->acceptsMimeTypes($videos['mimes'] ?? ['video/mp4', 'video/webm']);

        if (isset($videos['limit']) && (int) $videos['limit'] > 0) {
            $videosCollection->onlyKeepLatest((int) $videos['limit']);
        }

        $documentsCollection = $this->addMediaCollection('documents')
            ->acceptsMimeTypes($documents['mimes'] ?? ['application/pdf']);

        if (isset($documents['limit']) && (int) $documents['limit'] > 0) {
            $documentsCollection->onlyKeepLatest((int) $documents['limit']);
        }
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        /** @var array{width?:int, height?:int, sharpen?:int} $thumbnail */
        $thumbnail = config('products.media.conversions.thumbnail', []);
        /** @var array{width?:int, height?:int} $card */
        $card = config('products.media.conversions.card', []);
        /** @var array{width?:int, height?:int} $detail */
        $detail = config('products.media.conversions.detail', []);
        /** @var array{width?:int, height?:int} $zoom */
        $zoom = config('products.media.conversions.zoom', []);
        /** @var array{width?:int, height?:int} $webpCard */
        $webpCard = config('products.media.conversions.webp-card', []);

        $this->addMediaConversion('thumbnail')
            ->width($thumbnail['width'] ?? 150)
            ->height($thumbnail['height'] ?? 150)
            ->sharpen($thumbnail['sharpen'] ?? 10)
            ->optimize();

        $this->addMediaConversion('card')
            ->width($card['width'] ?? 400)
            ->height($card['height'] ?? 400)
            ->optimize();

        $this->addMediaConversion('detail')
            ->width($detail['width'] ?? 800)
            ->height($detail['height'] ?? 800)
            ->optimize();

        $this->addMediaConversion('zoom')
            ->width($zoom['width'] ?? 1600)
            ->height($zoom['height'] ?? 1600)
            ->optimize();

        $this->addMediaConversion('webp-card')
            ->width($webpCard['width'] ?? 400)
            ->height($webpCard['height'] ?? 400)
            ->format('webp')
            ->optimize();
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
    // MONEY HELPERS
    // =========================================================================

    public function getFormattedPrice(): string
    {
        $currency = mb_strtoupper($this->currency ?: config('products.defaults.currency', 'MYR'));
        $asMajorUnits = ! (bool) config('products.defaults.store_money_in_cents', true);

        return Money::$currency($this->price, $asMajorUnits)->format();
    }

    public function getFormattedComparePrice(): ?string
    {
        if (! $this->compare_price) {
            return null;
        }

        $currency = mb_strtoupper($this->currency ?: config('products.defaults.currency', 'MYR'));
        $asMajorUnits = ! (bool) config('products.defaults.store_money_in_cents', true);

        return Money::$currency($this->compare_price, $asMajorUnits)->format();
    }

    public function getFormattedCost(): ?string
    {
        if (! $this->cost) {
            return null;
        }

        $currency = mb_strtoupper($this->currency ?: config('products.defaults.currency', 'MYR'));
        $asMajorUnits = ! (bool) config('products.defaults.store_money_in_cents', true);

        return Money::$currency($this->cost, $asMajorUnits)->format();
    }

    public function getPriceAsMoney(): Money
    {
        $currency = mb_strtoupper($this->currency ?: config('products.defaults.currency', 'MYR'));
        $asMajorUnits = ! (bool) config('products.defaults.store_money_in_cents', true);

        return Money::$currency($this->price, $asMajorUnits);
    }

    // =========================================================================
    // STATUS HELPERS
    // =========================================================================

    public function isActive(): bool
    {
        return $this->status === ProductStatus::Active;
    }

    public function isDraft(): bool
    {
        return $this->status === ProductStatus::Draft;
    }

    public function isVisible(): bool
    {
        return $this->status->isVisible();
    }

    public function isPurchasable(): bool
    {
        return $this->status->isPurchasable();
    }

    public function activate(): self
    {
        $this->status = ProductStatus::Active;
        $this->published_at ??= now();
        $this->save();

        return $this;
    }

    public function archive(): self
    {
        $this->status = ProductStatus::Archived;
        $this->save();

        return $this;
    }

    // =========================================================================
    // TYPE HELPERS
    // =========================================================================

    public function hasVariants(): bool
    {
        return $this->type->hasVariants() && $this->variants()->exists();
    }

    public function isPhysical(): bool
    {
        return $this->type->isPhysical();
    }

    public function isDigital(): bool
    {
        return $this->type === ProductType::Digital;
    }

    public function isSubscription(): bool
    {
        return $this->type === ProductType::Subscription;
    }

    // =========================================================================
    // PRICE HELPERS
    // =========================================================================

    public function hasDiscount(): bool
    {
        return $this->compare_price && $this->compare_price > $this->price;
    }

    public function getDiscountPercentage(): ?float
    {
        if (! $this->hasDiscount()) {
            return null;
        }

        return round((($this->compare_price - $this->price) / $this->compare_price) * 100, 1);
    }

    public function getProfitMargin(): ?float
    {
        if (! $this->cost || $this->cost === 0) {
            return null;
        }

        if ($this->price === 0) {
            return null;
        }

        return round((($this->price - $this->cost) / $this->price) * 100, 1);
    }

    // =========================================================================
    // FEATURED IMAGE
    // =========================================================================

    public function getFeaturedImageUrl(string $conversion = 'card'): ?string
    {
        $hero = $this->getFirstMedia('hero');
        if ($hero) {
            return $hero->getUrl($conversion);
        }

        $gallery = $this->getFirstMedia('gallery');
        if ($gallery) {
            return $gallery->getUrl($conversion);
        }

        return $this->getFallbackMediaUrl('gallery');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeActive($query)
    {
        return $query->where('status', ProductStatus::Active);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeVisible($query)
    {
        return $query->where('status', ProductStatus::Active)
            ->whereIn('visibility', [
                ProductVisibility::Catalog,
                ProductVisibility::CatalogSearch,
            ]);
    }

    public function scopeSearchable($query)
    {
        return $query->where('status', ProductStatus::Active)
            ->whereIn('visibility', [
                ProductVisibility::Search,
                ProductVisibility::CatalogSearch,
            ]);
    }

    public function scopeOfType($query, ProductType $type)
    {
        return $query->where('type', $type);
    }

    public function scopeInCategory($query, Category $category)
    {
        return $query->whereHas('categories', function ($q) use ($category): void {
            $q->where('category_id', $category->id);
        });
    }

    public function scopePriceRange($query, int $min, int $max)
    {
        return $query->whereBetween('price', [$min, $max]);
    }

    // =========================================================================
    // BUYABLE INTERFACE
    // =========================================================================

    public function getBuyableIdentifier(): string
    {
        return $this->id;
    }

    public function getBuyableDescription(): string
    {
        return $this->name;
    }

    public function getBuyablePrice(): int
    {
        return $this->price ?? 0;
    }

    public function getBuyableWeight(): ?float
    {
        return $this->weight !== null ? (float) $this->weight : null;
    }

    public function isBuyable(): bool
    {
        return $this->isPurchasable();
    }

    // =========================================================================
    // PRICEABLE INTERFACE
    // =========================================================================

    public function getBasePrice(): int
    {
        return $this->price ?? 0;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function getCalculatedPrice(array $context = []): int
    {
        // For now, return base price. Pricing package will extend this.
        return $this->getBasePrice();
    }

    public function getComparePrice(): ?int
    {
        return $this->compare_price;
    }

    public function isOnSale(): bool
    {
        return $this->hasDiscount();
    }

    // =========================================================================
    // INVENTORYABLE INTERFACE
    // =========================================================================

    public function getInventorySku(): string
    {
        return $this->sku ?? '';
    }

    public function getStockQuantity(): int
    {
        // Will integrate with inventory package
        return 0;
    }

    public function isInStock(): bool
    {
        // Will integrate with inventory package
        return true;
    }

    public function hasStock(int $quantity): bool
    {
        // Will integrate with inventory package
        return true;
    }

    public function tracksInventory(): bool
    {
        return ! $this->isDigital();
    }

    // =========================================================================
    // BOOT
    // =========================================================================

    protected static function booted(): void
    {
        static::creating(function (Product $product): void {
            if (! (bool) config('products.features.owner.enabled', true)) {
                return;
            }

            $hasOwnerType = $product->owner_type !== null;
            $hasOwnerId = $product->owner_id !== null;

            if ($hasOwnerType !== $hasOwnerId) {
                throw new InvalidArgumentException('Invalid owner columns: owner_type and owner_id must be both set or both null.');
            }

            $owner = OwnerContext::resolve();

            if ($owner !== null && $hasOwnerType && ! $product->belongsToOwner($owner)) {
                throw new InvalidArgumentException('Cross-tenant write blocked: product owner does not match the current owner context.');
            }

            if (! (bool) config('products.features.owner.auto_assign_on_create', true)) {
                return;
            }

            if ($product->owner_type !== null || $product->owner_id !== null) {
                return;
            }

            $owner = OwnerContext::resolve();
            if ($owner === null) {
                return;
            }

            $product->assignOwner($owner);
        });

        static::deleting(function (Product $product): void {
            $product->options()->delete();
            $product->variants()->delete();
            $product->categories()->detach();
            $product->collections()->detach();
        });
    }

    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }
}
