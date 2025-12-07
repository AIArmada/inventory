<?php

declare(strict_types=1);

namespace AIArmada\Cart\Infrastructure\Caching;

use AIArmada\Cart\Storage\StorageInterface;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Eloquent\Model;

/**
 * Cached cart repository with multi-tier caching support.
 *
 * Implements a read-through cache pattern:
 * 1. Check L1 cache (local/memory)
 * 2. Check L2 cache (Redis/shared)
 * 3. Fallback to database
 *
 * Writes go to both cache tiers and database.
 */
final class CachedCartRepository implements StorageInterface
{
    private const string CACHE_PREFIX = 'cart:';

    private const int DEFAULT_TTL = 3600; // 1 hour

    public function __construct(
        private readonly StorageInterface $storage,
        private readonly CacheRepository $cache,
        private readonly int $ttl = self::DEFAULT_TTL
    ) {}

    /**
     * {@inheritDoc}
     */
    public function withOwner(?Model $owner): static
    {
        return new self(
            $this->storage->withOwner($owner),
            $this->cache,
            $this->ttl
        );
    }

    public function getOwnerType(): ?string
    {
        return $this->storage->getOwnerType();
    }

    public function getOwnerId(): string|int|null
    {
        return $this->storage->getOwnerId();
    }

    public function has(string $identifier, string $instance): bool
    {
        $key = $this->cacheKey($identifier, $instance, 'exists');

        return $this->cache->remember($key, $this->ttl, function () use ($identifier, $instance): bool {
            return $this->storage->has($identifier, $instance);
        });
    }

    public function forget(string $identifier, string $instance): void
    {
        $this->storage->forget($identifier, $instance);
        $this->invalidateCache($identifier, $instance);
    }

    public function flush(): void
    {
        $this->storage->flush();
        // Note: Cannot flush all cart caches without tracking all keys
        // Consider using cache tags for this
    }

    /**
     * {@inheritDoc}
     */
    public function getInstances(string $identifier): array
    {
        $key = $this->cacheKey($identifier, '*', 'instances');

        return $this->cache->remember($key, $this->ttl, function () use ($identifier): array {
            return $this->storage->getInstances($identifier);
        });
    }

