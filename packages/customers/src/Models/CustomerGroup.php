<?php

declare(strict_types=1);

namespace AIArmada\Customers\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
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
 * @property string|null $description
 * @property int|null $spending_limit
 * @property bool $is_active
 * @property bool $requires_approval
 * @property array<string, mixed>|null $settings
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|null $owner
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Customer> $members
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Customer> $admins
 */
class CustomerGroup extends Model
{
    use HasFactory;
    use HasOwner;
    use HasUuids;

    protected $guarded = ['id'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'spending_limit' => 'integer',
        'is_active' => 'boolean',
        'requires_approval' => 'boolean',
        'settings' => 'array',
        'metadata' => 'array',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'requires_approval' => true,
    ];

    public function getTable(): string
    {
        return config('customers.tables.groups', 'customer_groups');
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Get the group members.
     *
     * @return BelongsToMany<Customer, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(
            Customer::class,
            config('customers.tables.group_members', 'customer_group_members'),
            'group_id',
            'customer_id'
        )->withPivot(['role', 'joined_at'])->withTimestamps();
    }

    /**
     * Get group admins.
     *
     * @return BelongsToMany<Customer, $this>
     */
    public function admins(): BelongsToMany
    {
        return $this->members()->wherePivot('role', 'admin');
    }

    // =========================================================================
    // MEMBER MANAGEMENT
    // =========================================================================

    /**
     * Add a member to this group.
     */
    public function addMember(Customer $customer, string $role = 'member'): void
    {
        $this->members()->syncWithoutDetaching([
            $customer->id => [
                'role' => $role,
                'joined_at' => now(),
            ],
        ]);
    }

    /**
     * Remove a member from this group.
     */
    public function removeMember(Customer $customer): void
    {
        $this->members()->detach($customer->id);
    }

    /**
     * Promote a member to admin.
     */
    public function promoteToAdmin(Customer $customer): void
    {
        $this->members()->updateExistingPivot($customer->id, ['role' => 'admin']);
    }

    /**
     * Demote an admin to member.
     */
    public function demoteToMember(Customer $customer): void
    {
        $this->members()->updateExistingPivot($customer->id, ['role' => 'member']);
    }

    /**
     * Check if customer is a member.
     */
    public function hasMember(Customer $customer): bool
    {
        return $this->members()->where('customer_id', $customer->id)->exists();
    }

    /**
     * Check if customer is an admin.
     */
    public function isAdmin(Customer $customer): bool
    {
        return $this->admins()->where('customer_id', $customer->id)->exists();
    }

    // =========================================================================
    // SPENDING LIMIT
    // =========================================================================

    /**
     * Get the remaining spending limit for this group.
     * (Would need to integrate with orders package)
     */
    public function getRemainingSpendingLimit(): ?int
    {
        if ($this->spending_limit === null) {
            return null; // Unlimited
        }

        // This would calculate based on current month spending
        // For now, return the full limit
        return $this->spending_limit;
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // =========================================================================
    // BOOT
    // =========================================================================

    protected static function booted(): void
    {
        static::deleting(function (CustomerGroup $group): void {
            $group->members()->detach();
        });
    }
}
