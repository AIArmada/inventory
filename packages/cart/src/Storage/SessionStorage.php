<?php

declare(strict_types=1);

namespace AIArmada\Cart\Storage;

use Illuminate\Contracts\Session\Session;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;

final readonly class SessionStorage implements StorageInterface
{
    public function __construct(
        private Session $session,
        private string $keyPrefix = 'cart',
        private ?string $ownerType = null,
        private string|int|null $ownerId = null
    ) {
        //
    }

    /**
     * Create a new instance with the specified owner
     */
    public function withOwner(?Model $owner): static
    {
        return new self(
            session: $this->session,
            keyPrefix: $this->keyPrefix,
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
    public function getOwnerId(): string|int|null
    {
        return $this->ownerId;
    }

    /**
     * Check if cart exists in storage
     */
    public function has(string $identifier, string $instance): bool
    {
        return $this->session->has($this->getItemsKey($identifier, $instance)) ||
               $this->session->has($this->getConditionsKey($identifier, $instance));
    }

    /**
     * Remove cart from storage
     */
    public function forget(string $identifier, string $instance): void
    {
        $this->session->forget($this->getItemsKey($identifier, $instance));
        $this->session->forget($this->getConditionsKey($identifier, $instance));
        $this->clearMetadataKeys($identifier, $instance);
        $this->session->forget($this->getVersionKey($identifier, $instance));
        $this->session->forget($this->getIdKey($identifier, $instance));
        $this->session->forget($this->getCreatedAtKey($identifier, $instance));
        $this->session->forget($this->getUpdatedAtKey($identifier, $instance));
        $this->unregisterInstance($identifier, $instance);
    }

    /**
     * Clear all carts from storage
     */
    public function flush(): void
    {
        // Remove the entire cart data from session for this owner scope
        $this->session->forget($this->getBasePrefix());
    }

    /**
     * Get all instances for a specific identifier
     *
     * @return array<string>
     */
    public function getInstances(string $identifier): array
    {
        $registry = $this->session->get($this->getInstanceRegistryKey($identifier));
        if (is_array($registry) && $registry !== []) {
            return array_values($registry);
        }

        $instances = $this->discoverInstances($identifier);
        if ($instances !== []) {
            $this->session->put($this->getInstanceRegistryKey($identifier), $instances);
        }

        return $instances;
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

        $this->session->forget($this->getInstanceRegistryKey($identifier));
    }

    /**
     * Retrieve cart items from storage
     *
     * @return array<string, mixed>
     */
    public function getItems(string $identifier, string $instance): array
    {
        $data = $this->session->get($this->getItemsKey($identifier, $instance));

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
        $data = $this->session->get($this->getConditionsKey($identifier, $instance));

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
        $this->session->put($this->getItemsKey($identifier, $instance), $items);
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
        $this->session->put($this->getConditionsKey($identifier, $instance), $conditions);
        $this->touchCart($identifier, $instance);
    }

    /**
     * Store both items and conditions for a cart instance
     *
     * @param  array<string, mixed>  $items
     * @param  array<string, mixed>  $conditions
     */
    public function putBoth(string $identifier, string $instance, array $items, array $conditions): void
    {
        $this->validateDataSize($items, 'items');
        $this->validateDataSize($conditions, 'conditions');
        $this->session->put($this->getItemsKey($identifier, $instance), $items);
        $this->session->put($this->getConditionsKey($identifier, $instance), $conditions);
        $this->touchCart($identifier, $instance);
    }

    /**
     * Store cart metadata
     */
    public function putMetadata(string $identifier, string $instance, string $key, mixed $value): void
    {
        $metadataKey = $this->getMetadataKey($identifier, $instance, $key);
        $this->session->put($metadataKey, $value);
        $this->addMetadataKey($identifier, $instance, $key);
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

        foreach ($metadata as $key => $value) {
            $metadataKey = $this->getMetadataKey($identifier, $instance, $key);
            $this->session->put($metadataKey, $value);
        }

        $existingKeys = $this->session->get($this->getMetadataRegistryKey($identifier, $instance), []);
        $newKeys = array_unique(array_merge($existingKeys, array_keys($metadata)));
        $this->session->put($this->getMetadataRegistryKey($identifier, $instance), $newKeys);
        $this->touchCart($identifier, $instance);
    }

    /**
     * Retrieve cart metadata
     */
    public function getMetadata(string $identifier, string $instance, string $key): mixed
    {
        $metadataKey = $this->getMetadataKey($identifier, $instance, $key);

        return $this->session->get($metadataKey);
    }

    /**
     * Retrieve all cart metadata
     *
     * @return array<string, mixed>
     */
    public function getAllMetadata(string $identifier, string $instance): array
    {
        $metadata = [];
        $registryKey = $this->getMetadataRegistryKey($identifier, $instance);
        $metadataKeys = $this->session->get($registryKey);

        if (is_array($metadataKeys)) {
            foreach ($metadataKeys as $key) {
                $value = $this->session->get($this->getMetadataKey($identifier, $instance, $key));
                if ($value !== null) {
                    $metadata[$key] = $value;
                }
            }

            return $metadata;
        }

        $metadataPrefix = "{$this->getBasePrefix()}.{$identifier}.{$instance}.metadata.";
        $allKeys = $this->session->all();

        foreach ($allKeys as $key => $value) {
            if (str_starts_with((string) $key, $metadataPrefix)) {
                $metadataKey = mb_substr((string) $key, mb_strlen($metadataPrefix));
                $metadata[$metadataKey] = $value;
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
        $this->session->put($this->getItemsKey($identifier, $instance), []);
        $this->session->put($this->getConditionsKey($identifier, $instance), []);
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
     * Session storage doesn't support versioning, returns null
     */
    public function getVersion(string $identifier, string $instance): ?int
    {
        $version = $this->session->get($this->getVersionKey($identifier, $instance));

        return $version === null ? null : (int) $version;
    }

    /**
     * Get cart ID (primary key) from storage
     * Session storage doesn't have IDs, returns null
     */
    public function getId(string $identifier, string $instance): ?string
    {
        $id = $this->session->get($this->getIdKey($identifier, $instance));

        return is_string($id) ? $id : null;
    }

    /**
     * Get cart creation timestamp (not supported by session storage)
     */
    public function getCreatedAt(string $identifier, string $instance): ?string
    {
        $timestamp = $this->session->get($this->getCreatedAtKey($identifier, $instance));

        return is_string($timestamp) ? $timestamp : null;
    }

    /**
     * Get cart last updated timestamp (not supported by session storage)
     */
    public function getUpdatedAt(string $identifier, string $instance): ?string
    {
        $timestamp = $this->session->get($this->getUpdatedAtKey($identifier, $instance));

        return is_string($timestamp) ? $timestamp : null;
    }

    // =========================================================================
    // AI & Analytics Methods (Phase 0.2) - Stub implementations for session storage
    // =========================================================================

    /**
     * Get cart expiration timestamp.
     */
    public function getExpiresAt(string $identifier, string $instance): ?string
    {
        $timestamp = $this->session->get($this->getExpiresAtKey($identifier, $instance));

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
        $timestamp = $this->session->get($this->getLastActivityAtKey($identifier, $instance));

        return is_string($timestamp) ? $timestamp : null;
    }

    /**
     * Update last activity timestamp.
     */
    public function touchLastActivity(string $identifier, string $instance): void
    {
        $this->session->put(
            $this->getLastActivityAtKey($identifier, $instance),
            now()->toDateTimeString()
        );
    }

    /**
     * Get checkout started timestamp.
     */
    public function getCheckoutStartedAt(string $identifier, string $instance): ?string
    {
        $timestamp = $this->session->get($this->getCheckoutStartedAtKey($identifier, $instance));

        return is_string($timestamp) ? $timestamp : null;
    }

    /**
     * Mark checkout as started for conversion funnel tracking.
     */
    public function markCheckoutStarted(string $identifier, string $instance): void
    {
        $this->session->put(
            $this->getCheckoutStartedAtKey($identifier, $instance),
            now()->toDateTimeString()
        );
    }

    /**
     * Get checkout abandoned timestamp.
     */
    public function getCheckoutAbandonedAt(string $identifier, string $instance): ?string
    {
        $timestamp = $this->session->get($this->getCheckoutAbandonedAtKey($identifier, $instance));

        return is_string($timestamp) ? $timestamp : null;
    }

    /**
     * Mark checkout as abandoned for recovery tracking.
     */
    public function markCheckoutAbandoned(string $identifier, string $instance): void
    {
        $this->session->put(
            $this->getCheckoutAbandonedAtKey($identifier, $instance),
            now()->toDateTimeString()
        );
    }

    /**
     * Get number of recovery attempts made.
     */
    public function getRecoveryAttempts(string $identifier, string $instance): int
    {
        $attempts = $this->session->get($this->getRecoveryAttemptsKey($identifier, $instance));

        return $attempts !== null ? (int) $attempts : 0;
    }

    /**
     * Increment recovery attempts counter.
     */
    public function incrementRecoveryAttempts(string $identifier, string $instance): void
    {
        $key = $this->getRecoveryAttemptsKey($identifier, $instance);
        $current = $this->getRecoveryAttempts($identifier, $instance);
        $this->session->put($key, $current + 1);
    }

    /**
     * Get recovered at timestamp.
     */
    public function getRecoveredAt(string $identifier, string $instance): ?string
    {
        $timestamp = $this->session->get($this->getRecoveredAtKey($identifier, $instance));

        return is_string($timestamp) ? $timestamp : null;
    }

    /**
     * Mark cart as recovered (user returned after abandonment).
     */
    public function markRecovered(string $identifier, string $instance): void
    {
        $this->session->put(
            $this->getRecoveredAtKey($identifier, $instance),
            now()->toDateTimeString()
        );
    }

    /**
     * Clear all abandonment tracking data (checkout started, abandoned, recovery).
     */
    public function clearAbandonmentTracking(string $identifier, string $instance): void
    {
        $this->session->forget($this->getCheckoutStartedAtKey($identifier, $instance));
        $this->session->forget($this->getCheckoutAbandonedAtKey($identifier, $instance));
        $this->session->forget($this->getRecoveryAttemptsKey($identifier, $instance));
        $this->session->forget($this->getRecoveredAtKey($identifier, $instance));
    }

    // =========================================================================
    // Event Sourcing Methods (Phase 0.3) - Stub implementations for session storage
    // =========================================================================

    /**
     * Get current event stream position for replay.
     */
    public function getEventStreamPosition(string $identifier, string $instance): int
    {
        $position = $this->session->get($this->getEventStreamPositionKey($identifier, $instance));

        return $position !== null ? (int) $position : 0;
    }

    /**
     * Update event stream position after recording events.
     */
    public function setEventStreamPosition(string $identifier, string $instance, int $position): void
    {
        $this->session->put($this->getEventStreamPositionKey($identifier, $instance), $position);
    }

    /**
     * Get aggregate schema version for migrations.
     */
    public function getAggregateVersion(string $identifier, string $instance): string
    {
        $version = $this->session->get($this->getAggregateVersionKey($identifier, $instance));

        return is_string($version) ? $version : '1.0';
    }

    /**
     * Update aggregate schema version.
     */
    public function setAggregateVersion(string $identifier, string $instance, string $version): void
    {
        $this->session->put($this->getAggregateVersionKey($identifier, $instance), $version);
    }

    /**
     * Get last snapshot timestamp.
     */
    public function getSnapshotAt(string $identifier, string $instance): ?string
    {
        $timestamp = $this->session->get($this->getSnapshotAtKey($identifier, $instance));

        return is_string($timestamp) ? $timestamp : null;
    }

    /**
     * Update snapshot timestamp after taking a snapshot.
     */
    public function markSnapshotTaken(string $identifier, string $instance): void
    {
        $this->session->put(
            $this->getSnapshotAtKey($identifier, $instance),
            now()->toDateTimeString()
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
     * Recursively find all metadata keys in session data
     *
     * @param  array<string, mixed>  $data
     * @param  array<string>  $keysToRemove
     */
    private function findMetadataKeys(array $data, string $prefix, string $currentPath, array &$keysToRemove): void
    {
        foreach ($data as $key => $value) {
            $fullPath = $currentPath === '' ? (string) $key : $currentPath.'.'.$key;

            if (str_starts_with($fullPath, $prefix)) {
                $keysToRemove[] = $fullPath;
            } elseif (is_array($value) && str_starts_with($prefix, $fullPath.'.')) {
                // Only recurse if the prefix could potentially match deeper keys
                $this->findMetadataKeys($value, $prefix, $fullPath, $keysToRemove);
            }
        }
    }

    /**
     * Discover instances by inspecting session keys (legacy fallback).
     *
     * @return array<string>
     */
    private function discoverInstances(string $identifier): array
    {
        $instances = [];
        $allSessionData = $this->session->all();
        $itemsPrefix = "{$this->getBasePrefix()}.{$identifier}.";

        foreach (array_keys($allSessionData) as $key) {
            if (str_starts_with((string) $key, $itemsPrefix)) {
                $remainder = mb_substr((string) $key, mb_strlen($itemsPrefix));
                $parts = explode('.', $remainder);
                if (count($parts) >= 2 && ($parts[1] === 'items' || $parts[1] === 'conditions')) {
                    $instance = $parts[0];
                    if (! in_array($instance, $instances, true)) {
                        $instances[] = $instance;
                    }
                }
            }
        }

        return $instances;
    }

    private function getInstanceRegistryKey(string $identifier): string
    {
        return "{$this->getBasePrefix()}.{$identifier}._instances";
    }

    private function registerInstance(string $identifier, string $instance): void
    {
        $registryKey = $this->getInstanceRegistryKey($identifier);
        $instances = $this->session->get($registryKey, []);

        if (! in_array($instance, $instances, true)) {
            $instances[] = $instance;
            $this->session->put($registryKey, $instances);
        }
    }

    private function unregisterInstance(string $identifier, string $instance): void
    {
        $registryKey = $this->getInstanceRegistryKey($identifier);
        $instances = $this->session->get($registryKey, []);

        if ($instances === []) {
            return;
        }

        $filtered = array_values(array_filter($instances, fn (string $value) => $value !== $instance));
        if ($filtered === []) {
            $this->session->forget($registryKey);
        } else {
            $this->session->put($registryKey, $filtered);
        }
    }

    private function touchCart(string $identifier, string $instance): void
    {
        $this->registerInstance($identifier, $instance);
        $now = now()->toIso8601String();

        $idKey = $this->getIdKey($identifier, $instance);
        if (! $this->session->has($idKey)) {
            $this->session->put($idKey, (string) Str::uuid());
            $this->session->put($this->getCreatedAtKey($identifier, $instance), $now);
        }

        $versionKey = $this->getVersionKey($identifier, $instance);
        $version = ((int) $this->session->get($versionKey, 0)) + 1;
        $this->session->put($versionKey, $version);
        $this->session->put($this->getUpdatedAtKey($identifier, $instance), $now);
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
            $this->session->put($this->getIdKey($identifier, $instance), $id);
        }

        if ($version !== null) {
            $this->session->put($this->getVersionKey($identifier, $instance), $version);
        }

        if ($createdAt !== null) {
            $this->session->put($this->getCreatedAtKey($identifier, $instance), $createdAt);
        }

        if ($updatedAt !== null) {
            $this->session->put($this->getUpdatedAtKey($identifier, $instance), $updatedAt);
        }
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

    private function getMetadataRegistryKey(string $identifier, string $instance): string
    {
        return "{$this->getBasePrefix()}.{$identifier}.{$instance}.metadata._keys";
    }

    private function addMetadataKey(string $identifier, string $instance, string $key): void
    {
        $registryKey = $this->getMetadataRegistryKey($identifier, $instance);
        $keys = $this->session->get($registryKey, []);

        if (! in_array($key, $keys, true)) {
            $keys[] = $key;
            $this->session->put($registryKey, $keys);
        }
    }

    private function clearMetadataKeys(string $identifier, string $instance): void
    {
        $registryKey = $this->getMetadataRegistryKey($identifier, $instance);
        $metadataKeys = $this->session->get($registryKey);

        if (is_array($metadataKeys)) {
            foreach ($metadataKeys as $key) {
                $metadataKey = $this->getMetadataKey($identifier, $instance, $key);
                $this->session->forget($metadataKey);
            }

            $this->session->forget($registryKey);

            return;
        }

        // Fallback to legacy scan if registry wasn't available
        $metadataPrefix = "{$this->getBasePrefix()}.{$identifier}.{$instance}.metadata.";
        $allSessionData = $this->session->all();
        $keysToRemove = [];
        $this->findMetadataKeys($allSessionData, $metadataPrefix, '', $keysToRemove);
        foreach ($keysToRemove as $key) {
            $this->session->forget($key);
        }
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
            throw new InvalidArgumentException("Cannot validate {$type} data size: ".$e->getMessage());
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
