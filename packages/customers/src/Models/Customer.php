<?php

declare(strict_types=1);

namespace AIArmada\Customers\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\Customers\Enums\CustomerStatus;
use AIArmada\Customers\Events\CustomerCreated;
use AIArmada\Customers\Events\CustomerUpdated;
use AIArmada\Customers\Events\WalletCreditAdded;
use AIArmada\Customers\Events\WalletCreditDeducted;
use Akaunting\Money\Money;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property int|null $user_id
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string|null $phone
 * @property string|null $company
 * @property CustomerStatus $status
 * @property int $wallet_balance
 * @property int $lifetime_value
 * @property int $total_orders
 * @property bool $accepts_marketing
 * @property bool $is_tax_exempt
 * @property string|null $tax_exempt_reason
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $last_order_at
 * @property Carbon|null $last_login_at
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string $full_name
 * @property-read Model|null $user
 * @property-read Model|null $owner
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Address> $addresses
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Segment> $segments
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Wishlist> $wishlists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CustomerNote> $notes
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CustomerGroup> $groups
 */
class Customer extends Model
{
    use HasFactory;
    use HasOwner;
    use HasUuids;
    use SoftDeletes;

    protected $guarded = ['id'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'status' => CustomerStatus::class,
        'wallet_balance' => 'integer',
        'lifetime_value' => 'integer',
        'total_orders' => 'integer',
        'email_verified_at' => 'datetime',
        'last_order_at' => 'datetime',
        'last_login_at' => 'datetime',
        'accepts_marketing' => 'boolean',
        'is_tax_exempt' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
        'wallet_balance' => 0,
        'lifetime_value' => 0,
        'total_orders' => 0,
        'accepts_marketing' => true,
        'is_tax_exempt' => false,
    ];

    /**
     * @var array<string, class-string>
     */
    protected $dispatchesEvents = [
        'created' => CustomerCreated::class,
        'updated' => CustomerUpdated::class,
    ];

