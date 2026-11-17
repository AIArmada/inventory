<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Services\Stock;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Inventory\Contracts\ScopedCapacityReservationServiceInterface;
use AIArmada\Inventory\Data\ScopedCapacityLine;
use AIArmada\Inventory\Data\ScopedCapacityOutcome;
use AIArmada\Inventory\Models\CapacityHold;
use AIArmada\Inventory\Models\CapacityPool;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Durable, concurrency-safe reservation of application-defined resources.
 *
 * Every writer acquires the pool row lock and every reference transition is
 * serialized via a distributed cache lock. No date, media, ticketing or other
 * domain interpretation exists in this service.
 */
final class ScopedCapacityReservationService implements ScopedCapacityReservationServiceInterface
{
    /** @param list<ScopedCapacityLine> $lines */
    public function reserve(string $reference, array $lines, int $ttlSeconds): ScopedCapacityOutcome
    {
        $this->assertReference($reference);
        $this->assertTtl($ttlSeconds);
        $requirements = $this->normalize($lines);

        return $this->withReferenceLock($reference, function () use ($reference, $requirements, $ttlSeconds): ScopedCapacityOutcome {
            return DB::transaction(function () use ($reference, $requirements, $ttlSeconds): ScopedCapacityOutcome {
                $existing = $this->referenceHolds($reference);

                if ($existing->isNotEmpty()) {
                    $this->lockPools($existing->pluck('pool_id')->all());
                    $existing = $this->referenceHolds($reference);
                    $this->expireHolds($existing->pluck('pool_id')->all());
                    $existing = $this->referenceHolds($reference);
                    $recorded = [];

                    foreach ($existing as $hold) {
                        $recorded[json_encode([$hold->pool->resource_key, $hold->pool->scope_key], JSON_THROW_ON_ERROR)] = (int) $hold->quantity;
                    }

                    $requested = [];
                    foreach ($requirements as $line) {
                        $requested[$line->key()] = $line->quantity;
                    }

                    ksort($recorded);
                    ksort($requested);

                    if ($recorded !== $requested || $existing->contains(fn (CapacityHold $hold): bool => $hold->state !== CapacityHold::HELD)) {
                        throw new RuntimeException('Capacity reference has already been used for another or terminal reservation.');
                    }

                    return $this->outcome($reference, $existing);
                }

                // Resolve pool identities first, then lock all pool rows in
                // UUID order. Commit/release/expiry use that same lock order;
                // sorting the caller's resource keys would allow deadlocks
                // when two workflows use different multi-pool combinations.
                $poolIds = [];

                foreach ($requirements as $line) {
                    $pool = $this->poolQuery($line->resourceKey, $line->scopeKey)->first();

                    if (! $pool instanceof CapacityPool) {
                        throw new RuntimeException('Requested capacity resource has not been provisioned.');
                    }

                    $poolIds[$line->key()] = (string) $pool->getKey();
                }

                $locked = $this->lockPools(array_values($poolIds));
                $pools = [];

                foreach ($poolIds as $key => $poolId) {
                    $pools[$key] = $locked[$poolId];
                }

                $this->expireHolds(array_values($poolIds));

                foreach ($requirements as $line) {
                    $pool = $pools[$line->key()];
                    $pool->refresh();
                    if ($pool->available() < $line->quantity) {
                        throw new RuntimeException('Insufficient scoped resource capacity.');
                    }
                }

                $owner = $this->owner();
                $expires = CarbonImmutable::now()->addSeconds($ttlSeconds);

                foreach ($requirements as $line) {
                    $pool = $pools[$line->key()];
                    $pool->reserved += $line->quantity;
                    $pool->save();

                    $hold = new CapacityHold;
                    $hold->forceFill([
                        'pool_id' => $pool->id,
                        'reference' => $reference,
                        'quantity' => $line->quantity,
                        'state' => CapacityHold::HELD,
                        'expires_at' => $expires,
                        ...$owner,
                    ])->save();
                }

                return $this->outcome($reference, $this->referenceHolds($reference));
            });
        });
    }

    public function commit(string $reference, string $orderId): ScopedCapacityOutcome
    {
        if (mb_trim($orderId) === '') {
            throw new InvalidArgumentException('A confirmed reservation requires an order ID.');
        }

        return $this->transition($reference, function (CapacityHold $hold, CapacityPool $pool) use ($orderId): void {
            if ($hold->state === CapacityHold::COMMITTED && $hold->order_id === $orderId) {
                return;
            }
            if ($hold->state !== CapacityHold::HELD) {
                throw new RuntimeException('Only active holds may be committed.');
            }

            $pool->reserved -= $hold->quantity;
            $pool->committed += $hold->quantity;
            $pool->save();
            $hold->state = CapacityHold::COMMITTED;
            $hold->order_id = $orderId;
            $hold->save();
        });
    }

