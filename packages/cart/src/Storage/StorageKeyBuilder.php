<?php

declare(strict_types=1);

namespace AIArmada\Cart\Storage;

/**
 * Centralized cache key builder for storage implementations.
 *
 * This class provides a single source of truth for all storage key formats,
 * ensuring consistency between CacheStorage and any future storage drivers
 * that need cache-based key generation.
 */
final readonly class StorageKeyBuilder
{
    public function __construct(
        private string $prefix = 'cart',
        private ?string $ownerType = null,
        private string | int | null $ownerId = null,
    ) {}

    /**
     * Get the base prefix for all keys (includes owner scope if set).
     */
    public function basePrefix(): string
    {
        if ($this->ownerType !== null && $this->ownerId !== null) {
            return "{$this->prefix}.{$this->ownerType}.{$this->ownerId}";
        }

        return $this->prefix;
    }

    /**
     * Build a key for a specific cart field.
     */
    public function forField(string $identifier, string $instance, string $field): string
    {
        return "{$this->basePrefix()}.{$identifier}.{$instance}.{$field}";
    }

    /**
     * Cart items storage key.
     */
    public function items(string $identifier, string $instance): string
    {
        return $this->forField($identifier, $instance, 'items');
    }

    /**
     * Cart conditions storage key.
     */
    public function conditions(string $identifier, string $instance): string
    {
        return $this->forField($identifier, $instance, 'conditions');
    }

    /**
     * Cart metadata key.
     */
    public function metadata(string $identifier, string $instance, string $key): string
    {
        return "{$this->basePrefix()}.{$identifier}.{$instance}.metadata.{$key}";
    }

    /**
     * Metadata keys registry (tracks which metadata keys exist).
     */
    public function metadataRegistry(string $identifier, string $instance): string
    {
        return "{$this->basePrefix()}.{$identifier}.{$instance}.metadata._keys";
    }

    /**
     * Lock key for metadata batch operations.
     */
    public function metadataLock(string $identifier, string $instance): string
    {
        return "{$this->basePrefix()}.lock.{$identifier}.{$instance}.metadata";
    }

    /**
     * Cart version key.
     */
    public function version(string $identifier, string $instance): string
    {
        return $this->forField($identifier, $instance, 'version');
    }

    /**
     * Cart UUID key.
     */
    public function id(string $identifier, string $instance): string
    {
        return $this->forField($identifier, $instance, 'id');
    }

    /**
     * Cart created_at timestamp key.
     */
    public function createdAt(string $identifier, string $instance): string
    {
        return $this->forField($identifier, $instance, 'created_at');
    }

    /**
     * Cart updated_at timestamp key.
     */
    public function updatedAt(string $identifier, string $instance): string
    {
        return $this->forField($identifier, $instance, 'updated_at');
    }

    /**
     * Cart expires_at timestamp key.
     */
    public function expiresAt(string $identifier, string $instance): string
    {
        return $this->forField($identifier, $instance, 'expires_at');
    }

    /**
     * Instance registry key (tracks all instances for an identifier).
     */
    public function instanceRegistry(string $identifier): string
    {
        return "{$this->basePrefix()}.{$identifier}._instances";
    }

    /**
     * Identifiers registry key (tracks all cart identifiers).
     */
    public function identifiersRegistry(): string
    {
        return "{$this->basePrefix()}._identifiers";
    }

    /**
     * Lock key for a given storage key.
     */
    public function lock(string $key): string
    {
        return "lock.{$key}";
    }

    /**
     * Create a new builder with the specified owner.
     */
    public function withOwner(?string $ownerType, string | int | null $ownerId): self
    {
        return new self(
            prefix: $this->prefix,
            ownerType: $ownerType,
            ownerId: $ownerId,
        );
    }
}
