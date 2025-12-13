<?php

declare(strict_types=1);

namespace AIArmada\Customers\Models;

use AIArmada\Customers\Enums\AddressType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $customer_id
 * @property AddressType $type
 * @property string|null $label
 * @property string|null $recipient_name
 * @property string|null $company
 * @property string|null $phone
 * @property string $address_line_1
 * @property string|null $address_line_2
 * @property string $city
 * @property string|null $state
 * @property string $postcode
 * @property string $country
 * @property bool $is_default_billing
 * @property bool $is_default_shipping
 * @property bool $is_verified
 * @property array{lat?: float, lng?: float}|null $coordinates
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $full_address
 * @property-read Customer $customer
 */
class Address extends Model
{
    use HasFactory;
    use HasUuids;

    protected $guarded = ['id'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'type' => AddressType::class,
        'is_default_billing' => 'boolean',
        'is_default_shipping' => 'boolean',
        'is_verified' => 'boolean',
        'coordinates' => 'array', // ['lat' => x, 'lng' => y]
        'metadata' => 'array',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_default_billing' => false,
        'is_default_shipping' => false,
        'is_verified' => false,
    ];

    public function getTable(): string
    {
        return config('customers.tables.addresses', 'customer_addresses');
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Get the customer who owns this address.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    // =========================================================================
    // TYPE HELPERS
    // =========================================================================

    public function isBillingAddress(): bool
    {
        return $this->type->isBilling();
    }

    public function isShippingAddress(): bool
    {
        return $this->type->isShipping();
    }

    // =========================================================================
    // DEFAULT MANAGEMENT
    // =========================================================================

    /**
     * Set this as the default billing address.
     */
    public function setAsDefaultBilling(): void
    {
        // Remove default from other addresses
        $this->customer->addresses()
            ->where('id', '!=', $this->id)
            ->update(['is_default_billing' => false]);

        $this->update(['is_default_billing' => true]);
    }

    /**
     * Set this as the default shipping address.
     */
    public function setAsDefaultShipping(): void
    {
        $this->customer->addresses()
            ->where('id', '!=', $this->id)
            ->update(['is_default_shipping' => false]);

        $this->update(['is_default_shipping' => true]);
    }

    // =========================================================================
    // FORMATTING HELPERS
    // =========================================================================

    /**
     * Get the full address as a single line.
     */
    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address_line_1,
            $this->address_line_2,
            $this->city,
            $this->state,
            $this->postcode,
            $this->country,
        ]);

        return implode(', ', $parts);
    }

    /**
     * Get the address formatted for display (multi-line).
     */
    public function getFormattedAddress(): string
    {
        $lines = [];

        if ($this->label) {
            $lines[] = $this->label;
        }

        if ($this->recipient_name) {
            $lines[] = $this->recipient_name;
        }

        if ($this->company) {
            $lines[] = $this->company;
        }

        $lines[] = $this->address_line_1;

        if ($this->address_line_2) {
            $lines[] = $this->address_line_2;
        }

        $lines[] = implode(' ', array_filter([
            $this->city,
            $this->state,
            $this->postcode,
        ]));

        $lines[] = $this->country;

        if ($this->phone) {
            $lines[] = $this->phone;
        }

        return implode("\n", $lines);
    }

    /**
     * Get the address formatted for a shipping label.
     */
    public function toShippingLabel(): array
    {
        return [
            'name' => $this->recipient_name ?? $this->customer->full_name,
            'company' => $this->company,
            'address1' => $this->address_line_1,
            'address2' => $this->address_line_2,
            'city' => $this->city,
            'state' => $this->state,
            'postcode' => $this->postcode,
            'country' => $this->country,
            'phone' => $this->phone,
        ];
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeBilling($query)
    {
        return $query->whereIn('type', [AddressType::Billing, AddressType::Both]);
    }

    public function scopeShipping($query)
    {
        return $query->whereIn('type', [AddressType::Shipping, AddressType::Both]);
    }

    public function scopeDefaultBilling($query)
    {
        return $query->where('is_default_billing', true);
    }

    public function scopeDefaultShipping($query)
    {
        return $query->where('is_default_shipping', true);
    }

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }
}