    public function release(string $reference): ScopedCapacityOutcome
    {
        return $this->transition($reference, function (CapacityHold $hold, CapacityPool $pool): void {
            if ($hold->state === CapacityHold::RELEASED || $hold->state === CapacityHold::EXPIRED) {
                return;
            }
            if ($hold->state === CapacityHold::COMMITTED) {
                throw new RuntimeException('Committed capacity cannot be released as a temporary hold.');
            }

            $pool->reserved -= $hold->quantity;
            $pool->save();
            $hold->state = CapacityHold::RELEASED;
            $hold->save();
        });
    }

    public function extend(string $reference, int $ttlSeconds): ScopedCapacityOutcome
    {
        $this->assertTtl($ttlSeconds);

        return $this->transition($reference, function (CapacityHold $hold) use ($ttlSeconds): void {
            if ($hold->state !== CapacityHold::HELD) {
                throw new RuntimeException('Only active capacity holds may be extended.');
            }

            $hold->expires_at = CarbonImmutable::now()->addSeconds($ttlSeconds);
            $hold->save();
        });
    }

    public function find(string $reference): ScopedCapacityOutcome
    {
        return $this->withReferenceLock($reference, function () use ($reference): ScopedCapacityOutcome {
            return DB::transaction(function () use ($reference): ScopedCapacityOutcome {
                $holds = $this->referenceHolds($reference);
                $this->lockPools($holds->pluck('pool_id')->all());
                $this->expireHolds($holds->pluck('pool_id')->all());

                return $this->outcome($reference, $this->referenceHolds($reference));
            });
        });
    }

    public function available(string $resourceKey, string $scopeKey): ?int
    {
        return DB::transaction(function () use ($resourceKey, $scopeKey): ?int {
            $pool = $this->poolQuery($resourceKey, $scopeKey)->lockForUpdate()->first();
            if (! $pool instanceof CapacityPool) {
                return null;
            }

            $this->expireHolds([$pool->id]);
            $pool->refresh();

            return $pool->available();
        });
    }

    public function setCapacity(string $resourceKey, string $scopeKey, int $capacity): void
    {
        if (mb_trim($resourceKey) === '' || $capacity < 0) {
            throw new InvalidArgumentException('Capacity must have a resource key and a non-negative quantity.');
        }

        Cache::lock('capacity-pool:' . hash('sha256', json_encode([$this->owner(), $resourceKey, $scopeKey])), 15)
            ->block(5, function () use ($resourceKey, $scopeKey, $capacity): void {
                DB::transaction(function () use ($resourceKey, $scopeKey, $capacity): void {
                    $pool = $this->poolQuery($resourceKey, $scopeKey)->lockForUpdate()->first();

                    if (! $pool instanceof CapacityPool) {
                        $pool = new CapacityPool;
                        $pool->forceFill([
                            'resource_key' => $resourceKey,
                            'scope_key' => $scopeKey,
                            'capacity' => $capacity,
                            'reserved' => 0,
                            'committed' => 0,
                            ...$this->owner(),
                        ])->save();

                        return;
                    }

                    $this->expireHolds([$pool->id]);
                    $pool->refresh();

                    if ($capacity < $pool->reserved + $pool->committed) {
                        throw new RuntimeException('Capacity cannot be reduced below reserved and committed demand.');
                    }

                    $pool->capacity = $capacity;
                    $pool->save();
                });
            });
    }

    /** @param callable(CapacityHold, CapacityPool): void $mutate */
    private function transition(string $reference, callable $mutate): ScopedCapacityOutcome
    {
        $this->assertReference($reference);

        return $this->withReferenceLock($reference, function () use ($reference, $mutate): ScopedCapacityOutcome {
            return DB::transaction(function () use ($reference, $mutate): ScopedCapacityOutcome {
                $holds = $this->referenceHolds($reference);
                if ($holds->isEmpty()) {
                    throw new RuntimeException('Capacity reservation reference not found.');
                }

                $pools = $this->lockPools($holds->pluck('pool_id')->all());
                $this->expireHolds($holds->pluck('pool_id')->all());
                $holds = $this->referenceHolds($reference);
                foreach ($pools as $pool) {
                    $pool->refresh();
                }

                foreach ($holds as $hold) {
                    $mutate($hold, $pools[$hold->pool_id]);
                }

                return $this->outcome($reference, $this->referenceHolds($reference));
            });
        });
    }