    public function getTable(): string
    {
        return config('customers.tables.customers', 'customers');
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Get the associated user.
     *
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('customers.user_model', \App\Models\User::class), 'user_id');
    }

    /**
     * Get the customer's addresses.
     *
     * @return HasMany<Address, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class, 'customer_id');
    }

    /**
     * Get the customer's segments.
     *
     * @return BelongsToMany<Segment, $this>
     */
    public function segments(): BelongsToMany
    {
        return $this->belongsToMany(
            Segment::class,
            config('customers.tables.segment_customer', 'customer_segment_customer'),
            'customer_id',
            'segment_id'
        )->withTimestamps();
    }

    /**
     * Get the customer's wishlists.
     *
     * @return HasMany<Wishlist, $this>
     */
    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class, 'customer_id');
    }

    /**
     * Get the customer's notes.
     *
     * @return HasMany<CustomerNote, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(CustomerNote::class, 'customer_id')->latest();
    }

    /**
     * Get the customer's group memberships.
     *
     * @return BelongsToMany<CustomerGroup, $this>
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(
            CustomerGroup::class,
            config('customers.tables.group_members', 'customer_group_members'),
            'customer_id',
            'group_id'
        )->withPivot(['role', 'joined_at'])->withTimestamps();
    }

    // =========================================================================
    // ADDRESS HELPERS
    // =========================================================================

    /**
     * Get the default billing address.
     */
    public function getDefaultBillingAddress(): ?Address
    {
        return $this->addresses()
            ->where('is_default_billing', true)
            ->first();
    }

    /**
     * Get the default shipping address.
     */
    public function getDefaultShippingAddress(): ?Address
    {
        return $this->addresses()
            ->where('is_default_shipping', true)
            ->first();
    }

    // =========================================================================
    // WALLET HELPERS
    // =========================================================================

    /**
     * Get the formatted wallet balance.
     */
    public function getFormattedWalletBalance(): string
    {
        $currency = config('customers.wallet.currency', 'MYR');

        return Money::$currency($this->wallet_balance, true)->format();
    }

    /**
     * Add credit to wallet.
     */
    public function addCredit(int $amountInCents, ?string $reason = null): bool
    {
        $maxBalance = config('customers.wallet.max_balance', 100000_00);

        if (($this->wallet_balance + $amountInCents) > $maxBalance) {
            return false;
        }

        $this->increment('wallet_balance', $amountInCents);

        event(new WalletCreditAdded($this, $amountInCents, $reason));

        return true;
    }

    /**
     * Deduct credit from wallet.
     */
    public function deductCredit(int $amountInCents, ?string $reason = null): bool
    {
        if ($this->wallet_balance < $amountInCents) {
            return false;
        }

        $this->decrement('wallet_balance', $amountInCents);

        event(new WalletCreditDeducted($this, $amountInCents, $reason));

        return true;
    }

    /**
     * Check if customer has sufficient wallet balance.
     */
    public function hasWalletBalance(int $amountInCents): bool
    {
        return $this->wallet_balance >= $amountInCents;
    }

    // =========================================================================
    // LTV HELPERS
    // =========================================================================

    /**
     * Get the formatted lifetime value.
     */
    public function getFormattedLifetimeValue(): string
    {
        $currency = config('customers.wallet.currency', 'MYR');

        return Money::$currency($this->lifetime_value, true)->format();
    }

    /**
     * Get the average order value.
     */
    public function getAverageOrderValue(): int
    {
        if ($this->total_orders === 0) {
            return 0;
        }

        return (int) ($this->lifetime_value / $this->total_orders);
    }

    /**
     * Record a new order.
     */
    public function recordOrder(int $orderValueInCents): void
    {
        $this->increment('total_orders');
        $this->increment('lifetime_value', $orderValueInCents);
        $this->update(['last_order_at' => now()]);
    }

    // =========================================================================
    // STATUS HELPERS
    // =========================================================================

    public function isActive(): bool
    {
        return $this->status === CustomerStatus::Active;
    }

    public function isSuspended(): bool
    {
        return $this->status === CustomerStatus::Suspended;
    }

    public function canPlaceOrders(): bool
    {
        return $this->status->canPlaceOrders();
    }

    // =========================================================================
    // MARKETING HELPERS
    // =========================================================================

    public function acceptsMarketing(): bool
    {
        return $this->accepts_marketing;
    }

    public function optInMarketing(): void
    {
        $this->update(['accepts_marketing' => true]);
    }

    public function optOutMarketing(): void
    {
        $this->update(['accepts_marketing' => false]);
    }

    // =========================================================================
    // FULL NAME
    // =========================================================================

    public function getFullNameAttribute(): string
    {
        return mb_trim("{$this->first_name} {$this->last_name}");
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeActive($query)
    {
        return $query->where('status', CustomerStatus::Active);
    }

    public function scopeAcceptsMarketing($query)
    {
        return $query->where('accepts_marketing', true);
    }

    public function scopeHighValue($query, int $minLifetimeValue = 1000_00)
    {
        return $query->where('lifetime_value', '>=', $minLifetimeValue);
    }

    public function scopeInSegment($query, string | Segment $segment)
    {
        $segmentId = $segment instanceof Segment ? $segment->id : $segment;

        return $query->whereHas('segments', fn ($q) => $q->where('segment_id', $segmentId));
    }

    public function scopeRecentlyActive($query, int $days = 30)
    {
        return $query->where('last_login_at', '>=', now()->subDays($days));
    }

    // =========================================================================
    // BOOT
    // =========================================================================

    protected static function booted(): void
    {
        static::deleting(function (Customer $customer): void {
            $customer->addresses()->delete();
            $customer->wishlists()->delete();
            $customer->notes()->delete();
            $customer->segments()->detach();
            $customer->groups()->detach();
        });
    }
}
