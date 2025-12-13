<?php

declare(strict_types=1);

namespace AIArmada\Customers\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * @property string $id
 * @property string $customer_id
 * @property string $name
 * @property string|null $description
 * @property bool $is_public
 * @property string $share_token
 * @property bool $is_default
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Customer $customer
 * @property-read \Illuminate\Database\Eloquent\Collection<int, WishlistItem> $items
 */
class Wishlist extends Model
{
    use HasFactory;
    use HasUuids;

    protected $guarded = ['id'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_public' => 'boolean',
        'is_default' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_public' => false,
        'is_default' => false,
    ];

    public function getTable(): string
    {
        return config('customers.tables.wishlists', 'wishlists');
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Get the customer who owns this wishlist.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Get the wishlist items.
     *
     * @return HasMany<WishlistItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(WishlistItem::class, 'wishlist_id');
    }

    // =========================================================================
    // ITEM MANAGEMENT
    // =========================================================================

    /**
     * Add a product to this wishlist.
     *
     * @param  string  $productType  Model class name
     * @param  string  $productId  Product ID
     */
    public function addProduct(string $productType, string $productId, ?array $metadata = null): WishlistItem
    {
        $maxItems = config('customers.wishlists.max_items_per_wishlist', 100);

        if ($this->items()->count() >= $maxItems) {
            throw new RuntimeException("Maximum wishlist items ({$maxItems}) reached.");
        }

        // Check if already exists
        $existing = $this->items()
            ->where('product_type', $productType)
            ->where('product_id', $productId)
            ->first();

        if ($existing) {
            return $existing;
        }

        return $this->items()->create([
            'product_type' => $productType,
            'product_id' => $productId,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Remove a product from this wishlist.
     */
    public function removeProduct(string $productType, string $productId): bool
    {
        return $this->items()
            ->where('product_type', $productType)
            ->where('product_id', $productId)
            ->delete() > 0;
    }

    /**
     * Check if a product is in this wishlist.
     */
    public function hasProduct(string $productType, string $productId): bool
    {
        return $this->items()
            ->where('product_type', $productType)
            ->where('product_id', $productId)
            ->exists();
    }

    /**
     * Clear all items from this wishlist.
     */
    public function clear(): void
    {
        $this->items()->delete();
    }

    // =========================================================================
    // SHARING HELPERS
    // =========================================================================

    /**
     * Get the shareable URL for this wishlist.
     */
    public function getShareUrl(): string
    {
        return url("/wishlist/shared/{$this->share_token}");
    }

    /**
     * Make this wishlist public.
     */
    public function makePublic(): void
    {
        $this->update(['is_public' => true]);
    }

    /**
     * Make this wishlist private.
     */
    public function makePrivate(): void
    {
        $this->update(['is_public' => false]);
    }

    /**
     * Regenerate the share token.
     */
    public function regenerateShareToken(): void
    {
        $this->update(['share_token' => Str::random(32)]);
    }

    // =========================================================================
    // DEFAULT MANAGEMENT
    // =========================================================================

    /**
     * Set this as the default wishlist.
     */
    public function setAsDefault(): void
    {
        // Remove default from other wishlists
        $this->customer->wishlists()
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->update(['is_default' => true]);
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    protected static function booted(): void
    {
        static::creating(function (Wishlist $wishlist): void {
            if (empty($wishlist->share_token)) {
                $wishlist->share_token = Str::random(32);
            }
        });

        static::deleting(function (Wishlist $wishlist): void {
            $wishlist->items()->delete();
        });
    }
}
