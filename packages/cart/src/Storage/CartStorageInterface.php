<?php

declare(strict_types=1);

namespace AIArmada\Cart\Storage;

use Illuminate\Database\Eloquent\Model;

/**
 * Core cart storage operations interface.
 *
 * This interface defines the essential storage operations required for cart functionality.
 * Implementations must provide basic CRUD operations for items, conditions, and metadata.
 */
interface CartStorageInterface
{
    /**
     * Set the owner for multi-tenancy scoping.
     *
     * Returns a new instance with the owner set, allowing fluent chaining.
     * When owner is set, all storage operations will be scoped to that owner.
     *
     * @param  Model|null  $owner  The owner model to scope operations to
     * @return static New instance with owner set
     */
    public function withOwner(?Model $owner): static;

    /**
     * Get the current owner type.
     *
     * @return string|null The current owner morph class or null if not set
     */
    public function getOwnerType(): ?string;

    /**
     * Get the current owner ID.
     *
     * @return string|int|null The current owner ID or null if not set
     */
    public function getOwnerId(): string | int | null;

    /**
     * Check if cart exists in storage.
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     */
    public function has(string $identifier, string $instance): bool;

    /**
     * Remove cart from storage.
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     */
    public function forget(string $identifier, string $instance): void;

    /**
     * Retrieve cart items from storage.
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     * @return array<string, mixed> Cart items array
     */
    public function getItems(string $identifier, string $instance): array;

    /**
     * Retrieve cart conditions from storage.
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     * @return array<string, mixed> Cart conditions array
     */
    public function getConditions(string $identifier, string $instance): array;

    /**
     * Store cart items in storage.
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     * @param  array<string, mixed>  $items  Cart items array
     */
    public function putItems(string $identifier, string $instance, array $items): void;

    /**
     * Store cart conditions in storage.
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     * @param  array<string, mixed>  $conditions  Cart conditions array
     */
    public function putConditions(string $identifier, string $instance, array $conditions): void;

    /**
     * Store both items and conditions in storage.
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     * @param  array<string, mixed>  $items  Cart items array
     * @param  array<string, mixed>  $conditions  Cart conditions array
     */
    public function putBoth(string $identifier, string $instance, array $items, array $conditions): void;

    /**
     * Retrieve cart metadata by key.
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     * @param  string  $key  Metadata key
     * @return mixed Metadata value or null if not found
     */
    public function getMetadata(string $identifier, string $instance, string $key): mixed;

    /**
     * Retrieve all cart metadata.
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     * @return array<string, mixed> All metadata key-value pairs
     */
    public function getAllMetadata(string $identifier, string $instance): array;

    /**
     * Store cart metadata.
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     * @param  string  $key  Metadata key
     * @param  mixed  $value  Metadata value
     */
    public function putMetadata(string $identifier, string $instance, string $key, mixed $value): void;

    /**
     * Store multiple metadata values at once.
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     * @param  array<string, mixed>  $metadata  Metadata key-value pairs
     */
    public function putMetadataBatch(string $identifier, string $instance, array $metadata): void;

    /**
     * Clear all metadata for a cart.
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     */
    public function clearMetadata(string $identifier, string $instance): void;

    /**
     * Clear all cart data (items, conditions, metadata) in a single operation.
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     */
    public function clearAll(string $identifier, string $instance): void;

    /**
     * Get cart version for change tracking.
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     * @return int|null Version number or null if cart doesn't exist
     */
    public function getVersion(string $identifier, string $instance): ?int;

    /**
     * Get cart ID (primary key) from storage.
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     * @return string|null Cart UUID or null if cart doesn't exist
     */
    public function getId(string $identifier, string $instance): ?string;

    /**
     * Get cart creation timestamp.
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     * @return string|null ISO 8601 timestamp or null if cart doesn't exist
     */
    public function getCreatedAt(string $identifier, string $instance): ?string;

    /**
     * Get cart last updated timestamp.
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     * @return string|null ISO 8601 timestamp or null if cart doesn't exist
     */
    public function getUpdatedAt(string $identifier, string $instance): ?string;

    /**
     * Get cart expiration timestamp.
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     * @return string|null ISO 8601 timestamp or null if no expiration
     */
    public function getExpiresAt(string $identifier, string $instance): ?string;

    /**
     * Check if a cart has expired.
     *
     * @param  string  $identifier  User/session identifier
     * @param  string  $instance  Cart instance name
     */
    public function isExpired(string $identifier, string $instance): bool;

    /**
     * Swap cart identifier to transfer cart ownership.
     *
     * @param  string  $oldIdentifier  The old identifier (e.g., guest session)
     * @param  string  $newIdentifier  The new identifier (e.g., user ID)
     * @param  string  $instance  Cart instance name
     * @return bool True if swap was successful
     */
    public function swapIdentifier(string $oldIdentifier, string $newIdentifier, string $instance): bool;

    /**
     * Clear all carts from storage.
     */
    public function flush(): void;

    /**
     * Get all instances for a specific identifier.
     *
     * @param  string  $identifier  User/session identifier
     * @return array<string> Array of instance names
     */
    public function getInstances(string $identifier): array;

    /**
     * Remove all instances for a specific identifier.
     *
     * @param  string  $identifier  User/session identifier
     */
    public function forgetIdentifier(string $identifier): void;
}
