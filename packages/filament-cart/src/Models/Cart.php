<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Models;

use AIArmada\Cart\Cart as BaseCart;
use AIArmada\FilamentCart\Services\CartInstanceManager;
use Akaunting\Money\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * @property string $identifier
 * @property string $instance
 * @property array<mixed>|null $items
 * @property array<mixed>|null $conditions
 * @property array<mixed>|null $metadata
 * @property int $items_count
 * @property int $quantity
 * @property int $subtotal
 * @property int $total
 * @property int $savings
 * @property string $currency
 * @property \Illuminate\Support\Carbon|null $last_activity_at
 * @property \Illuminate\Support\Carbon|null $checkout_started_at
 * @property \Illuminate\Support\Carbon|null $checkout_abandoned_at
 * @property int $recovery_attempts
 * @property \Illuminate\Support\Carbon|null $recovered_at
 * @property bool $is_collaborative
 * @property int $collaborator_count
 * @property string|null $fraud_risk_level
 * @property float|null $fraud_score
 */
class Cart extends Model
{
    /**
     * This model cannot use cascade deletes because there is a dedicated cart sync manager
     * that handles the synchronization and cleanup of cart items and conditions.
     * Cascade handling is managed at the application level through the sync manager.
     */

    /** @use HasFactory<\AIArmada\FilamentCart\Database\Factories\CartFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [
        'identifier',
        'instance',
        'items',
        'conditions',
        'metadata',
        'items_count',
        'quantity',
        'subtotal',
        'total',
        'savings',
        'currency',
        'last_activity_at',
        'checkout_started_at',
        'checkout_abandoned_at',
        'recovery_attempts',
        'recovered_at',
        'is_collaborative',
        'collaborator_count',
        'fraud_risk_level',
        'fraud_score',
    ];

    protected $casts = [
        'items' => 'array',
        'conditions' => 'array',
        'metadata' => 'array',
        'items_count' => 'integer',
        'quantity' => 'integer',
        'subtotal' => 'integer',
        'total' => 'integer',
        'savings' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'checkout_started_at' => 'datetime',
        'checkout_abandoned_at' => 'datetime',
        'recovery_attempts' => 'integer',
        'recovered_at' => 'datetime',
        'is_collaborative' => 'boolean',
        'collaborator_count' => 'integer',
        'fraud_score' => 'float',
    ];

    protected $attributes = [
        'items' => null,
        'conditions' => null,
        'metadata' => null,
        'items_count' => 0,
        'quantity' => 0,
        'subtotal' => 0,
        'total' => 0,
        'savings' => 0,
        'currency' => 'USD',
        'recovery_attempts' => 0,
        'is_collaborative' => false,
        'collaborator_count' => 0,
    ];

    public function getTable(): string
    {
        $tables = config('filament-cart.database.tables', []);

        return $tables['snapshots'] ?? 'cart_snapshots';
    }

    public function getCartInstance(): ?BaseCart
    {
        try {
            return app(CartInstanceManager::class)->resolve($this->instance, $this->identifier);
        } catch (Throwable $exception) {
            Log::warning('Failed to resolve cart instance', [
                'identifier' => $this->identifier,
                'instance' => $this->instance,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    public function getSubtotalInDollarsAttribute(): float
    {
        return $this->subtotal / 100;
    }

    public function getTotalInDollarsAttribute(): float
    {
        return $this->total / 100;
    }

    public function getSavingsInDollarsAttribute(): float
    {
        return $this->savings / 100;
    }

    /** @return HasMany<CartItem, Cart> */
    /** @phpstan-ignore return.type, missingType.generics */
    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /** @return HasMany<CartItem, Cart> */
    public function items(): HasMany
    {
        return $this->cartItems();
    }

    /** @return HasMany<CartCondition, Cart> */
    /** @phpstan-ignore return.type, missingType.generics */
    public function cartConditions(): HasMany
    {
        return $this->hasMany(CartCondition::class);
    }