    public function forgetIdentifier(string $identifier): void
    {
        $instances = $this->getInstances($identifier);
        $this->storage->forgetIdentifier($identifier);

        foreach ($instances as $instance) {
            $this->invalidateCache($identifier, $instance);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getItems(string $identifier, string $instance): array
    {
        $key = $this->cacheKey($identifier, $instance, 'items');

        return $this->cache->remember($key, $this->ttl, function () use ($identifier, $instance): array {
            return $this->storage->getItems($identifier, $instance);
        });
    }

    /**
     * {@inheritDoc}
     */
    public function getConditions(string $identifier, string $instance): array
    {
        $key = $this->cacheKey($identifier, $instance, 'conditions');

        return $this->cache->remember($key, $this->ttl, function () use ($identifier, $instance): array {
            return $this->storage->getConditions($identifier, $instance);
        });
    }

    /**
     * {@inheritDoc}
     */
    public function putItems(string $identifier, string $instance, array $items): void
    {
        $this->storage->putItems($identifier, $instance, $items);
        $this->invalidateCache($identifier, $instance, ['items', 'exists']);
    }

    /**
     * {@inheritDoc}
     */
    public function putConditions(string $identifier, string $instance, array $conditions): void
    {
        $this->storage->putConditions($identifier, $instance, $conditions);
        $this->invalidateCache($identifier, $instance, ['conditions']);
    }

    /**
     * {@inheritDoc}
     */
    public function putBoth(string $identifier, string $instance, array $items, array $conditions): void
    {
        $this->storage->putBoth($identifier, $instance, $items, $conditions);
        $this->invalidateCache($identifier, $instance, ['items', 'conditions', 'exists']);
    }

    public function putMetadata(string $identifier, string $instance, string $key, mixed $value): void
    {
        $this->storage->putMetadata($identifier, $instance, $key, $value);
        $this->invalidateCache($identifier, $instance, ['metadata']);
    }

    /**
     * {@inheritDoc}
     */
    public function putMetadataBatch(string $identifier, string $instance, array $metadata): void
    {
        $this->storage->putMetadataBatch($identifier, $instance, $metadata);
        $this->invalidateCache($identifier, $instance, ['metadata']);
    }

    public function getMetadata(string $identifier, string $instance, string $key): mixed
    {
        $cacheKey = $this->cacheKey($identifier, $instance, "metadata:{$key}");

        return $this->cache->remember($cacheKey, $this->ttl, function () use ($identifier, $instance, $key): mixed {
            return $this->storage->getMetadata($identifier, $instance, $key);
        });
    }

    /**
     * {@inheritDoc}
     */
    public function getAllMetadata(string $identifier, string $instance): array
    {
        $key = $this->cacheKey($identifier, $instance, 'metadata');

        return $this->cache->remember($key, $this->ttl, function () use ($identifier, $instance): array {
            return $this->storage->getAllMetadata($identifier, $instance);
        });
    }

    public function clearMetadata(string $identifier, string $instance): void
    {
        $this->storage->clearMetadata($identifier, $instance);
        $this->invalidateCache($identifier, $instance, ['metadata']);
    }

    public function clearAll(string $identifier, string $instance): void
    {
        $this->storage->clearAll($identifier, $instance);
        $this->invalidateCache($identifier, $instance);
    }

    public function getVersion(string $identifier, string $instance): ?int
    {
        $key = $this->cacheKey($identifier, $instance, 'version');

        return $this->cache->remember($key, $this->ttl, function () use ($identifier, $instance): ?int {
            return $this->storage->getVersion($identifier, $instance);
        });
    }

    public function getId(string $identifier, string $instance): ?string
    {
        $key = $this->cacheKey($identifier, $instance, 'id');

        return $this->cache->remember($key, $this->ttl, function () use ($identifier, $instance): ?string {
            return $this->storage->getId($identifier, $instance);
        });
    }

    public function swapIdentifier(string $oldIdentifier, string $newIdentifier, string $instance): bool
    {
        $result = $this->storage->swapIdentifier($oldIdentifier, $newIdentifier, $instance);

        if ($result) {
            $this->invalidateCache($oldIdentifier, $instance);
            $this->invalidateCache($newIdentifier, $instance);
        }

        return $result;
    }

    public function getCreatedAt(string $identifier, string $instance): ?string
    {
        $key = $this->cacheKey($identifier, $instance, 'created_at');

        return $this->cache->remember($key, $this->ttl, function () use ($identifier, $instance): ?string {
            return $this->storage->getCreatedAt($identifier, $instance);
        });
    }

    public function getUpdatedAt(string $identifier, string $instance): ?string
    {
        // Don't cache updated_at as it changes frequently
        return $this->storage->getUpdatedAt($identifier, $instance);
    }

    public function getExpiresAt(string $identifier, string $instance): ?string
    {
        return $this->storage->getExpiresAt($identifier, $instance);
    }

    public function isExpired(string $identifier, string $instance): bool
    {
        return $this->storage->isExpired($identifier, $instance);
    }

    // =========================================================================
    // AI & Analytics Methods (Phase 0.2) - No caching for these
    // =========================================================================

    public function getLastActivityAt(string $identifier, string $instance): ?string
    {
        return $this->storage->getLastActivityAt($identifier, $instance);
    }

    public function touchLastActivity(string $identifier, string $instance): void
    {
        $this->storage->touchLastActivity($identifier, $instance);
    }

    public function getCheckoutStartedAt(string $identifier, string $instance): ?string
    {
        return $this->storage->getCheckoutStartedAt($identifier, $instance);
    }

    public function markCheckoutStarted(string $identifier, string $instance): void
    {
        $this->storage->markCheckoutStarted($identifier, $instance);
    }

    public function getCheckoutAbandonedAt(string $identifier, string $instance): ?string
    {
        return $this->storage->getCheckoutAbandonedAt($identifier, $instance);
    }

    public function markCheckoutAbandoned(string $identifier, string $instance): void
    {
        $this->storage->markCheckoutAbandoned($identifier, $instance);
    }

    public function getRecoveryAttempts(string $identifier, string $instance): int
    {
        return $this->storage->getRecoveryAttempts($identifier, $instance);
    }

    public function incrementRecoveryAttempts(string $identifier, string $instance): void
    {
        $this->storage->incrementRecoveryAttempts($identifier, $instance);
    }

    public function getRecoveredAt(string $identifier, string $instance): ?string
    {
        return $this->storage->getRecoveredAt($identifier, $instance);
    }

    public function markRecovered(string $identifier, string $instance): void
    {
        $this->storage->markRecovered($identifier, $instance);
    }

    public function clearAbandonmentTracking(string $identifier, string $instance): void
    {
        $this->storage->clearAbandonmentTracking($identifier, $instance);
    }

    // =========================================================================
    // Event Sourcing Methods (Phase 0.3) - No caching for these
    // =========================================================================

    public function getEventStreamPosition(string $identifier, string $instance): int
    {
        return $this->storage->getEventStreamPosition($identifier, $instance);
    }

    public function setEventStreamPosition(string $identifier, string $instance, int $position): void
    {
        $this->storage->setEventStreamPosition($identifier, $instance, $position);
    }

    public function getAggregateVersion(string $identifier, string $instance): string
    {
        return $this->storage->getAggregateVersion($identifier, $instance);
    }

    public function setAggregateVersion(string $identifier, string $instance, string $version): void
    {
        $this->storage->setAggregateVersion($identifier, $instance, $version);
    }

    public function getSnapshotAt(string $identifier, string $instance): ?string
    {
        return $this->storage->getSnapshotAt($identifier, $instance);
    }

    public function markSnapshotTaken(string $identifier, string $instance): void
    {
        $this->storage->markSnapshotTaken($identifier, $instance);
    }

    /**
     * Warm the cache for a specific cart.
     */
    public function warmCache(string $identifier, string $instance): void
    {
        // Pre-fetch commonly accessed data
        $this->getItems($identifier, $instance);
        $this->getConditions($identifier, $instance);
        $this->getAllMetadata($identifier, $instance);
        $this->getVersion($identifier, $instance);
        $this->getId($identifier, $instance);
    }

    // =========================================================================
    // Cache Management
    // =========================================================================

    /**
     * Generate cache key for a cart property.
     */
    private function cacheKey(string $identifier, string $instance, string $property): string
    {
        $ownerPart = '';
        if ($this->getOwnerType() !== null && $this->getOwnerId() !== null) {
            $ownerPart = ':'.$this->getOwnerType().':'.$this->getOwnerId();
        }

        return self::CACHE_PREFIX.$identifier.':'.$instance.$ownerPart.':'.$property;
    }

    /**
     * Invalidate cached data for a cart.
     *
     * @param  array<string>|null  $properties  Specific properties to invalidate, or null for all
     */
    private function invalidateCache(string $identifier, string $instance, ?array $properties = null): void
    {
        $allProperties = ['items', 'conditions', 'metadata', 'version', 'id', 'exists', 'created_at'];

        $propertiesToInvalidate = $properties ?? $allProperties;

        foreach ($propertiesToInvalidate as $property) {
            $this->cache->forget($this->cacheKey($identifier, $instance, $property));
        }

        // Also invalidate instances cache
        $this->cache->forget($this->cacheKey($identifier, '*', 'instances'));
    }
}
