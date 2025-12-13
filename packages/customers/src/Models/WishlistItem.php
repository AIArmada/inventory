<?php

declare(strict_types=1);

namespace AIArmada\Customers\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $wishlist_id
 * @property string $product_type
 * @property string $product_id
 * @property Carbon|null $added_at
 * @property bool $notified_on_sale
 * @property bool $notified_in_stock
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Wishlist $wishlist
 * @property-read Model|null $product
 */
class WishlistItem extends Model
{
    use HasFactory;
    use HasUuids;

    protected $guarded = ['id'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'added_at' => 'datetime',
        'notified_on_sale' => 'boolean',
        'notified_in_stock' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'notified_on_sale' => false,
        'notified_in_stock' => false,
    ];

    public function getTable(): string
    {
        return config('customers.tables.wishlist_items', 'wishlist_items');
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Get the wishlist this item belongs to.
     *
     * @return BelongsTo<Wishlist, $this>
     */
    public function wishlist(): BelongsTo
    {
        return $this->belongsTo(Wishlist::class, 'wishlist_id');
    }

    /**
     * Get the product (polymorphic).
     */
    public function product(): MorphTo
    {
        return $this->morphTo('product', 'product_type', 'product_id');
    }

    // =========================================================================
    // NOTIFICATION HELPERS
    // =========================================================================

    /**
     * Mark that the customer was notified about a sale.
     */
    public function markSaleNotified(): void
    {
        $this->update(['notified_on_sale' => true]);
    }

    /**
     * Mark that the customer was notified about stock.
     */
    public function markStockNotified(): void
    {
        $this->update(['notified_in_stock' => true]);
    }

    /**
     * Reset notification flags (e.g., when price changes again).
     */
    public function resetNotifications(): void
    {
        $this->update([
            'notified_on_sale' => false,
            'notified_in_stock' => false,
        ]);
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeNeedsStockNotification($query)
    {
        return $query->where('notified_in_stock', false);
    }

    public function scopeNeedsSaleNotification($query)
    {
        return $query->where('notified_on_sale', false);
    }

    protected static function booted(): void
    {
        static::creating(function (WishlistItem $item): void {
            if (empty($item->added_at)) {
                $item->added_at = now();
            }
        });
    }
}
