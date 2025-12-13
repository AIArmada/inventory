<?php

declare(strict_types=1);

namespace AIArmada\Customers\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\Customers\Enums\SegmentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property SegmentType $type
 * @property array<int, array{field: string, operator?: string, value: mixed}>|null $conditions
 * @property bool $is_automatic
 * @property int $priority
 * @property bool $is_active
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|null $owner
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Customer> $customers
 */
class Segment extends Model
{
    use HasFactory;
    use HasOwner;
    use HasUuids;

    protected $guarded = ['id'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'type' => SegmentType::class,
        'conditions' => 'array',
        'is_active' => 'boolean',
        'is_automatic' => 'boolean',
        'priority' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'is_automatic' => true,
        'priority' => 0,
    ];

    public function getTable(): string
    {
        return config('customers.tables.segments', 'customer_segments');
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Get the customers in this segment.
     *
     * @return BelongsToMany<Customer, $this>
     */
    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(
            Customer::class,
            config('customers.tables.segment_customer', 'customer_segment_customer'),
            'segment_id',
            'customer_id'
        )->withTimestamps();
    }

    // =========================================================================
    // CONDITION ENGINE
    // =========================================================================

    /**
     * Get customers matching the segment conditions.
     */
    public function getMatchingCustomers(): \Illuminate\Support\Collection
    {
        if (! $this->is_automatic || empty($this->conditions)) {
            return $this->customers;
        }

        $query = Customer::query()->active();
        $this->applyConditions($query, $this->conditions);

        return $query->get();
    }

    /**
     * Rebuild the customer list for automatic segments.
     */
    public function rebuildCustomerList(): int
    {
        if (! $this->is_automatic) {
            return $this->customers()->count();
        }

        $matchingCustomers = $this->getMatchingCustomers();
        $this->customers()->sync($matchingCustomers->pluck('id'));

        return $matchingCustomers->count();
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    public function isAutomatic(): bool
    {
        return $this->is_automatic;
    }

    public function isManual(): bool
    {
        return ! $this->is_automatic;
    }

    /**
     * Add a customer to this segment.
     */
    public function addCustomer(Customer $customer): void
    {
        $this->customers()->syncWithoutDetaching([$customer->id]);
    }

    /**
     * Remove a customer from this segment.
     */
    public function removeCustomer(Customer $customer): void
    {
        $this->customers()->detach($customer->id);
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeAutomatic($query)
    {
        return $query->where('is_automatic', true);
    }

    public function scopeManual($query)
    {
        return $query->where('is_automatic', false);
    }

    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'desc');
    }

    public function scopeOfType($query, SegmentType $type)
    {
        return $query->where('type', $type);
    }

    // =========================================================================
    // BOOT
    // =========================================================================

    protected static function booted(): void
    {
        static::deleting(function (Segment $segment): void {
            $segment->customers()->detach();
        });
    }

    /**
     * Apply segment conditions to a query.
     *
     * @param  array<int, array{field?: string|null, operator?: string, value?: mixed}>  $conditions
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
                'lifetime_value_min' => $query->where('lifetime_value', '>=', $value),
                'lifetime_value_max' => $query->where('lifetime_value', '<=', $value),
                'total_orders_min' => $query->where('total_orders', '>=', $value),
                'total_orders_max' => $query->where('total_orders', '<=', $value),
                'last_order_days' => $query->where('last_order_at', '>=', now()->subDays($value)),
                'no_order_days' => $query->where('last_order_at', '<=', now()->subDays($value)),
                'accepts_marketing' => $query->where('accepts_marketing', (bool) $value),
                'is_tax_exempt' => $query->where('is_tax_exempt', (bool) $value),
                default => $query->where($field, $operator, $value),
            };
        }
    }
}
