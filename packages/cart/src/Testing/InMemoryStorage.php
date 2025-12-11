<?php

declare(strict_types=1);

namespace AIArmada\Cart\Testing;

use AIArmada\Cart\Storage\StorageInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Simple in-memory storage implementation for testing purposes.
 *
 * This storage implementation keeps all data in memory arrays,
 * making it suitable for unit testing without external dependencies.
 */
class InMemoryStorage implements StorageInterface
{
    /** @var array<string, array<string, array<array-key, mixed>>> */
    private array $items = [];

    /** @var array<string, array<string, array<array-key, mixed>>> */
    private array $conditions = [];

    /** @var array<string, array<string, array<string, mixed>>> */
    private array $metadata = [];

    /** @var array<string, array<string, array<array-key, mixed>>> */
    private array $instances = [];

    /** @var array<string, array<string, int>> */
    private array $versions = [];

    /** @var array<string, array<string, string>> */
    private array $ids = [];

    private ?string $ownerType = null;

    private string | int | null $ownerId = null;

    public function __construct(?string $ownerType = null, string | int | null $ownerId = null)
    {
        $this->ownerType = $ownerType;
        $this->ownerId = $ownerId;
    }

    /**
     * Create a new instance with the specified owner
     */
    public function withOwner(?Model $owner): static
    {
        $storage = clone $this;
        $storage->ownerType = $owner?->getMorphClass();
        $storage->ownerId = $owner?->getKey();

        return $storage;
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

    public function has(string $identifier, string $instance): bool
    {
        $key = $this->scopedKey($identifier);

        return isset($this->items[$key][$instance]) ||
               isset($this->conditions[$key][$instance]);
    }

    public function getItems(string $identifier, string $instance): array
    {
        $key = $this->scopedKey($identifier);

        return $this->items[$key][$instance] ?? [];
    }

    public function putItems(string $identifier, string $instance, array $items): void
    {
        $key = $this->scopedKey($identifier);
        $this->items[$key][$instance] = $items;
        $this->instances[$key][$instance] = true;
        $this->incrementVersion($identifier, $instance);
    }

    public function getConditions(string $identifier, string $instance): array
    {
        $key = $this->scopedKey($identifier);

        return $this->conditions[$key][$instance] ?? [];
    }

    public function putConditions(string $identifier, string $instance, array $conditions): void
    {
        $key = $this->scopedKey($identifier);
        $this->conditions[$key][$instance] = $conditions;
        $this->instances[$key][$instance] = true;
        $this->incrementVersion($identifier, $instance);
    }

    public function forget(string $identifier, string $instance): void
    {
        $key = $this->scopedKey($identifier);
        unset(
            $this->items[$key][$instance],
            $this->conditions[$key][$instance],
            $this->metadata[$key][$instance],
            $this->versions[$key][$instance],
            $this->ids[$key][$instance]
        );

        if (empty($this->items[$key])) {
            unset($this->items[$key]);
        }
        if (empty($this->conditions[$key])) {
            unset($this->conditions[$key]);
        }
        if (empty($this->metadata[$key])) {
            unset($this->metadata[$key]);
        }
        if (empty($this->versions[$key])) {
            unset($this->versions[$key]);
        }
        if (empty($this->ids[$key])) {
            unset($this->ids[$key]);
        }
    }

    public function forgetIdentifier(string $identifier): void
    {
        $key = $this->scopedKey($identifier);
        unset(
            $this->items[$key],
            $this->conditions[$key],
            $this->metadata[$key],
            $this->versions[$key],
            $this->ids[$key],
            $this->instances[$key]
        );
    }

    public function flush(): void
    {
        $this->items = [];
        $this->conditions = [];
        $this->metadata = [];
        $this->instances = [];
        $this->versions = [];
        $this->ids = [];
    }

    public function getInstances(string $identifier): array
    {
        $key = $this->scopedKey($identifier);

        return array_keys($this->instances[$key] ?? []);
    }

    public function putMetadata(string $identifier, string $instance, string $key, mixed $value): void
    {
        $scopedKey = $this->scopedKey($identifier);
        $this->metadata[$scopedKey][$instance][$key] = $value;
        $this->incrementVersion($identifier, $instance);
    }

    public function putMetadataBatch(string $identifier, string $instance, array $metadata): void
    {
        if (empty($metadata)) {
            return;
        }

        $scopedKey = $this->scopedKey($identifier);
        foreach ($metadata as $key => $value) {
            $this->metadata[$scopedKey][$instance][$key] = $value;
        }
        $this->incrementVersion($identifier, $instance);
    }

    public function getMetadata(string $identifier, string $instance, string $key): mixed
    {
        $scopedKey = $this->scopedKey($identifier);

        return $this->metadata[$scopedKey][$instance][$key] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAllMetadata(string $identifier, string $instance): array
    {
        $key = $this->scopedKey($identifier);

        return $this->metadata[$key][$instance] ?? [];
    }

    public function clearMetadata(string $identifier, string $instance): void
    {
        $key = $this->scopedKey($identifier);
        if (isset($this->metadata[$key][$instance])) {
            unset($this->metadata[$key][$instance]);
        }
        $this->incrementVersion($identifier, $instance);
    }

    public function clearAll(string $identifier, string $instance): void
    {
        $key = $this->scopedKey($identifier);
        $this->items[$key][$instance] = [];
        $this->conditions[$key][$instance] = [];
        if (isset($this->metadata[$key][$instance])) {
            unset($this->metadata[$key][$instance]);
        }
        $this->incrementVersion($identifier, $instance);
    }

    public function getVersion(string $identifier, string $instance): ?int
    {
        $key = $this->scopedKey($identifier);

        return $this->versions[$key][$instance] ?? null;
    }

    public function getId(string $identifier, string $instance): ?string
    {
        $key = $this->scopedKey($identifier);
        if (! isset($this->ids[$key][$instance])) {
            $this->ids[$key][$instance] = uniqid('cart_', true);
        }

        return $this->ids[$key][$instance];
    }

    public function swapIdentifier(string $oldIdentifier, string $newIdentifier, string $instance): bool
    {
        $oldKey = $this->scopedKey($oldIdentifier);
        $newKey = $this->scopedKey($newIdentifier);

        if (! isset($this->items[$oldKey][$instance]) && ! isset($this->conditions[$oldKey][$instance])) {
            return false;
        }

        if (isset($this->items[$oldKey][$instance])) {
            $this->items[$newKey][$instance] = $this->items[$oldKey][$instance];
            unset($this->items[$oldKey][$instance]);
        }

        if (isset($this->conditions[$oldKey][$instance])) {
            $this->conditions[$newKey][$instance] = $this->conditions[$oldKey][$instance];
            unset($this->conditions[$oldKey][$instance]);
        }

        if (isset($this->metadata[$oldKey][$instance])) {
            $this->metadata[$newKey][$instance] = $this->metadata[$oldKey][$instance];
            unset($this->metadata[$oldKey][$instance]);
        }

        if (isset($this->versions[$oldKey][$instance])) {
            $this->versions[$newKey][$instance] = $this->versions[$oldKey][$instance];
            unset($this->versions[$oldKey][$instance]);
        }

        if (isset($this->ids[$oldKey][$instance])) {
            $this->ids[$newKey][$instance] = $this->ids[$oldKey][$instance];
            unset($this->ids[$oldKey][$instance]);
        }

        return true;
    }

    public function putBoth(string $identifier, string $instance, array $items, array $conditions): void
    {
        $key = $this->scopedKey($identifier);
        $this->items[$key][$instance] = $items;
        $this->conditions[$key][$instance] = $conditions;
        $this->instances[$key][$instance] = true;
        $this->incrementVersion($identifier, $instance);
    }

    /**
     * Get cart creation timestamp (not supported by in-memory storage)
     */
    public function getCreatedAt(string $identifier, string $instance): ?string
    {
        return null;
    }

    /**
     * Get cart last updated timestamp (not supported by in-memory storage)
     */
    public function getUpdatedAt(string $identifier, string $instance): ?string
    {
        return null;
    }

    // =========================================================================
    // AI & Analytics Methods (Phase 0.2) - Stub implementations for in-memory storage
    // =========================================================================

    /**
     * Get cart expiration timestamp.
     */
    public function getExpiresAt(string $identifier, string $instance): ?string
    {
        return null;
    }

    /**
     * Check if a cart has expired.
     */
    public function isExpired(string $identifier, string $instance): bool
    {
        return false;
    }

    /**
     * Get last activity timestamp for engagement tracking.
     */
    public function getLastActivityAt(string $identifier, string $instance): ?string
    {
        return null;
    }

    /**
     * Update last activity timestamp.
     */
    public function touchLastActivity(string $identifier, string $instance): void
    {
        // No-op for in-memory storage
    }

    /**
     * Get checkout started timestamp.
     */
    public function getCheckoutStartedAt(string $identifier, string $instance): ?string
    {
        return null;
    }

    /**
     * Mark checkout as started for conversion funnel tracking.
     */
    public function markCheckoutStarted(string $identifier, string $instance): void
    {
        // No-op for in-memory storage
    }

    /**
     * Get checkout abandoned timestamp.
     */
    public function getCheckoutAbandonedAt(string $identifier, string $instance): ?string
    {
        return null;
    }

    /**
     * Mark checkout as abandoned for recovery tracking.
     */
    public function markCheckoutAbandoned(string $identifier, string $instance): void
    {
        // No-op for in-memory storage
    }

    /**
     * Get number of recovery attempts made.
     */
    public function getRecoveryAttempts(string $identifier, string $instance): int
    {
        return 0;
    }

    /**
     * Increment recovery attempts counter.
     */
    public function incrementRecoveryAttempts(string $identifier, string $instance): void
    {
        // No-op for in-memory storage
    }

    /**
     * Get recovered at timestamp.
     */
    public function getRecoveredAt(string $identifier, string $instance): ?string
    {
        return null;
    }

    /**
     * Mark cart as recovered (user returned after abandonment).
     */
    public function markRecovered(string $identifier, string $instance): void
    {
        // No-op for in-memory storage
    }

    /**
     * Clear all abandonment tracking data (checkout started, abandoned, recovery).
     */
    public function clearAbandonmentTracking(string $identifier, string $instance): void
    {
        // No-op for in-memory storage
    }

    // =========================================================================
    // Event Sourcing Methods (Phase 0.3) - Stub implementations for in-memory storage
    // =========================================================================

    /**
     * Get current event stream position for replay.
     */
    public function getEventStreamPosition(string $identifier, string $instance): int
    {
        return 0;
    }

    /**
     * Update event stream position after recording events.
     */
    public function setEventStreamPosition(string $identifier, string $instance, int $position): void
    {
        // No-op for in-memory storage
    }

    /**
     * Get aggregate schema version for migrations.
     */
    public function getAggregateVersion(string $identifier, string $instance): string
    {
        return '1.0';
    }

    /**
     * Update aggregate schema version.
     */
    public function setAggregateVersion(string $identifier, string $instance, string $version): void
    {
        // No-op for in-memory storage
    }

    /**
     * Get last snapshot timestamp.
     */
    public function getSnapshotAt(string $identifier, string $instance): ?string
    {
        return null;
    }

    /**
     * Update snapshot timestamp after taking a snapshot.
     */
    public function markSnapshotTaken(string $identifier, string $instance): void
    {
        // No-op for in-memory storage
    }

    /**
     * Get the storage key scoped to owner if set
     */
    private function scopedKey(string $identifier): string
    {
        if ($this->ownerType !== null && $this->ownerId !== null) {
            return "{$this->ownerType}:{$this->ownerId}:{$identifier}";
        }

        return $identifier;
    }

    private function incrementVersion(string $identifier, string $instance): void
    {
        $key = $this->scopedKey($identifier);
        $current = $this->versions[$key][$instance] ?? 0;
        $this->versions[$key][$instance] = $current + 1;
    }
}
