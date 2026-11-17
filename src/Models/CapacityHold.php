<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $pool_id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $reference
 * @property int $quantity
 * @property string $state
 * @property string|null $order_id
 * @property CarbonImmutable $expires_at
 * @property-read CapacityPool $pool
 */
final class CapacityHold extends Model
{
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    public const string HELD = 'held';

    public const string COMMITTED = 'committed';

    public const string RELEASED = 'released';

    public const string EXPIRED = 'expired';

    protected static string $ownerScopeConfigKey = 'inventory.owner';

    protected $guarded = ['id', 'owner_type', 'owner_id'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'expires_at' => 'immutable_datetime',
        ];
    }

    public function getTable(): string
    {
        return config('inventory.database.tables.capacity_holds', 'inventory_capacity_holds');
    }

    /** @return BelongsTo<CapacityPool, $this> */
    public function pool(): BelongsTo
    {
        return $this->belongsTo(CapacityPool::class, 'pool_id');
    }
}
