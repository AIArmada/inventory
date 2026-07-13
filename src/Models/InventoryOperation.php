<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Durable record of a single logical inventory operation for an Order.
 *
 * Ensures one deduction and one release per order even under duplicate
 * event delivery, queue retries, or manual replays. The unique constraint
 * on (order_id, kind) serializes concurrent attempts at the database level.
 *
 * @property string $id
 * @property string $order_id
 * @property string $kind
 * @property string $status
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
final class InventoryOperation extends Model
{
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    public const string KIND_DEDUCTION = 'deduction';

    public const string KIND_RELEASE = 'release';

    public const string STATUS_PENDING = 'pending';

    public const string STATUS_COMPLETED = 'completed';

    public const string STATUS_FAILED = 'failed';

    protected static string $ownerScopeConfigKey = 'inventory.owner';

    protected $fillable = [
        'order_id',
        'kind',
        'status',
        'completed_at',
        'owner_id',
        'owner_type',
    ];

    protected $casts = [
        'completed_at' => 'immutable_datetime',
    ];

    public function getTable(): string
    {
        return config('inventory.database.tables.operations', 'inventory_operations');
    }
}
