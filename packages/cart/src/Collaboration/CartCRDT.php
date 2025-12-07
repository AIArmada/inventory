<?php

declare(strict_types=1);

namespace AIArmada\Cart\Collaboration;

use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * CRDT (Conflict-free Replicated Data Type) implementation for cart operations.
 *
 * Enables conflict-free merging of concurrent cart modifications
 * from multiple collaborators.
 */
final class CartCRDT
{
    private const CACHE_PREFIX = 'cart:crdt:';

    private const CACHE_TTL = 86400;

    /**
     * Create an add operation.
     *
     * @param  array<string, mixed>  $data
     */
    public function createAddOperation(
        string $cartId,
        string $userId,
        string $itemId,
        array $data
    ): CRDTOperation {
        $clock = $this->incrementClock($cartId, $userId);

        return new CRDTOperation(
            id: Str::uuid()->toString(),
            type: 'add',
            cartId: $cartId,
            userId: $userId,
            itemId: $itemId,
            data: $data,
            vectorClock: $clock,
            timestamp: now()
        );
    }

    /**
     * Create an update operation.
     *
     * @param  array<string, mixed>  $data
     */
    public function createUpdateOperation(
        string $cartId,
        string $userId,
        string $itemId,
        array $data
    ): CRDTOperation {
        $clock = $this->incrementClock($cartId, $userId);

        return new CRDTOperation(
            id: Str::uuid()->toString(),
            type: 'update',
            cartId: $cartId,
            userId: $userId,
            itemId: $itemId,
            data: $data,
            vectorClock: $clock,
            timestamp: now()
        );
    }

    /**
     * Create a remove operation.
     */
    public function createRemoveOperation(
        string $cartId,
        string $userId,
        string $itemId
    ): CRDTOperation {
        $clock = $this->incrementClock($cartId, $userId);

        return new CRDTOperation(
            id: Str::uuid()->toString(),
            type: 'remove',
            cartId: $cartId,
            userId: $userId,
            itemId: $itemId,
            data: [],
            vectorClock: $clock,
            timestamp: now()
        );
    }

    /**
     * Apply an operation with conflict resolution.
     */
    public function apply(CRDTOperation $operation): bool
    {
        $key = self::CACHE_PREFIX.$operation->cartId.':operations';
        $operations = Cache::get($key, []);

        if ($this->hasConflict($operation, $operations)) {
            $resolved = $this->resolveConflict($operation, $operations);

            if (! $resolved) {
                return false;
            }
        }

        $operations[] = $operation->toArray();
        $operations = array_slice($operations, -1000);

        Cache::put($key, $operations, self::CACHE_TTL);

        $this->updateVectorClock($operation->cartId, $operation->vectorClock);

        return true;
    }

    /**
     * Merge remote operations with local state.
     *
     * @param  array<CRDTOperation>  $remoteOperations
     * @return array<CRDTOperation>
     */
    public function merge(string $cartId, array $remoteOperations): array
    {
        $key = self::CACHE_PREFIX.$cartId.':operations';
        $localOperations = Cache::get($key, []);

        $merged = [];
        $seen = [];

        $allOperations = array_merge(
            array_map(fn ($op) => CRDTOperation::fromArray($op), $localOperations),
            $remoteOperations
        );

        usort($allOperations, fn ($a, $b) => $this->compareOperations($a, $b));

        foreach ($allOperations as $operation) {
            if (isset($seen[$operation->id])) {
                continue;
            }

            $seen[$operation->id] = true;

            if (! $this->isSuperseded($operation, $allOperations)) {
                $merged[] = $operation;
            }
        }

        Cache::put($key, array_map(fn ($op) => $op->toArray(), $merged), self::CACHE_TTL);

        return $merged;
    }

    /**
     * Get operations for a cart.
     *
     * @return array<CRDTOperation>
     */
    public function getOperations(string $cartId, ?int $sinceVersion = null): array
    {
        $key = self::CACHE_PREFIX.$cartId.':operations';
        $operations = Cache::get($key, []);

        $result = array_map(fn ($op) => CRDTOperation::fromArray($op), $operations);

        if ($sinceVersion !== null) {
            $result = array_filter(
                $result,
                fn (CRDTOperation $op) => $this->getVersionFromClock($op->vectorClock) > $sinceVersion
            );
        }

        return array_values($result);
    }

