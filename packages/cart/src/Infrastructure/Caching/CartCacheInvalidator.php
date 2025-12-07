<?php

declare(strict_types=1);

namespace AIArmada\Cart\Infrastructure\Caching;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Log;

/**
 * Handles cache invalidation for carts.
 *
 * Can be triggered by:
 * - Cart events (ItemAdded, ItemRemoved, etc.)
 * - Database updates
 * - Manual invalidation
 */
final class CartCacheInvalidator
{
    private const string CACHE_PREFIX = 'cart:';

    private const array CACHE_KEYS = [
        'items',
        'conditions',
        'metadata',
        'version',
        'id',
        'exists',
        'created_at',
    ];

    public function __construct(
        private readonly CacheRepository $cache
    ) {}

    /**
     * Invalidate all cached data for a specific cart.
     */
    public function invalidateCart(string $identifier, string $instance, ?string $ownerType = null, string|int|null $ownerId = null): void
    {
        $ownerPart = '';
        if ($ownerType !== null && $ownerId !== null) {
            $ownerPart = ':'.$ownerType.':'.$ownerId;
        }

        foreach (self::CACHE_KEYS as $key) {
            $cacheKey = self::CACHE_PREFIX.$identifier.':'.$instance.$ownerPart.':'.$key;
            $this->cache->forget($cacheKey);
        }

        // Also invalidate instances cache
        $this->cache->forget(self::CACHE_PREFIX.$identifier.':*'.$ownerPart.':instances');

        Log::debug('Cart cache invalidated', [
            'identifier' => $identifier,
            'instance' => $instance,
            'owner_type' => $ownerType,
            'owner_id' => $ownerId,
        ]);
    }

    /**
     * Invalidate specific cache keys for a cart.
     *
     * @param  array<string>  $keys
     */
    public function invalidateKeys(string $identifier, string $instance, array $keys, ?string $ownerType = null, string|int|null $ownerId = null): void
    {
        $ownerPart = '';
        if ($ownerType !== null && $ownerId !== null) {
            $ownerPart = ':'.$ownerType.':'.$ownerId;
        }

        foreach ($keys as $key) {
            $cacheKey = self::CACHE_PREFIX.$identifier.':'.$instance.$ownerPart.':'.$key;
            $this->cache->forget($cacheKey);
        }
    }

    /**
     * Invalidate all carts for a specific identifier.
     *
     * @param  array<string>  $instances
     */
    public function invalidateIdentifier(string $identifier, array $instances = [], ?string $ownerType = null, string|int|null $ownerId = null): void
    {
        foreach ($instances as $instance) {
            $this->invalidateCart($identifier, $instance, $ownerType, $ownerId);
        }

        // Invalidate the instances list itself
        $ownerPart = '';
        if ($ownerType !== null && $ownerId !== null) {
            $ownerPart = ':'.$ownerType.':'.$ownerId;
        }

        $this->cache->forget(self::CACHE_PREFIX.$identifier.':*'.$ownerPart.':instances');
    }

    /**
     * Invalidate items cache (after add/remove/update).
     */
    public function invalidateItems(string $identifier, string $instance, ?string $ownerType = null, string|int|null $ownerId = null): void
    {
        $this->invalidateKeys($identifier, $instance, ['items', 'version'], $ownerType, $ownerId);
    }

    /**
     * Invalidate conditions cache (after condition changes).
     */
    public function invalidateConditions(string $identifier, string $instance, ?string $ownerType = null, string|int|null $ownerId = null): void
    {
        $this->invalidateKeys($identifier, $instance, ['conditions', 'version'], $ownerType, $ownerId);
    }

    /**
     * Invalidate metadata cache (after metadata changes).
     */
    public function invalidateMetadata(string $identifier, string $instance, ?string $ownerType = null, string|int|null $ownerId = null): void
    {
        $this->invalidateKeys($identifier, $instance, ['metadata', 'version'], $ownerType, $ownerId);
    }
}