    /** @return HasMany<CartCondition, Cart> */
    /** @phpstan-ignore method.notFound, missingType.generics */
    public function cartLevelConditions(): HasMany
    {
        /** @phpstan-ignore-next-line */
        return $this->cartConditions()->cartLevel();
    }

    /** @return HasMany<CartCondition, Cart> */
    /** @phpstan-ignore method.notFound, missingType.generics */
    public function itemLevelConditions(): HasMany
    {
        /** @phpstan-ignore-next-line */
        return $this->cartConditions()->itemLevel();
    }

    /** @phpstan-ignore-next-line */
    public function user(): BelongsTo
    {
        /** @var class-string<Model> $userModel */
        $userModel = config('auth.providers.users.model', \Illuminate\Foundation\Auth\User::class);

        return $this->belongsTo($userModel, 'identifier', 'id');
    }

    public function isEmpty(): bool
    {
        return $this->items_count === 0 || $this->quantity === 0;
    }

    public function formatMoney(int $amount): string
    {
        $currency = mb_strtoupper($this->currency ?: config('cart.money.default_currency', 'USD'));

        return (string) Money::{$currency}($amount);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function instance(Builder $query, string $instance): void
    {
        $query->where('instance', $instance);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function byIdentifier(Builder $query, string $identifier): void
    {
        $query->where('identifier', $identifier);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function notEmpty(Builder $query): void
    {
        $query->where('items_count', '>', 0);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function recent(Builder $query, int $days = 7): void
    {
        $query->where('updated_at', '>=', now()->subDays($days));
    }

    /**
     * @param  Builder<self>  $query
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function withSavings(Builder $query): void
    {
        $query->where('savings', '>', 0);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function abandoned(Builder $query): void
    {
        $query->whereNotNull('checkout_abandoned_at')
            ->whereNull('recovered_at');
    }

    /**
     * @param  Builder<self>  $query
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function recovered(Builder $query): void
    {
        $query->whereNotNull('recovered_at');
    }

    /**
     * @param  Builder<self>  $query
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function inCheckout(Builder $query): void
    {
        $query->whereNotNull('checkout_started_at')
            ->whereNull('checkout_abandoned_at');
    }

    /**
     * @param  Builder<self>  $query
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function collaborative(Builder $query): void
    {
        $query->where('is_collaborative', true);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function highFraudRisk(Builder $query): void
    {
        $query->whereIn('fraud_risk_level', ['high', 'medium']);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function needsRecovery(Builder $query): void
    {
        $query->whereNotNull('checkout_abandoned_at')
            ->whereNull('recovered_at')
            ->where('recovery_attempts', '<', 3);
    }

    /**
     * Check if cart is abandoned.
     */
    public function isAbandoned(): bool
    {
        return $this->checkout_abandoned_at !== null && $this->recovered_at === null;
    }

    /**
     * Check if cart is in checkout process.
     */
    public function isInCheckout(): bool
    {
        return $this->checkout_started_at !== null && $this->checkout_abandoned_at === null;
    }

    /**
     * Check if cart was recovered.
     */
    public function isRecovered(): bool
    {
        return $this->recovered_at !== null;
    }

    /**
     * Check if cart has fraud risk.
     */
    public function hasFraudRisk(): bool
    {
        return in_array($this->fraud_risk_level, ['high', 'medium'], true);
    }

    /**
     * Get fraud risk color for display.
     */
    public function getFraudRiskColor(): string
    {
        return match ($this->fraud_risk_level) {
            'high' => 'danger',
            'medium' => 'warning',
            'low' => 'info',
            default => 'gray',
        };
    }

    /** @return Attribute<string, never> */
    protected function formattedSubtotal(): Attribute
    {
        return Attribute::get(fn (): string => $this->formatMoney($this->subtotal));
    }

    /** @return Attribute<string, never> */
    protected function formattedTotal(): Attribute
    {
        return Attribute::get(fn (): string => $this->formatMoney($this->total));
    }

    /** @return Attribute<string, never> */
    protected function formattedSavings(): Attribute
    {
        return Attribute::get(fn (): string => $this->formatMoney($this->savings));
    }
}
