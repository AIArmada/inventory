<?php

declare(strict_types=1);

namespace AIArmada\Cart\Models;

use AIArmada\Cart\Collections\CartCollection;
use AIArmada\Cart\Collections\CartConditionCollection;
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Cart Eloquent Model for database persistence.
 *
 * This model provides an Eloquent interface to cart data stored in the database.
 * It works alongside the Cart value object and DatabaseStorage for hybrid access patterns.
 *
 * @property string $id
 * @property string $identifier
 * @property string $instance
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property array<string, mixed>|null $items
 * @property array<string, mixed>|null $conditions
 * @property array<string, mixed>|null $metadata
 * @property int $version
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read CartCollection<int, CartItem> $cartItems
 * @property-read CartConditionCollection<int, CartCondition> $cartConditions
 */
class CartModel extends Model
{
    use HasOwner {
        scopeForOwner as baseScopeForOwner;
    }
    use HasOwnerScopeConfig;
    use HasUuids;

    /**
     * Config key for owner scope configuration.
     */
    protected static string $ownerScopeConfigKey = 'cart.owner';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'identifier',
        'instance',
        'owner_type',
        'owner_id',
        'items',
        'conditions',
        'metadata',
        'version',
        'expires_at',
    ];

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return config('cart.database.table', 'carts');
    }

    /**
     * Get items as a CartCollection of CartItem objects.
     *
     * @return CartCollection<int, CartItem>
     */
    public function getCartItemsAttribute(): CartCollection
    {
        $items = $this->items ?? [];
        $cartItems = [];

        foreach ($items as $itemData) {
            if (is_array($itemData)) {
                $cartItems[] = CartItem::fromArray($itemData);
            }
        }

        return new CartCollection($cartItems);
    }

    /**
     * Get conditions as a CartConditionCollection.
     *
     * @return CartConditionCollection<int, CartCondition>
     */
    public function getCartConditionsAttribute(): CartConditionCollection
    {
        $conditions = $this->conditions ?? [];
        $cartConditions = [];

        foreach ($conditions as $conditionData) {
            if (is_array($conditionData)) {
                $cartConditions[] = CartCondition::fromArray($conditionData);
            }
        }

        return new CartConditionCollection($cartConditions);
    }

    /**
     * Check if cart has expired.
     */
    public function isExpired(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        return now()->isAfter($this->expires_at);
    }

    /**
     * Check if cart is empty.
     */
    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    /**
     * Get the number of items in the cart.
     */
    public function getItemCount(): int
    {
        return count($this->items ?? []);
    }

    /**
     * Get total quantity of all items.
     */
    public function getTotalQuantity(): int
    {
        $items = $this->items ?? [];
        $total = 0;

        foreach ($items as $item) {
            if (is_array($item) && isset($item['quantity'])) {
                $total += (int) $item['quantity'];
            }
        }

        return $total;
    }

    /**
     * Get metadata value by key.
     */
    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Application-level cascade delete.
     */
    protected static function booted(): void
    {
        // No cascades needed - carts are standalone
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'items' => 'array',
            'conditions' => 'array',
            'metadata' => 'array',
            'version' => 'integer',
            'expires_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * Scope to filter by identifier.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function forIdentifier(Builder $query, string $identifier): void
    {
        $query->where('identifier', $identifier);
    }

    /**
     * Scope to filter by instance.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function forInstance(Builder $query, string $instance): void
    {
        $query->where('instance', $instance);
    }

    /**
     * Scope to find by identifier and instance.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function forCart(Builder $query, string $identifier, string $instance): void
    {
        $query->where('identifier', $identifier)
            ->where('instance', $instance);
    }

    /**
     * Scope to filter expired carts.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function expired(Builder $query): void
    {
        $query->whereNotNull('expires_at')
            ->where('expires_at', '<', now());
    }

    /**
     * Scope to filter non-expired carts.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function notExpired(Builder $query): void
    {
        $query->where(function (Builder $q): void {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Scope to filter non-empty carts.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function withItems(Builder $query): void
    {
        // DB-agnostic approach: check for non-empty JSON array
        // Works on MySQL, PostgreSQL, and SQLite
        $query->whereNotNull('items')
            ->where('items', '!=', '[]')
            ->where('items', '!=', '{}');
    }

    /**
     * Scope to filter carts inactive for specified minutes.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function inactiveFor(Builder $query, int $minutes): void
    {
        $threshold = now()->subMinutes($minutes);
        $query->where('updated_at', '<', $threshold);
    }

    /**
     * Scope to order by most recent activity.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function recentActivity(Builder $query): void
    {
        $query->orderByDesc('updated_at');
    }
}
