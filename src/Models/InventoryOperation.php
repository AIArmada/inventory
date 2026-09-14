<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Models;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
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

    // NOTE: owner_type/owner_id are deliberately not fillable. The owning
    // tuple is assigned by services via forceFill and verified by the
    // saving guard below, so caller input can never spoof ownership.
    protected $fillable = [
        'order_id',
        'kind',
        'status',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (InventoryOperation $operation): void {
            if (! config('inventory.owner.enabled', false)) {
                return;
            }

            $owner = OwnerContext::resolve();
            $autoAssign = (bool) config('inventory.owner.auto_assign_on_create', true);

            if ($owner === null) {
                if ($operation->owner_type !== null || $operation->owner_id !== null) {
                    throw new AuthorizationException('Cannot save an owned operation without an owner context.');
                }

                return;
            }

            if ($operation->owner_type === null && $operation->owner_id === null) {
                if ($autoAssign && ! $operation->exists) {
                    $operation->owner_type = $owner->getMorphClass();
                    $operation->owner_id = $owner->getKey();
                }

                return;
            }

            if ($operation->owner_type !== $owner->getMorphClass()
                || (string) $operation->owner_id !== (string) $owner->getKey()) {
                throw new AuthorizationException('Operation owner differs from the current owner context.');
            }
        });
    }

    public function getTable(): string
    {
        return config('inventory.database.tables.operations', 'inventory_operations');
    }
}
