<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Services\Stock;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Inventory\Contracts\CheckoutReservationServiceInterface;
use AIArmada\Inventory\Contracts\InventoryableInterface;
use AIArmada\Inventory\Data\ReservationLine;
use AIArmada\Inventory\Data\ReservationOutcome;
use AIArmada\Inventory\Exceptions\InvalidReservationTransition;
use AIArmada\Inventory\Exceptions\ReservationReferenceConflict;
use AIArmada\Inventory\Models\InventoryAllocation;
use AIArmada\Inventory\Models\InventoryReservation;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\Variant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
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

        // Serializes concurrent reserves for the same reference. NULL-owner
        // rows defeat the composite unique key on MySQL, so the database
        // alone cannot guarantee idempotent creation on every driver — the
        // lock is the only serializer there, so losers must wait for it
        // rather than proceed into the transaction.
        return Cache::lock($this->reservationLockKey($reference), 10)->block(5, function () use (
            $reference,
            $lines,
            $lineSnapshot,
            $ttlSeconds,
        ): ReservationOutcome {
            return $this->attemptReserve($reference, $lines, $lineSnapshot, $ttlSeconds);
        });
    }

    /**
     * @param  list<ReservationLine>  $lines
     * @param  array<string, array{requested: int, reserved: int}>  $lineSnapshot
     */
    private function attemptReserve(string $reference, array $lines, array $lineSnapshot, int $ttlSeconds): ReservationOutcome
    {
        // Transient lock contention (SQLite busy, deadlocks) backs off and
        // retries with a fresh transaction instead of failing the checkout.
        // WAL snapshot-upgrade refusals fail immediately ([HY000, 5]) without
        // engaging SQLite's busy_timeout, so this PHP backstop is the only
        // thing covering a descheduled writer: budget ~4s across 6 attempts.
        $backoffMicros = [50000, 150000, 400000, 1000000, 2500000];

        for (; ;) {
            try {
                return $this->reserveInTransaction($reference, $lines, $lineSnapshot, $ttlSeconds);
            } catch (QueryException $exception) {
                if ($backoffMicros === [] || ! $this->isTransientLockError($exception)) {
                    throw $exception;
                }

                usleep(array_shift($backoffMicros));
            }
        }
    }

    private function isTransientLockError(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);

        return ($sqlState === 'HY000' && $driverCode === 5)
            || $sqlState === '40001'
            || $sqlState === '40P01'
            || ($sqlState === 'HY000' && in_array($driverCode, [1205, 1213], true));
    }

    /**
     * @param  list<ReservationLine>  $lines
     * @param  array<string, array{requested: int, reserved: int}>  $lineSnapshot
     */
    private function reserveInTransaction(string $reference, array $lines, array $lineSnapshot, int $ttlSeconds): ReservationOutcome
    {
        return DB::transaction(function () use ($reference, $lines, $lineSnapshot, $ttlSeconds): ReservationOutcome {
            $owner = $this->resolveOwner();
            $expiresAt = CarbonImmutable::now()->addSeconds($ttlSeconds);

            $group = $this->findGroupForOwner($reference, $owner);

            if ($group instanceof InventoryReservation) {
                return $this->idempotentOutcome($reference, $group, $lineSnapshot);
            }

            $group = new InventoryReservation;
            $group->forceFill([
                'reference' => $reference,
                'owner_type' => $owner['type'],
                'owner_id' => $owner['id'],
                'status' => InventoryReservation::STATE_RESERVED,
                'line_snapshot' => $lineSnapshot,
                'ttl_seconds' => $ttlSeconds,
                'expires_at' => $expiresAt,
            ]);

            try {
                $group->save();
            } catch (QueryException $exception) {
                $group = $this->recoverRacedGroup($reference, $owner, $exception);

                if (! $group instanceof InventoryReservation) {
                    throw $exception;
                }

                return $this->idempotentOutcome($reference, $group, $lineSnapshot);
            }

            foreach ($lines as $line) {
                $model = $this->resolveInventoryModel($line);

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
        }, 3);
    }

    /**
     * @param  array<string, array{requested: int, reserved: int}>  $lineSnapshot
     */
    private function idempotentOutcome(string $reference, InventoryReservation $group, array $lineSnapshot): ReservationOutcome
    {
        if ($group->status === InventoryReservation::STATE_RESERVED
            && $group->line_snapshot === $lineSnapshot) {
            return $this->outcome($group);
        }

        throw new ReservationReferenceConflict(
            $reference,
            'A reservation for this reference already exists in state: ' . $group->status,
        );
    }

    /**
     * @param  array{type: string|null, id: string|int|null}  $owner
     */
    private function findGroupForOwner(string $reference, array $owner): ?InventoryReservation
    {
        /** @var InventoryReservation|null $group */
        $group = InventoryReservation::query()
            ->where('reference', $reference)
            ->where('owner_type', $owner['type'])
            ->where('owner_id', $owner['id'])
            ->first();

        return $group;
    }

    /**
     * Re-read the group after a lost creation race. Returns null when the
     * failure was not a unique violation or the winner is not visible.
     *
     * @param  array{type: string|null, id: string|int|null}  $owner
     */
    private function recoverRacedGroup(string $reference, array $owner, QueryException $exception): ?InventoryReservation
    {
        if (! in_array((string) ($exception->errorInfo[0] ?? $exception->getCode()), ['23000', '23505'], true)) {
            return null;
        }

        return $this->findGroupForOwner($reference, $owner);
    }

    private function reservationLockKey(string $reference): string
    {
        $owner = $this->resolveOwner();

        return 'inventory-reservation:' . hash('sha256', implode('|', [
            $reference,
            (string) $owner['type'],
            (string) $owner['id'],
        ]));
    }

    public function release(string $reference): ReservationOutcome
    {
        return DB::transaction(function () use ($reference): ReservationOutcome {
            $group = $this->lockGroup($reference);

            if ($group === null) {
                return new ReservationOutcome(reference: $reference, state: 'not_found');
            }

            $this->expireIfNeeded($group);

            if ($group->status === InventoryReservation::STATE_RELEASED || $group->status === InventoryReservation::STATE_EXPIRED) {
                return $this->outcome($group);
            }

            if ($group->status !== InventoryReservation::STATE_RESERVED) {
                throw new InvalidReservationTransition($reference, $group->status, InventoryReservation::STATE_RELEASED);
            }

            $this->allocationService->releaseAllForReservationGroup($group->id);
            $group->update(['status' => InventoryReservation::STATE_RELEASED, 'expires_at' => CarbonImmutable::now()]);

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

            if ($group->status === InventoryReservation::STATE_COMMITTED) {
                if ($group->order_id !== $orderId) {
                    throw new ReservationReferenceConflict($reference, 'The reservation has already been committed to another order.');
                }

                return $this->outcome($group);
            }

            if ($group->status !== InventoryReservation::STATE_RESERVED) {
                throw new InvalidReservationTransition($reference, $group->status, InventoryReservation::STATE_COMMITTED);
            }

            $this->allocationService->commitReservationGroup($group->id, $reference, $orderId);
            $group->update(['status' => InventoryReservation::STATE_COMMITTED, 'order_id' => $orderId]);

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

            if ($group->status !== InventoryReservation::STATE_RESERVED) {
                return $this->outcome($group);
            }

            $newExpiry = CarbonImmutable::now()->addSeconds($ttlSeconds);
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

    /**
     * Release expired allocations and remove reservation groups past retention.
     *
     * @return int Number of reservation groups removed.
     */
    public function cleanupExpiredReservations(): int
    {
        $now = CarbonImmutable::now();
        $cutoff = $now->subMinutes(max(0, (int) config('inventory.cleanup.keep_expired_for_minutes', 0)));

        return DB::transaction(function () use ($now, $cutoff): int {
            $groups = InventoryOwnerScope::applyToLocationQuery(
                InventoryReservation::query()
                    ->where(function ($query) use ($now, $cutoff): void {
                        $query
                            ->where(function ($reserved) use ($now): void {
                                $reserved
                                    ->where('status', InventoryReservation::STATE_RESERVED)
                                    ->whereNotNull('expires_at')
                                    ->where('expires_at', '<=', $now);
                            })
                            ->orWhere(function ($terminal) use ($cutoff): void {
                                $terminal
                                    ->whereIn('status', [
                                        InventoryReservation::STATE_COMMITTED,
                                        InventoryReservation::STATE_RELEASED,
                                        InventoryReservation::STATE_EXPIRED,
                                    ])
                                    ->where('updated_at', '<=', $cutoff);
                            });
                    })
                    ->lockForUpdate()
            )->get();

            $deleted = 0;

            foreach ($groups as $group) {
                if ($group->status === InventoryReservation::STATE_RESERVED) {
                    $this->expireIfNeeded($group);
                }

                $this->allocationService->releaseAllForReservationGroup($group->id);
                $group->delete();
                $deleted++;
            }

            return $deleted;
        });
    }

    private function outcome(InventoryReservation $group): ReservationOutcome
    {
        return new ReservationOutcome(
            reference: $group->reference,
            state: $group->status,
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

            $key = $line->inventoryableType !== null && $line->inventoryableId !== null
                ? $line->inventoryableType . ':' . $line->inventoryableId . ':' . ($line->variantId ?? '')
                : $line->productId . ':' . ($line->variantId ?? '');
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
        if (! $group->isExpired() || $group->status === InventoryReservation::STATE_EXPIRED) {
            return;
        }

        $this->allocationService->releaseAllForReservationGroup($group->id);
        $group->update(['status' => InventoryReservation::STATE_EXPIRED]);
        $group->refresh();
    }

    private function findGroup(string $reference): ?InventoryReservation
    {
        $query = InventoryOwnerScope::applyToLocationQuery(
            InventoryReservation::query()->where('reference', $reference)
        );

        return $query->first();
    }

    private function lockGroup(string $reference): ?InventoryReservation
    {
        $query = InventoryOwnerScope::applyToLocationQuery(
            InventoryReservation::query()
                ->where('reference', $reference)
                ->lockForUpdate()
        );

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

    private function resolveInventoryModel(ReservationLine $line): ?Model
    {
        if ($line->inventoryableType !== null && $line->inventoryableId !== null) {
            $inventoryableClass = Relation::getMorphedModel($line->inventoryableType) ?? $line->inventoryableType;

            // The line type is caller-influenced: only resolve classes that
            // are explicitly inventoryable, never arbitrary Eloquent models.
            if (is_string($inventoryableClass) && $this->isAllowedInventoryableClass($inventoryableClass)) {
                /** @var class-string<Model> $inventoryableClass */
                $inventoryable = $inventoryableClass::query()->find($line->inventoryableId);

                if ($inventoryable instanceof Model) {
                    return $inventoryable;
                }
            }
        }

        if ($line->variantId !== null) {
            $variantClass = config('inventory.models.variant') ?? Variant::class;

            if (class_exists($variantClass)) {
                $variant = $variantClass::query()->find($line->variantId);

                if ($variant !== null) {
                    return $variant;
                }
            }
        }

        $productClass = config('inventory.models.product') ?? Product::class;

        if (! class_exists($productClass)) {
            return null;
        }

        return $productClass::query()->find($line->productId);
    }

    private function isAllowedInventoryableClass(string $class): bool
    {
        if (! is_a($class, Model::class, true)) {
            return false;
        }

        $variantClass = config('inventory.models.variant');
        $productClass = config('inventory.models.product');

        foreach ([$variantClass, $productClass, Variant::class, Product::class] as $configured) {
            if (is_string($configured) && ($class === $configured || is_a($class, $configured, true))) {
                return true;
            }
        }

        if (is_a($class, InventoryableInterface::class, true)) {
            return true;
        }

        return interface_exists('AIArmada\\Products\\Contracts\\Inventoryable')
            && is_a($class, 'AIArmada\\Products\\Contracts\\Inventoryable', true);
    }
}
