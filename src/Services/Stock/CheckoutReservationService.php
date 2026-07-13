<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Services\Stock;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Inventory\Contracts\CheckoutReservationServiceInterface;
use AIArmada\Inventory\Data\ReservationLine;
use AIArmada\Inventory\Data\ReservationOutcome;
use AIArmada\Inventory\Exceptions\InvalidReservationTransition;
use AIArmada\Inventory\Exceptions\ReservationReferenceConflict;
use AIArmada\Inventory\Models\InventoryAllocation;
use AIArmada\Inventory\Models\InventoryReservation;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\Variant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class CheckoutReservationService implements CheckoutReservationServiceInterface
{
    public function __construct(
        private readonly InventoryAllocationService $allocationService,
    ) {}

    /** @param list<ReservationLine> $lines */
    public function reserve(string $reference, array $lines, int $ttlSeconds): ReservationOutcome
    {
        $lineSnapshot = $this->lineSnapshot($lines);

        if ($lineSnapshot === []) {
            throw new ReservationReferenceConflict($reference, 'A reservation requires at least one valid line.');
        }

        return DB::transaction(function () use ($reference, $lines, $ttlSeconds): ReservationOutcome {
            $existing = $this->findGroup($reference);

            if ($existing !== null) {
                if ($existing->state === InventoryReservation::STATE_RESERVED
                    && $existing->line_snapshot === $this->lineSnapshot($lines)) {
                    return $this->outcome($existing);
                }

                throw new ReservationReferenceConflict(
                    $reference,
                    'A reservation for this reference already exists in state: ' . $existing->state,
                );
            }

            $owner = $this->resolveOwner();
            $expiresAt = now()->addSeconds($ttlSeconds);

            $group = InventoryReservation::create([
                'reference' => $reference,
                'state' => InventoryReservation::STATE_RESERVED,
                'line_snapshot' => $this->lineSnapshot($lines),
                'ttl_seconds' => $ttlSeconds,
                'expires_at' => $expiresAt,
                'owner_type' => $owner['type'],
                'owner_id' => $owner['id'],
            ]);

            foreach ($lines as $line) {
                $model = $this->resolveInventoryModel($line->productId, $line->variantId);

                if ($model === null) {
                    throw new ReservationReferenceConflict($reference, 'Inventory model could not be resolved for a requested line.');
                }

                $allocations = $this->allocationService->allocate(
                    model: $model,
                    quantity: $line->quantity,
                    cartId: $reference,
                    ttlMinutes: max(1, (int) ceil($ttlSeconds / 60)),
                );

                $allocations->each(fn (InventoryAllocation $a) => $a->update(['reservation_group_id' => $group->id]));
            }

            $group->refresh();

            return $this->outcome($group);
        });
    }

    public function release(string $reference): ReservationOutcome
    {
        return DB::transaction(function () use ($reference): ReservationOutcome {
            $group = $this->lockGroup($reference);

            if ($group === null) {
                return new ReservationOutcome(reference: $reference, state: 'not_found');
            }

            $this->expireIfNeeded($group);

            if ($group->state === InventoryReservation::STATE_RELEASED || $group->state === InventoryReservation::STATE_EXPIRED) {
                return $this->outcome($group);
            }

            if ($group->state !== InventoryReservation::STATE_RESERVED) {
                throw new InvalidReservationTransition($reference, $group->state, InventoryReservation::STATE_RELEASED);
            }

            $this->allocationService->releaseAllForReservationGroup($group->id);
            $group->update(['state' => InventoryReservation::STATE_RELEASED, 'expires_at' => now()]);

            return $this->outcome($group);
        });
    }

    public function commit(string $reference, string $orderId): ReservationOutcome
    {
        return DB::transaction(function () use ($reference, $orderId): ReservationOutcome {
            $group = $this->lockGroup($reference);

            if ($group === null) {
                return new ReservationOutcome(reference: $reference, state: 'not_found');
            }

            $this->expireIfNeeded($group);

            if ($group->state === InventoryReservation::STATE_COMMITTED) {
                if ($group->order_id !== $orderId) {
                    throw new ReservationReferenceConflict($reference, 'The reservation has already been committed to another order.');
                }

                return $this->outcome($group);
            }

            if ($group->state !== InventoryReservation::STATE_RESERVED) {
                throw new InvalidReservationTransition($reference, $group->state, InventoryReservation::STATE_COMMITTED);
            }

            $this->allocationService->commitReservationGroup($group->id, $reference, $orderId);
            $group->update(['state' => InventoryReservation::STATE_COMMITTED, 'order_id' => $orderId]);

            return $this->outcome($group);
        });
    }

    public function extend(string $reference, int $ttlSeconds): ReservationOutcome
    {
        return DB::transaction(function () use ($reference, $ttlSeconds): ReservationOutcome {
            $group = $this->lockGroup($reference);

            if ($group === null) {
                return new ReservationOutcome(reference: $reference, state: 'not_found');
            }

            $this->expireIfNeeded($group);

            if ($group->state !== InventoryReservation::STATE_RESERVED) {
                return $this->outcome($group);
            }

            $newExpiry = now()->addSeconds($ttlSeconds);
            $group->update(['ttl_seconds' => $ttlSeconds, 'expires_at' => $newExpiry]);

            $this->allocationService->extendReservationGroupAllocations($group->id, max(1, (int) ceil($ttlSeconds / 60)));

            return $this->outcome($group);
        });
    }

    public function find(string $reference): ReservationOutcome
    {
        $group = $this->findGroup($reference);

        if ($group === null) {
            return new ReservationOutcome(reference: $reference, state: 'not_found');
        }

        $this->expireIfNeeded($group);

        return $this->outcome($group->refresh());
    }

    private function outcome(InventoryReservation $group): ReservationOutcome
    {
        return new ReservationOutcome(
            reference: $group->reference,
            state: $group->state,
            expiresAt: $group->expires_at?->toIso8601String(),
            orderId: $group->order_id,
            lines: $group->line_snapshot,
        );
    }

    /**
     * @param  list<ReservationLine>  $lines
     * @return array<string, array{requested: int, reserved: int}>
     */
    private function lineSnapshot(array $lines): array
    {
        $snapshot = [];

        foreach ($lines as $line) {
            if ($line->quantity <= 0) {
                continue;
            }

            $key = $line->productId . ':' . ($line->variantId ?? '');
            $current = $snapshot[$key] ?? ['requested' => 0, 'reserved' => 0];
            $current['requested'] += $line->quantity;
            $current['reserved'] += $line->quantity;
            $snapshot[$key] = $current;
        }

        ksort($snapshot);

        return $snapshot;
    }

    private function expireIfNeeded(InventoryReservation $group): void
    {
        if (! $group->isExpired() || $group->state === InventoryReservation::STATE_EXPIRED) {
            return;
        }

        $this->allocationService->releaseAllForReservationGroup($group->id);
        $group->update(['state' => InventoryReservation::STATE_EXPIRED]);
        $group->refresh();
    }

    private function findGroup(string $reference): ?InventoryReservation
    {
        $query = InventoryReservation::query()->where('reference', $reference);

        return $query->first();
    }

    private function lockGroup(string $reference): ?InventoryReservation
    {
        $query = InventoryReservation::query()
            ->where('reference', $reference)
            ->lockForUpdate();

        return $query->first();
    }

    /** @return array{type: string|null, id: string|int|null} */
    private function resolveOwner(): array
    {
        $owner = OwnerContext::resolve();

        if ($owner === null) {
            return ['type' => null, 'id' => null];
        }

        return [
            'type' => $owner->getMorphClass(),
            'id' => $owner->getKey(),
        ];
    }

    private function resolveInventoryModel(string $productId, ?string $variantId): ?Model
    {
        if ($variantId !== null) {
            $variantClass = config('inventory.models.variant') ?? Variant::class;

            if (class_exists($variantClass)) {
                $variant = $variantClass::query()->find($variantId);

                if ($variant !== null) {
                    return $variant;
                }
            }
        }

        $productClass = config('inventory.models.product') ?? Product::class;

        if (! class_exists($productClass)) {
            return null;
        }

        return $productClass::query()->find($productId);
    }
}
