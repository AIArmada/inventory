<?php

declare(strict_types=1);

namespace AIArmada\Cart\Storage;

use Illuminate\Cache\Repository as Cache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;

final readonly class CacheStorage implements StorageInterface
{
    public function __construct(
        private Cache $cache,
        private string $keyPrefix = 'cart',
        private int $ttl = 86400, // 24 hours
        private bool $useLocking = false, // Enable for multi-server setups with shared cache
        private int $lockTimeout = 5, // Lock timeout in seconds
        private ?string $ownerType = null,
        private string | int | null $ownerId = null
    ) {
        //
    }

    /**
     * Create a new instance with the specified owner
     */
    public function withOwner(?Model $owner): static
    {
        return new self(
            cache: $this->cache,
            keyPrefix: $this->keyPrefix,
            ttl: $this->ttl,
            useLocking: $this->useLocking,
            lockTimeout: $this->lockTimeout,
            ownerType: $owner?->getMorphClass(),
            ownerId: $owner?->getKey()
        );
    }

    /**
     * Get the current owner type
     */
    public function getOwnerType(): ?string
    {
        return $this->ownerType;
    }

    /**
     * Get the current owner ID
     */
    public function getOwnerId(): string | int | null
    {
        return $this->ownerId;
    }

    /**
     * Check if cart exists in storage
     */
    public function has(string $identifier, string $instance): bool
    {
        // Check if either items or conditions exist for this cart
        return $this->cache->has($this->getItemsKey($identifier, $instance))
            || $this->cache->has($this->getConditionsKey($identifier, $instance));
    }

    /**
     * Remove cart from storage
     */
    public function forget(string $identifier, string $instance): void
    {
        $this->cache->forget($this->getItemsKey($identifier, $instance));
        $this->cache->forget($this->getConditionsKey($identifier, $instance));
        $this->clearMetadata($identifier, $instance);
        $this->cache->forget($this->getVersionKey($identifier, $instance));
        $this->cache->forget($this->getIdKey($identifier, $instance));
        $this->cache->forget($this->getCreatedAtKey($identifier, $instance));
        $this->cache->forget($this->getUpdatedAtKey($identifier, $instance));
        $this->unregisterInstance($identifier, $instance);
    }

    /**
     * Clear all carts from storage
     */
    public function flush(): void
    {
        // For cache storage, we'll clear all items
        // In production you might want to use cache tags for more granular control
        $store = $this->cache->getStore();
        if (method_exists($store, 'flush')) { // @phpstan-ignore function.alreadyNarrowedType
            $store->flush();
        }
    }

    /**
     * Get all instances for a specific identifier
     *
     * @return array<string, mixed>
     */
    /**
     * @return array<int, mixed>
     */
    public function getInstances(string $identifier): array
    {
        $instances = $this->cache->get($this->getInstanceRegistryKey($identifier), []);

        return is_array($instances) ? array_values($instances) : [];
    }

    /**
     * Remove all instances for a specific identifier
     */
    public function forgetIdentifier(string $identifier): void
    {
        $instances = $this->getInstances($identifier);

        foreach ($instances as $instance) {
            $this->forget($identifier, $instance);
        }

        $this->cache->forget($this->getInstanceRegistryKey($identifier));
    }

    /**
     * Retrieve cart items from storage
     *
     * @return array<string, mixed>
     */
    public function getItems(string $identifier, string $instance): array
    {
        $data = $this->cache->get($this->getItemsKey($identifier, $instance));

        if (is_string($data)) {
            return json_decode($data, true) ?: [];
        }

        return $data ?: [];
    }

    /**
     * Retrieve cart conditions from storage
     *
     * @return array<string, mixed>
     */
    public function getConditions(string $identifier, string $instance): array
    {
        $data = $this->cache->get($this->getConditionsKey($identifier, $instance));

        if (is_string($data)) {
            return json_decode($data, true) ?: [];
        }

        return $data ?: [];
    }

    /**
     * Store cart items in storage
     *
     * @param  array<string, mixed>  $items
     */
    public function putItems(string $identifier, string $instance, array $items): void
    {
        $this->validateDataSize($items, 'items');
        $this->storeItemsPayload($identifier, $instance, $items);
        $this->touchCart($identifier, $instance);
    }

    /**
     * Store cart conditions in storage
     *
     * @param  array<string, mixed>  $conditions
     */
    public function putConditions(string $identifier, string $instance, array $conditions): void
    {
        $this->validateDataSize($conditions, 'conditions');
        $this->storeConditionsPayload($identifier, $instance, $conditions);
        $this->touchCart($identifier, $instance);
    }

    /**
     * Store both items and conditions in storage
     *
     * @param  array<string, mixed>  $items
     * @param  array<string, mixed>  $conditions
     */
    public function putBoth(string $identifier, string $instance, array $items, array $conditions): void
    {
        $this->validateDataSize($items, 'items');
        $this->validateDataSize($conditions, 'conditions');
        $this->storeItemsPayload($identifier, $instance, $items);
        $this->storeConditionsPayload($identifier, $instance, $conditions);
        $this->touchCart($identifier, $instance);
    }

    /**
     * Store cart metadata
     */
    public function putMetadata(string $identifier, string $instance, string $key, mixed $value): void
    {
        $metadataKey = $this->getMetadataKey($identifier, $instance, $key);

        if ($this->useLocking && method_exists($this->cache, 'lock')) {
            $this->putMetadataWithLock($metadataKey, $value);
        } else {
            $this->cache->put($metadataKey, $value, $this->ttl);
        }

        // Track metadata key in registry for clearMetadata support
        $keysRegistryKey = "{$this->getBasePrefix()}.{$identifier}.{$instance}.metadata._keys";
        $metadataKeys = $this->cache->get($keysRegistryKey, []);
        if (! in_array($key, $metadataKeys, true)) {
            $metadataKeys[] = $key;
            $this->cache->put($keysRegistryKey, $metadataKeys, $this->ttl);
        }

        $this->touchCart($identifier, $instance);
    }

    /**
     * Store multiple metadata values at once
     *
     * @param  array<string, mixed>  $metadata
     */
    public function putMetadataBatch(string $identifier, string $instance, array $metadata): void
    {
        if (empty($metadata)) {
            return;
        }

        $keysRegistryKey = "{$this->getBasePrefix()}.{$identifier}.{$instance}.metadata._keys";

        if ($this->useLocking && method_exists($this->cache, 'lock')) {
            $lock = $this->cache->lock("{$this->getBasePrefix()}.lock.{$identifier}.{$instance}.metadata", 10);

            try {
                $lock->block(5);

                // Store all metadata values
                foreach ($metadata as $key => $value) {
                    $metadataKey = $this->getMetadataKey($identifier, $instance, $key);
                    $this->cache->put($metadataKey, $value, $this->ttl);
                }

                // Update registry with all new keys
                $metadataKeys = $this->cache->get($keysRegistryKey, []);
                $newKeys = array_unique(array_merge($metadataKeys, array_keys($metadata)));
                $this->cache->put($keysRegistryKey, $newKeys, $this->ttl);
            } finally {
                $lock->release();
            }
        } else {
            // Store all metadata values without locking
            foreach ($metadata as $key => $value) {
                $metadataKey = $this->getMetadataKey($identifier, $instance, $key);
                $this->cache->put($metadataKey, $value, $this->ttl);
            }

            // Update registry with all new keys
            $metadataKeys = $this->cache->get($keysRegistryKey, []);
            $newKeys = array_unique(array_merge($metadataKeys, array_keys($metadata)));
            $this->cache->put($keysRegistryKey, $newKeys, $this->ttl);
        }

        $this->touchCart($identifier, $instance);
    }

    /**
     * Retrieve cart metadata
     */
    public function getMetadata(string $identifier, string $instance, string $key): mixed
    {
        $metadataKey = $this->getMetadataKey($identifier, $instance, $key);

        return $this->cache->get($metadataKey);
    }

    /**
     * Retrieve all cart metadata
     *
     * @return array<string, mixed>
     */
    public function getAllMetadata(string $identifier, string $instance): array
    {
        $keysRegistryKey = "{$this->getBasePrefix()}.{$identifier}.{$instance}.metadata._keys";
        $metadataKeys = $this->cache->get($keysRegistryKey, []);
        $metadata = [];

        foreach ($metadataKeys as $key) {
            $metadataKey = $this->getMetadataKey($identifier, $instance, $key);
            $value = $this->cache->get($metadataKey);
            if ($value !== null) {
                $metadata[$key] = $value;
            }
        }

        return $metadata;
    }

    /**
     * Clear all metadata for a cart
     */
    public function clearMetadata(string $identifier, string $instance): void
    {
        $this->clearMetadataKeys($identifier, $instance);
        $this->touchCart($identifier, $instance);
    }

    /**
     * Clear all cart data (items, conditions, metadata) in a single operation
     */
    public function clearAll(string $identifier, string $instance): void
    {
        $this->storeItemsPayload($identifier, $instance, []);
        $this->storeConditionsPayload($identifier, $instance, []);
        $this->clearMetadataKeys($identifier, $instance);
        $this->touchCart($identifier, $instance);
    }

    /**
     * Swap cart identifier by transferring cart data from old identifier to new identifier.
     * This changes cart ownership to ensure the new identifier has an active cart.
     */
    public function swapIdentifier(string $oldIdentifier, string $newIdentifier, string $instance): bool
    {
        if (! $this->has($oldIdentifier, $instance)) {
            return false;
        }

        // Remove any existing cart for the new identifier to mirror DB behaviour
        if ($this->has($newIdentifier, $instance)) {
            $this->forget($newIdentifier, $instance);
        }

        $items = $this->getItems($oldIdentifier, $instance);
        $conditions = $this->getConditions($oldIdentifier, $instance);
        $metadata = $this->getAllMetadata($oldIdentifier, $instance);
        $version = $this->getVersion($oldIdentifier, $instance);
        $id = $this->getId($oldIdentifier, $instance);
        $createdAt = $this->getCreatedAt($oldIdentifier, $instance);
        $updatedAt = $this->getUpdatedAt($oldIdentifier, $instance);

        $this->putBoth($newIdentifier, $instance, $items, $conditions);

        if (! empty($metadata)) {
            $this->putMetadataBatch($newIdentifier, $instance, $metadata);
        }

        $this->overwriteCartMetadata($newIdentifier, $instance, $id, $version, $createdAt, $updatedAt);

        $this->forget($oldIdentifier, $instance);

        return true;
    }

    /**
     * Get cart version for change tracking
     * Cache storage doesn't support versioning, returns null
     */
    public function getVersion(string $identifier, string $instance): ?int
    {
        $version = $this->cache->get($this->getVersionKey($identifier, $instance));

        return $version === null ? null : (int) $version;
    }

    /**
     * Get cart ID (primary key) from storage
     * Cache storage doesn't have IDs, returns null
     */
    public function getId(string $identifier, string $instance): ?string
    {
        $id = $this->cache->get($this->getIdKey($identifier, $instance));

        return is_string($id) ? $id : null;
    }

    /**
     * Get cart creation timestamp (not supported by cache storage)
     */
    public function getCreatedAt(string $identifier, string $instance): ?string
    {
        $timestamp = $this->cache->get($this->getCreatedAtKey($identifier, $instance));

        return is_string($timestamp) ? $timestamp : null;
    }

    /**
     * Get cart last updated timestamp (not supported by cache storage)
     */
    public function getUpdatedAt(string $identifier, string $instance): ?string
    {
        $timestamp = $this->cache->get($this->getUpdatedAtKey($identifier, $instance));

        return is_string($timestamp) ? $timestamp : null;
    }

    // =========================================================================
    // AI & Analytics Methods (Phase 0.2) - Stub implementations for cache storage
    // =========================================================================

    /**
     * Get cart expiration timestamp.
     */
    public function getExpiresAt(string $identifier, string $instance): ?string
    {
        $timestamp = $this->cache->get($this->getExpiresAtKey($identifier, $instance));

        return is_string($timestamp) ? $timestamp : null;
    }

    /**
     * Check if a cart has expired.
     */
    public function isExpired(string $identifier, string $instance): bool
    {
        $expiresAt = $this->getExpiresAt($identifier, $instance);

        if ($expiresAt === null) {
            return false;
        }

        return now()->isAfter($expiresAt);
    }

    /**
     * Get last activity timestamp for engagement tracking.
     */
    public function getLastActivityAt(string $identifier, string $instance): ?string
    {
        $timestamp = $this->cache->get($this->getLastActivityAtKey($identifier, $instance));

        return is_string($timestamp) ? $timestamp : null;
    }

    /**
     * Update last activity timestamp.
     */
    public function touchLastActivity(string $identifier, string $instance): void
    {
        $this->cache->put(
            $this->getLastActivityAtKey($identifier, $instance),
            now()->toDateTimeString(),
            $this->ttl
        );
    }

    /**
     * Get checkout started timestamp.
     */
    public function getCheckoutStartedAt(string $identifier, string $instance): ?string
    {
        $timestamp = $this->cache->get($this->getCheckoutStartedAtKey($identifier, $instance));

        return is_string($timestamp) ? $timestamp : null;
    }

    /**
     * Mark checkout as started for conversion funnel tracking.
     */
    public function markCheckoutStarted(string $identifier, string $instance): void
    {
        $this->cache->put(
            $this->getCheckoutStartedAtKey($identifier, $instance),
            now()->toDateTimeString(),
            $this->ttl
        );
    }

    /**
     * Get checkout abandoned timestamp.
     */
    public function getCheckoutAbandonedAt(string $identifier, string $instance): ?string
    {
        $timestamp = $this->cache->get($this->getCheckoutAbandonedAtKey($identifier, $instance));

        return is_string($timestamp) ? $timestamp : null;
    }

    /**
     * Mark checkout as abandoned for recovery tracking.
     */
    public function markCheckoutAbandoned(string $identifier, string $instance): void
    {
        $this->cache->put(
            $this->getCheckoutAbandonedAtKey($identifier, $instance),
            now()->toDateTimeString(),
            $this->ttl
        );
    }

    /**
     * Get number of recovery attempts made.
     */
    public function getRecoveryAttempts(string $identifier, string $instance): int
    {
        $attempts = $this->cache->get($this->getRecoveryAttemptsKey($identifier, $instance));

        return $attempts !== null ? (int) $attempts : 0;
    }

    /**
     * Increment recovery attempts counter.
     */
    public function incrementRecoveryAttempts(string $identifier, string $instance): void
    {
        $key = $this->getRecoveryAttemptsKey($identifier, $instance);
        $current = $this->getRecoveryAttempts($identifier, $instance);
        $this->cache->put($key, $current + 1, $this->ttl);
    }

    /**
     * Get recovered at timestamp.
     */
    public function getRecoveredAt(string $identifier, string $instance): ?string
    {
        $timestamp = $this->cache->get($this->getRecoveredAtKey($identifier, $instance));

        return is_string($timestamp) ? $timestamp : null;
    }

    /**
     * Mark cart as recovered (user returned after abandonment).
     */
    public function markRecovered(string $identifier, string $instance): void
    {
        $this->cache->put(
            $this->getRecoveredAtKey($identifier, $instance),
            now()->toDateTimeString(),
            $this->ttl
        );
    }

    /**
     * Clear all abandonment tracking data (checkout started, abandoned, recovery).
     */
    public function clearAbandonmentTracking(string $identifier, string $instance): void
    {
        $this->cache->forget($this->getCheckoutStartedAtKey($identifier, $instance));
        $this->cache->forget($this->getCheckoutAbandonedAtKey($identifier, $instance));
        $this->cache->forget($this->getRecoveryAttemptsKey($identifier, $instance));
        $this->cache->forget($this->getRecoveredAtKey($identifier, $instance));
    }

    // =========================================================================
    // Event Sourcing Methods (Phase 0.3) - Stub implementations for cache storage
    // =========================================================================

    /**
     * Get current event stream position for replay.
     */
    public function getEventStreamPosition(string $identifier, string $instance): int
    {
        $position = $this->cache->get($this->getEventStreamPositionKey($identifier, $instance));

        return $position !== null ? (int) $position : 0;
    }

    /**
     * Update event stream position after recording events.
     */
    public function setEventStreamPosition(string $identifier, string $instance, int $position): void
    {
        $this->cache->put($this->getEventStreamPositionKey($identifier, $instance), $position, $this->ttl);
    }

    /**
     * Get aggregate schema version for migrations.
     */
    public function getAggregateVersion(string $identifier, string $instance): string
    {
        $version = $this->cache->get($this->getAggregateVersionKey($identifier, $instance));

        return is_string($version) ? $version : '1.0';
    }

    /**
     * Update aggregate schema version.
     */
    public function setAggregateVersion(string $identifier, string $instance, string $version): void
    {
        $this->cache->put($this->getAggregateVersionKey($identifier, $instance), $version, $this->ttl);
    }

    /**
     * Get last snapshot timestamp.
     */
    public function getSnapshotAt(string $identifier, string $instance): ?string
    {
        $timestamp = $this->cache->get($this->getSnapshotAtKey($identifier, $instance));

        return is_string($timestamp) ? $timestamp : null;
    }

    /**
     * Update snapshot timestamp after taking a snapshot.
     */
    public function markSnapshotTaken(string $identifier, string $instance): void
    {
        $this->cache->put(
            $this->getSnapshotAtKey($identifier, $instance),
            now()->toDateTimeString(),
            $this->ttl
        );
    }

    /**
     * Get the base key prefix including owner scope when set
     */
    private function getBasePrefix(): string
    {
        if ($this->ownerType !== null && $this->ownerId !== null) {
            return "{$this->keyPrefix}.owner.{$this->ownerType}.{$this->ownerId}";
        }

        return $this->keyPrefix;
    }

    /**
     * @param  array<string, mixed>  $items
     */
    private function storeItemsPayload(string $identifier, string $instance, array $items): void
    {
        if ($this->useLocking) {
            $this->putItemsWithLock($identifier, $instance, $items);
        } else {
            $this->putItemsSimple($identifier, $instance, $items);
        }
    }

    /**
     * @param  array<string, mixed>  $conditions
     */
    private function storeConditionsPayload(string $identifier, string $instance, array $conditions): void
    {
        if ($this->useLocking) {
            $this->putConditionsWithLock($identifier, $instance, $conditions);
        } else {
            $this->putConditionsSimple($identifier, $instance, $conditions);
        }
    }

    /**
     * Store items with locking to prevent concurrent modification
     *
     * @param  array<string, mixed>  $items
     */
    private function putItemsWithLock(string $identifier, string $instance, array $items): void
    {
        $key = $this->getItemsKey($identifier, $instance);

        if (! method_exists($this->cache, 'lock')) {
            // Fallback for cache drivers that don't support locking
            $this->cache->put($key, $items, $this->ttl);

            return;
        }

        $lock = $this->cache->lock("lock.{$key}", $this->lockTimeout);

        $lock->block($this->lockTimeout, function () use ($key, $items): void {
            $this->cache->put($key, $items, $this->ttl);
        });
    }

    /**
     * Store items without locking (simple/fast mode)
     *
     * @param  array<string, mixed>  $items
     */
    private function putItemsSimple(string $identifier, string $instance, array $items): void
    {
        $this->cache->put($this->getItemsKey($identifier, $instance), $items, $this->ttl);
    }

    /**
     * Store conditions with locking to prevent concurrent modification
     *
     * @param  array<string, mixed>  $conditions
     */
    private function putConditionsWithLock(string $identifier, string $instance, array $conditions): void
    {
        $key = $this->getConditionsKey($identifier, $instance);

        if (! method_exists($this->cache, 'lock')) {
            // Fallback for cache drivers that don't support locking
            $this->cache->put($key, $conditions, $this->ttl);

            return;
        }

        $lock = $this->cache->lock("lock.{$key}", $this->lockTimeout);

        $lock->block($this->lockTimeout, function () use ($key, $conditions): void {
            $this->cache->put($key, $conditions, $this->ttl);
        });
    }

    /**
     * Store conditions without locking (simple/fast mode)
     *
     * @param  array<string, mixed>  $conditions
     */
    private function putConditionsSimple(string $identifier, string $instance, array $conditions): void
    {
        $this->cache->put($this->getConditionsKey($identifier, $instance), $conditions, $this->ttl);
    }

    /**
     * Store metadata with locking to prevent concurrent modification
     */
    private function putMetadataWithLock(string $metadataKey, mixed $value): void
    {
        if (! method_exists($this->cache, 'lock')) {
            $this->cache->put($metadataKey, $value, $this->ttl);

            return;
        }

        $lock = $this->cache->lock("lock.{$metadataKey}", $this->lockTimeout);

        $lock->block($this->lockTimeout, function () use ($metadataKey, $value): void {
            $this->cache->put($metadataKey, $value, $this->ttl);
        });
    }

    /**
     * Register the cart instance for faster lookups.
     */
    private function registerInstance(string $identifier, string $instance): void
    {
        $instances = $this->cache->get($this->getInstanceRegistryKey($identifier), []);
        if (! in_array($instance, $instances, true)) {
            $instances[] = $instance;
        }

        $this->cache->put($this->getInstanceRegistryKey($identifier), $instances, $this->ttl);
    }

    /**
     * Unregister the cart instance.
     */
    private function unregisterInstance(string $identifier, string $instance): void
    {
        $instances = $this->cache->get($this->getInstanceRegistryKey($identifier), []);

        if ($instances === []) {
            return;
        }

        $filtered = array_values(array_filter($instances, fn (string $value) => $value !== $instance));
        if ($filtered === []) {
            $this->cache->forget($this->getInstanceRegistryKey($identifier));
        } else {
            $this->cache->put($this->getInstanceRegistryKey($identifier), $filtered, $this->ttl);
        }
    }

    private function touchCart(string $identifier, string $instance): void
    {
        $this->registerInstance($identifier, $instance);

        $now = now()->toIso8601String();
        $idKey = $this->getIdKey($identifier, $instance);
        if (! $this->cache->has($idKey)) {
            $this->cache->put($idKey, (string) Str::uuid(), $this->ttl);
            $this->cache->put($this->getCreatedAtKey($identifier, $instance), $now, $this->ttl);
        }

        $this->cache->put($this->getUpdatedAtKey($identifier, $instance), $now, $this->ttl);

        $versionKey = $this->getVersionKey($identifier, $instance);
        $version = ((int) $this->cache->get($versionKey, 0)) + 1;
        $this->cache->put($versionKey, $version, $this->ttl);
    }

    private function overwriteCartMetadata(
        string $identifier,
        string $instance,
        ?string $id,
        ?int $version,
        ?string $createdAt,
        ?string $updatedAt
    ): void {
        if ($id !== null) {
            $this->cache->put($this->getIdKey($identifier, $instance), $id, $this->ttl);
        }

        if ($version !== null) {
            $this->cache->put($this->getVersionKey($identifier, $instance), $version, $this->ttl);
        }

        if ($createdAt !== null) {
            $this->cache->put($this->getCreatedAtKey($identifier, $instance), $createdAt, $this->ttl);
        }

        if ($updatedAt !== null) {
            $this->cache->put($this->getUpdatedAtKey($identifier, $instance), $updatedAt, $this->ttl);
        }
    }

    private function getInstanceRegistryKey(string $identifier): string
    {
        return "{$this->getBasePrefix()}.{$identifier}._instances";
    }

    private function getVersionKey(string $identifier, string $instance): string
    {
        return "{$this->getBasePrefix()}.{$identifier}.{$instance}.version";
    }

    private function getIdKey(string $identifier, string $instance): string
    {
        return "{$this->getBasePrefix()}.{$identifier}.{$instance}.id";
    }

    private function getCreatedAtKey(string $identifier, string $instance): string
    {
        return "{$this->getBasePrefix()}.{$identifier}.{$instance}.created_at";
    }

    private function getUpdatedAtKey(string $identifier, string $instance): string
    {
        return "{$this->getBasePrefix()}.{$identifier}.{$instance}.updated_at";
    }

    /**
     * Validate data size to prevent memory issues and DoS attacks
     *
     * @param  array<string, mixed>  $data
     */
    private function validateDataSize(array $data, string $type): void
    {
        // Get size limits from config or use defaults
        $maxItems = config('cart.limits.max_items', 1000);
        $maxDataSize = config('cart.limits.max_data_size_bytes', 1024 * 1024); // 1MB default

        // Check item count limit
        if ($type === 'items' && count($data) > $maxItems) {
            throw new InvalidArgumentException("Cart cannot contain more than {$maxItems} items");
        }

        // Check data size limit
        try {
            $jsonSize = mb_strlen(json_encode($data, JSON_THROW_ON_ERROR));
            if ($jsonSize > $maxDataSize) {
                $maxSizeMB = round($maxDataSize / (1024 * 1024), 2);

                throw new InvalidArgumentException("Cart {$type} data size ({$jsonSize} bytes) exceeds maximum allowed size of {$maxSizeMB}MB");
            }
        } catch (JsonException $e) {
            throw new InvalidArgumentException("Cannot validate {$type} data size: " . $e->getMessage());
        }
    }

    /**
     * Get the items storage key
     */
    private function getItemsKey(string $identifier, string $instance): string
    {
        return "{$this->getBasePrefix()}.{$identifier}.{$instance}.items";
    }

    /**
     * Get the conditions storage key
     */
    private function getConditionsKey(string $identifier, string $instance): string
    {
        return "{$this->getBasePrefix()}.{$identifier}.{$instance}.conditions";
    }

    /**
     * Get the metadata storage key
     */
    private function getMetadataKey(string $identifier, string $instance, string $key): string
    {
        return "{$this->getBasePrefix()}.{$identifier}.{$instance}.metadata.{$key}";
    }

    private function clearMetadataKeys(string $identifier, string $instance): void
    {
        $keysRegistryKey = "{$this->getBasePrefix()}.{$identifier}.{$instance}.metadata._keys";
        $metadataKeys = $this->cache->get($keysRegistryKey, []);

        foreach ($metadataKeys as $key) {
            $metadataKey = $this->getMetadataKey($identifier, $instance, $key);
            $this->cache->forget($metadataKey);
        }

        $this->cache->forget($keysRegistryKey);
    }

    // =========================================================================
    // Additional Key Generators
    // =========================================================================

    private function getExpiresAtKey(string $identifier, string $instance): string
    {
        return "{$this->getBasePrefix()}.{$identifier}.{$instance}.expires_at";
    }

    private function getLastActivityAtKey(string $identifier, string $instance): string
    {
        return "{$this->getBasePrefix()}.{$identifier}.{$instance}.last_activity_at";
    }

    private function getCheckoutStartedAtKey(string $identifier, string $instance): string
    {
        return "{$this->getBasePrefix()}.{$identifier}.{$instance}.checkout_started_at";
    }

    private function getCheckoutAbandonedAtKey(string $identifier, string $instance): string
    {
        return "{$this->getBasePrefix()}.{$identifier}.{$instance}.checkout_abandoned_at";
    }

    private function getRecoveryAttemptsKey(string $identifier, string $instance): string
    {
        return "{$this->getBasePrefix()}.{$identifier}.{$instance}.recovery_attempts";
    }

    private function getRecoveredAtKey(string $identifier, string $instance): string
    {
        return "{$this->getBasePrefix()}.{$identifier}.{$instance}.recovered_at";
    }

    private function getEventStreamPositionKey(string $identifier, string $instance): string
    {
        return "{$this->getBasePrefix()}.{$identifier}.{$instance}.event_stream_position";
    }

    private function getAggregateVersionKey(string $identifier, string $instance): string
    {
        return "{$this->getBasePrefix()}.{$identifier}.{$instance}.aggregate_version";
    }

    private function getSnapshotAtKey(string $identifier, string $instance): string
    {
        return "{$this->getBasePrefix()}.{$identifier}.{$instance}.snapshot_at";
    }
}
