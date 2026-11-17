<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $resource_key
 * @property string $scope_key
 * @property int $capacity
 * @property int $reserved
 * @property int $committed
 */
final class CapacityPool extends Model
{
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'inventory.owner';

    protected $guarded = ['id', 'owner_type', 'owner_id'];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'reserved' => 'integer',
            'committed' => 'integer',
        ];
    }

    public function getTable(): string
    {
        return config('inventory.database.tables.capacity_pools', 'inventory_capacity_pools');
    }

    /** @return HasMany<CapacityHold, $this> */
    public function holds(): HasMany
    {
        return $this->hasMany(CapacityHold::class, 'pool_id');
    }

    public function available(): int
    {
        return max(0, $this->capacity - $this->reserved - $this->committed);
    }
}
