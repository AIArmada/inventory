<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Models;

use Akaunting\Money\Money;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cart Item Model
 *
 * Normalized representation of cart items for efficient querying.
 * This model is readonly and only updated via cart events
 * to maintain data consistency with the cart package.
 *
 * @property int $cart_id
 * @property string $item_id
 * @property string $name
 * @property int $price
 * @property int $quantity
 * @property array<mixed>|null $attributes
 * @property array<mixed>|null $conditions
 * @property string|null $associated_model
 */
final class CartItem extends Model
{
    /** @use HasFactory<\AIArmada\FilamentCart\Database\Factories\CartItemFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = true;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'cart_id',
        'item_id',
        'name',
        'price',
        'quantity',
        'attributes',
        'conditions',
        'associated_model',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'price' => 'integer',
        'quantity' => 'integer',
        'attributes' => 'array',
        'conditions' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function getTable(): string
    {
        $tables = config('filament-cart.database.tables', []);
        $prefix = config('filament-cart.database.table_prefix', 'cart_');

        return $tables['snapshot_items'] ?? $prefix . 'snapshot_items';
    }

    /**
     * Get the computed subtotal (price × quantity).
     */
    public function getSubtotalAttribute(): int
    {
        return $this->price * $this->quantity;
    }

    /**
     * Get the cart that owns this item.
     *
     * @return BelongsTo<Cart, CartItem>
     */
    /** @phpstan-ignore return.type, missingType.generics */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * Get price in dollars (converted from cents).
     */
    public function getPriceInDollarsAttribute(): float
    {
        return $this->price / 100;
    }

    /**
     * Get subtotal in dollars (converted from cents).
     */
    public function getSubtotalInDollarsAttribute(): float
    {
        return $this->subtotal / 100;
    }

    /**
     * Get formatted price.
     */
    public function getFormattedPriceAttribute(): string
    {
        return $this->formatMoney($this->price);
    }

    /**
     * Get formatted subtotal.
     */
    public function getFormattedSubtotalAttribute(): string
    {
        return $this->formatMoney($this->subtotal);
    }

    /**
     * Check if item has conditions.
     */
    public function hasConditions(): bool
    {
        return ! empty($this->conditions) && is_array($this->conditions) && count($this->conditions) > 0;
    }

    /**
     * Get conditions count.
     */
    public function getConditionsCountAttribute(): int
    {
        return $this->hasConditions() ? count($this->conditions) : 0;
    }

    /**
     * Get attributes count.
     */
    public function getAttributesCountAttribute(): int
    {
        $attributes = $this->getAttribute('attributes');

        return ! empty($attributes) && is_array($attributes) ? count($attributes) : 0;
    }

    /**
     * Scope to filter by cart instance.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function instance(Builder $query, string $instance): void
    {
        $query->whereHas('cart', function ($q) use ($instance): void {
            $q->where('instance', $instance);
        });
    }

    /**
     * Scope to filter by cart identifier.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function byIdentifier(Builder $query, string $identifier): void
    {
        $query->whereHas('cart', function ($q) use ($identifier): void {
            $q->where('identifier', $identifier);
        });
    }

    /**
     * Scope to filter by item name.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function byName(Builder $query, string $name): void
    {
        $query->where('name', 'like', "%{$name}%");
    }

    /**
     * Scope to filter by price range.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function priceBetween(Builder $query, float $min, float $max): void
    {
        // Convert dollars to cents for comparison
        $query->whereBetween('price', [$min * 100, $max * 100]);
    }

    /**
     * Scope to filter by quantity range.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function quantityBetween(Builder $query, int $min, int $max): void
    {
        $query->whereBetween('quantity', [$min, $max]);
    }

    /**
     * Scope to get items with conditions.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function withConditions(Builder $query): void
    {
        $query->whereNotNull('conditions')
            ->whereJsonLength('conditions', '>', 0);
    }

    /**
     * Scope to get items without conditions.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function withoutConditions(Builder $query): void
    {
        $query->whereNull('conditions')
            ->orWhereJsonLength('conditions', '=', 0);
    }

    private function formatMoney(int $amount): string
    {
        $currency = mb_strtoupper($this->cart->currency ?? config('cart.money.default_currency', 'USD'));

        return (string) Money::{$currency}($amount);
    }
}
