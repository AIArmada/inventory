<?php

declare(strict_types=1);

namespace Tests\Support\Cart;

use AIArmada\Cart\Storage\StorageInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * In-memory storage implementation for testing purposes.
 * 
 * This class was moved from packages/cart/src/Testing to tests/Support
 * as part of the cart package simplification.
 */
final class InMemoryStorage implements StorageInterface
{
    /** @var array<string, array<string, array<string, mixed>>> */
    private array $data = [];

    private ?string $ownerType = null;

    private string|int|null $ownerId = null;

    public function has(string $identifier, string $instance): bool
    {
        return isset($this->data[$identifier][$instance]);
    }

    public function forget(string $identifier, string $instance): void
    {
        unset($this->data[$identifier][$instance]);
    }

    public function flush(): void
    {
        $this->data = [];
    }

    /**
     * @return array<string>
     */
    public function getInstances(string $identifier): array
    {
        return array_keys($this->data[$identifier] ?? []);
    }

    public function forgetIdentifier(string $identifier): void
    {
        unset($this->data[$identifier]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getItems(string $identifier, string $instance): array
    {
        return $this->data[$identifier][$instance]['items'] ?? [];
    }

    /**
     * @param array<string, array<string, mixed>> $items
     */
    public function putItems(string $identifier, string $instance, array $items): void
    {
        $this->data[$identifier][$instance]['items'] = $items;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getConditions(string $identifier, string $instance): array
    {
        return $this->data[$identifier][$instance]['conditions'] ?? [];
    }

    /**
     * @param array<string, array<string, mixed>> $conditions
     */
    public function putConditions(string $identifier, string $instance, array $conditions): void
    {
        $this->data[$identifier][$instance]['conditions'] = $conditions;
    }

    /**
     * @param array<string, array<string, mixed>> $items
     * @param array<string, array<string, mixed>> $conditions
     */
    public function putBoth(string $identifier, string $instance, array $items, array $conditions): void
    {
        $this->data[$identifier][$instance]['items'] = $items;
        $this->data[$identifier][$instance]['conditions'] = $conditions;
    }

    public function putMetadata(string $identifier, string $instance, string $key, mixed $value): void
    {
        $this->data[$identifier][$instance]['metadata'][$key] = $value;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function putMetadataBatch(string $identifier, string $instance, array $metadata): void
    {
        $this->data[$identifier][$instance]['metadata'] = array_merge(
            $this->data[$identifier][$instance]['metadata'] ?? [],
            $metadata
        );
    }

    public function getMetadata(string $identifier, string $instance, string $key): mixed
    {
        return $this->data[$identifier][$instance]['metadata'][$key] ?? null;
    }

    public function clearMetadata(string $identifier, string $instance): void
    {
        $this->data[$identifier][$instance]['metadata'] = [];
    }

    public function clearAll(string $identifier, string $instance): void
    {
        $this->data[$identifier][$instance]['items'] = [];
        $this->data[$identifier][$instance]['conditions'] = [];
        $this->data[$identifier][$instance]['metadata'] = [];
    }

    public function swapIdentifier(string $oldIdentifier, string $newIdentifier, string $instance): bool
    {
        if (isset($this->data[$oldIdentifier][$instance])) {
            $this->data[$newIdentifier][$instance] = $this->data[$oldIdentifier][$instance];
            unset($this->data[$oldIdentifier][$instance]);

            return true;
        }

        return false;
    }

    public function getVersion(string $identifier, string $instance): ?int
    {
        return null;
    }

    public function getId(string $identifier, string $instance): ?string
    {
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAllMetadata(string $identifier, string $instance): array
    {
        return $this->data[$identifier][$instance]['metadata'] ?? [];
    }

    public function getCreatedAt(string $identifier, string $instance): ?string
    {
        return null;
    }

    public function getUpdatedAt(string $identifier, string $instance): ?string
    {
        return null;
    }

    public function withTenantId(?string $tenantId): static
    {
        return $this;
    }

    public function getTenantId(): ?string
    {
        return null;
    }

    public function withOwner(?Model $owner): static
    {
        if ($owner === null) {
            $this->ownerType = null;
            $this->ownerId = null;
        } else {
            $this->ownerType = $owner::class;
            $this->ownerId = $owner->getKey();
        }

        return $this;
    }

    public function getOwnerType(): ?string
    {
        return $this->ownerType;
    }

    public function getOwnerId(): string|int|null
    {
        return $this->ownerId;
    }

    public function getOwner(): ?Model
    {
        return null;
    }

    public function getExpiresAt(string $identifier, string $instance): ?string
    {
        return null;
    }

    public function isExpired(string $identifier, string $instance): bool
    {
        return false;
    }
}