    /** @return array<string, CapacityPool> */
    private function lockPools(array $ids): array
    {
        $pools = [];
        $ids = array_values(array_unique($ids));
        sort($ids);

        foreach ($ids as $id) {
            $pool = $this->ownerQuery(CapacityPool::query())->whereKey($id)->lockForUpdate()->firstOrFail();
            $pools[$id] = $pool;
        }

        return $pools;
    }

    /** @param list<string> $poolIds */
    private function expireHolds(array $poolIds): void
    {
        if ($poolIds === []) {
            return;
        }

        $expired = $this->ownerQuery(CapacityHold::query())
            ->whereIn('pool_id', $poolIds)
            ->where('state', CapacityHold::HELD)
            ->where('expires_at', '<=', CarbonImmutable::now())
            ->lockForUpdate()
            ->get();

        foreach ($expired as $hold) {
            $pool = $this->ownerQuery(CapacityPool::query())->whereKey($hold->pool_id)->firstOrFail();
            $pool->reserved -= $hold->quantity;
            $pool->save();
            $hold->state = CapacityHold::EXPIRED;
            $hold->save();
        }
    }

    /** @param Collection<int, CapacityHold> $holds */
    private function outcome(string $reference, Collection $holds): ScopedCapacityOutcome
    {
        if ($holds->isEmpty()) {
            return new ScopedCapacityOutcome($reference, 'not_found', []);
        }

        $lines = [];
        foreach ($holds as $hold) {
            $lines[json_encode([$hold->pool->resource_key, $hold->pool->scope_key], JSON_THROW_ON_ERROR)] = (int) $hold->quantity;
        }

        $first = $holds->first();

        return new ScopedCapacityOutcome(
            reference: $reference,
            state: $first->state,
            lines: $lines,
            expiresAt: $first->expires_at?->toIso8601String(),
            orderId: $first->order_id,
        );
    }

    /** @return Collection<int, CapacityHold> */
    private function referenceHolds(string $reference): Collection
    {
        return $this->ownerQuery(CapacityHold::query())
            ->where('reference', $reference)
            ->with('pool')
            ->orderBy('pool_id')
            ->get();
    }

    /** @return Builder<CapacityPool> */
    private function poolQuery(string $resourceKey, string $scopeKey): Builder
    {
        return $this->ownerQuery(CapacityPool::query())
            ->where('resource_key', $resourceKey)
            ->where('scope_key', $scopeKey);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function ownerQuery(Builder $query): Builder
    {
        $owner = $this->owner();

        return $owner['owner_type'] === null
            ? $query->whereNull('owner_type')->whereNull('owner_id')
            : $query->where('owner_type', $owner['owner_type'])->where('owner_id', $owner['owner_id']);
    }

    /** @return array{owner_type: string|null, owner_id: string|null} */
    private function owner(): array
    {
        $model = (bool) config('inventory.owner.enabled', false) ? OwnerContext::resolve() : null;

        return [
            'owner_type' => $model?->getMorphClass(),
            'owner_id' => $model === null ? null : (string) $model->getKey(),
        ];
    }

    /** @param list<ScopedCapacityLine> $lines
     * @return list<ScopedCapacityLine>
     */
    private function normalize(array $lines): array
    {
        if ($lines === []) {
            throw new InvalidArgumentException('A capacity reservation requires at least one line.');
        }

        $combined = [];
        foreach ($lines as $line) {
            if (! $line instanceof ScopedCapacityLine) {
                throw new InvalidArgumentException('Capacity lines must be ScopedCapacityLine instances.');
            }

            $key = $line->key();
            $prior = $combined[$key]->quantity ?? 0;
            if ($prior > PHP_INT_MAX - $line->quantity) {
                throw new InvalidArgumentException('Capacity demand exceeds the supported integer range.');
            }
            $combined[$key] = new ScopedCapacityLine($line->resourceKey, $line->scopeKey, $prior + $line->quantity);
        }

        ksort($combined);

        return array_values($combined);
    }

    private function assertReference(string $reference): void
    {
        if (mb_trim($reference) === '') {
            throw new InvalidArgumentException('Capacity reservation reference cannot be empty.');
        }
    }

    private function assertTtl(int $seconds): void
    {
        if ($seconds < 1) {
            throw new InvalidArgumentException('Capacity reservation TTL must be positive.');
        }
    }

    private function withReferenceLock(string $reference, callable $callback): mixed
    {
        return Cache::lock('capacity-ref:' . hash('sha256', json_encode([$this->owner(), $reference])), 15)
            ->block(5, $callback);
    }
}
