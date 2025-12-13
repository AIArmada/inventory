<?php

declare(strict_types=1);

namespace AIArmada\Customers\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $customer_id
 * @property int|null $created_by
 * @property string $content
 * @property bool $is_internal
 * @property bool $is_pinned
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Customer $customer
 * @property-read Model|null $createdBy
 */
class CustomerNote extends Model
{
    use HasFactory;
    use HasUuids;

    protected $guarded = ['id'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_internal' => 'boolean',
        'is_pinned' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_internal' => true,
        'is_pinned' => false,
    ];

    public function getTable(): string
    {
        return config('customers.tables.notes', 'customer_notes');
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Get the customer this note belongs to.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Get the user who created this note.
     *
     * @return BelongsTo<Model, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(config('customers.user_model', \App\Models\User::class), 'created_by');
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Check if this is an internal note.
     */
    public function isInternal(): bool
    {
        return $this->is_internal;
    }

    /**
     * Check if this note is visible to customer.
     */
    public function isVisibleToCustomer(): bool
    {
        return ! $this->is_internal;
    }

    /**
     * Pin this note.
     */
    public function pin(): void
    {
        $this->update(['is_pinned' => true]);
    }

    /**
     * Unpin this note.
     */
    public function unpin(): void
    {
        $this->update(['is_pinned' => false]);
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeInternal($query)
    {
        return $query->where('is_internal', true);
    }

    public function scopeVisibleToCustomer($query)
    {
        return $query->where('is_internal', false);
    }

    public function scopePinned($query)
    {
        return $query->where('is_pinned', true);
    }
}