    /**
     * Get current vector clock for a cart.
     *
     * @return array<string, int>
     */
    public function getVectorClock(string $cartId): array
    {
        $key = self::CACHE_PREFIX.$cartId.':clock';

        return Cache::get($key, []);
    }

    /**
     * Replay operations to rebuild cart state.
     *
     * @return array<string, array<string, mixed>>
     */
    public function replay(string $cartId): array
    {
        $operations = $this->getOperations($cartId);
        $state = [];

        foreach ($operations as $operation) {
            switch ($operation->type) {
                case 'add':
                    $state[$operation->itemId] = $operation->data;
                    break;
                case 'update':
                    $state[$operation->itemId] = array_merge(
                        $state[$operation->itemId] ?? [],
                        $operation->data
                    );
                    break;
                case 'remove':
                    unset($state[$operation->itemId]);
                    break;
            }
        }

        return $state;
    }

    /**
     * Check if there's a conflict with existing operations.
     *
     * @param  array<array<string, mixed>>  $existingOperations
     */
    private function hasConflict(CRDTOperation $operation, array $existingOperations): bool
    {
        foreach ($existingOperations as $existing) {
            if ($existing['item_id'] === $operation->itemId) {
                $existingVersion = $this->getVersionFromClock($existing['vector_clock']);
                $newVersion = $this->getVersionFromClock($operation->vectorClock);

                if ($existingVersion >= $newVersion && $existing['timestamp'] >= $operation->timestamp->format('Y-m-d H:i:s')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Resolve a conflict using Last-Writer-Wins strategy.
     *
     * @param  array<array<string, mixed>>  $existingOperations
     */
    private function resolveConflict(CRDTOperation $operation, array $existingOperations): bool
    {
        $latestTimestamp = $operation->timestamp;

        foreach ($existingOperations as $existing) {
            if ($existing['item_id'] === $operation->itemId) {
                $existingTime = new DateTimeImmutable($existing['timestamp']);

                if ($existingTime > $latestTimestamp) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Compare operations for sorting.
     */
    private function compareOperations(CRDTOperation $a, CRDTOperation $b): int
    {
        $versionA = $this->getVersionFromClock($a->vectorClock);
        $versionB = $this->getVersionFromClock($b->vectorClock);

        if ($versionA !== $versionB) {
            return $versionA <=> $versionB;
        }

        return $a->timestamp <=> $b->timestamp;
    }

    /**
     * Check if operation is superseded by another.
     *
     * @param  array<CRDTOperation>  $allOperations
     */
    private function isSuperseded(CRDTOperation $operation, array $allOperations): bool
    {
        if ($operation->type !== 'add' && $operation->type !== 'update') {
            return false;
        }

        foreach ($allOperations as $other) {
            if ($other->id === $operation->id) {
                continue;
            }

            if ($other->itemId === $operation->itemId && $other->type === 'remove') {
                if ($this->compareOperations($operation, $other) < 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Increment the vector clock for a user.
     *
     * @return array<string, int>
     */
    private function incrementClock(string $cartId, string $userId): array
    {
        $key = self::CACHE_PREFIX.$cartId.':clock';
        $clock = Cache::get($key, []);

        $clock[$userId] = ($clock[$userId] ?? 0) + 1;

        Cache::put($key, $clock, self::CACHE_TTL);

        return $clock;
    }

    /**
     * Update the vector clock with new values.
     *
     * @param  array<string, int>  $newClock
     */
    private function updateVectorClock(string $cartId, array $newClock): void
    {
        $key = self::CACHE_PREFIX.$cartId.':clock';
        $clock = Cache::get($key, []);

        foreach ($newClock as $userId => $version) {
            $clock[$userId] = max($clock[$userId] ?? 0, $version);
        }

        Cache::put($key, $clock, self::CACHE_TTL);
    }

    /**
     * Get version number from vector clock.
     *
     * @param  array<string, int>  $clock
     */
    private function getVersionFromClock(array $clock): int
    {
        return array_sum($clock);
    }
}
