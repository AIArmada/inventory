<?php

declare(strict_types=1);

namespace AIArmada\Cart\Testing;

use AIArmada\Cart\Storage\StorageInterface;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * In-memory storage implementation for unit testing.
 *
 * This storage does NOT persist data between requests - it's purely for testing
 * cart logic without database dependencies.
 *
 * @internal For testing purposes only
 */
class InMemoryStorage implements StorageInterface
{
    /**
     * @var array<string, array{
     *     id: string,
     *     items: array<string, mixed>,
     *     conditions: array<string, mixed>,
     *     metadata: array<string, mixed>,
     *     version: int,
     *     created_at: string,
     *     updated_at: string,
     *     expires_at: string|null
     * }>
     */
    private array $carts = [];

    private ?string $ownerType = null;

    private string|int|null $ownerId = null;

    public function withOwner(?Model $owner): static
    {
        $clone = clone $this;
        if ($owner !== null) {
            $clone->ownerType = $owner->getMorphClass();
            $clone->ownerId = $owner->getKey();
        } else {
            $clone->ownerType = null;
            $clone->ownerId = null;
        }

        return $clone;
    }

    public function getOwnerType(): ?string
    {
        return $this->ownerType;
    }

    public function getOwnerId(): string|int|null
    {
        return $this->ownerId;
    }

    public function has(string $identifier, string $instance): bool
    {
        return isset($this->carts[$this->key($identifier, $instance)]);
    }

    public function forget(string $identifier, string $instance): void
    {
        unset($this->carts[$this->key($identifier, $instance)]);
    }

    public function getItems(string $identifier, string $instance): array
    {
        return $this->getCart($identifier, $instance)['items'] ?? [];
    }

    public function getConditions(string $identifier, string $instance): array
    {
        return $this->getCart($identifier, $instance)['conditions'] ?? [];
    }

    public function putItems(string $identifier, string $instance, array $items): void
    {
        $this->ensureCart($identifier, $instance);
        $key = $this->key($identifier, $instance);
        $this->carts[$key]['items'] = $items;
        $this->carts[$key]['version']++;
        $this->carts[$key]['updated_at'] = CarbonImmutable::now()->toIso8601String();
    }

    public function putConditions(string $identifier, string $instance, array $conditions): void
    {
        $this->ensureCart($identifier, $instance);
        $key = $this->key($identifier, $instance);
        $this->carts[$key]['conditions'] = $conditions;
        $this->carts[$key]['version']++;
        $this->carts[$key]['updated_at'] = CarbonImmutable::now()->toIso8601String();
    }

    public function putBoth(string $identifier, string $instance, array $items, array $conditions): void
    {
        $this->ensureCart($identifier, $instance);
        $key = $this->key($identifier, $instance);
        $this->carts[$key]['items'] = $items;
        $this->carts[$key]['conditions'] = $conditions;
        $this->carts[$key]['version']++;
        $this->carts[$key]['updated_at'] = CarbonImmutable::now()->toIso8601String();
    }

    public function getMetadata(string $identifier, string $instance, string $key): mixed
    {
        return $this->getCart($identifier, $instance)['metadata'][$key] ?? null;
    }

    public function getAllMetadata(string $identifier, string $instance): array
    {
        return $this->getCart($identifier, $instance)['metadata'] ?? [];
    }

    public function putMetadata(string $identifier, string $instance, string $key, mixed $value): void
    {
        $this->ensureCart($identifier, $instance);
        $cartKey = $this->key($identifier, $instance);
        $this->carts[$cartKey]['metadata'][$key] = $value;
        $this->carts[$cartKey]['version']++;
        $this->carts[$cartKey]['updated_at'] = CarbonImmutable::now()->toIso8601String();
    }

    public function putMetadataBatch(string $identifier, string $instance, array $metadata): void
    {
        $this->ensureCart($identifier, $instance);
        $key = $this->key($identifier, $instance);
        $this->carts[$key]['metadata'] = array_merge(
            $this->carts[$key]['metadata'],
            $metadata
        );
        $this->carts[$key]['version']++;
        $this->carts[$key]['updated_at'] = CarbonImmutable::now()->toIso8601String();
    }

    public function clearMetadata(string $identifier, string $instance): void
    {
        if ($this->has($identifier, $instance)) {
            $key = $this->key($identifier, $instance);
            $this->carts[$key]['metadata'] = [];
            $this->carts[$key]['version']++;
            $this->carts[$key]['updated_at'] = CarbonImmutable::now()->toIso8601String();
        }
    }

    public function clearAll(string $identifier, string $instance): void
    {
        $this->forget($identifier, $instance);
    }

    public function getVersion(string $identifier, string $instance): ?int
    {
        return $this->getCart($identifier, $instance)['version'] ?? null;
    }

    public function getId(string $identifier, string $instance): ?string
    {
        return $this->getCart($identifier, $instance)['id'] ?? null;
    }

    public function getCreatedAt(string $identifier, string $instance): ?string
    {
        return $this->getCart($identifier, $instance)['created_at'] ?? null;
    }

    public function getUpdatedAt(string $identifier, string $instance): ?string
    {
        return $this->getCart($identifier, $instance)['updated_at'] ?? null;
    }

    public function getExpiresAt(string $identifier, string $instance): ?string
    {
        return $this->getCart($identifier, $instance)['expires_at'] ?? null;
    }

    public function isExpired(string $identifier, string $instance): bool
    {
        $expiresAt = $this->getExpiresAt($identifier, $instance);
        if ($expiresAt === null) {
            return false;
        }

        return CarbonImmutable::parse($expiresAt)->isPast();
    }

    public function swapIdentifier(string $oldIdentifier, string $newIdentifier, string $instance): bool
    {
        $oldKey = $this->key($oldIdentifier, $instance);
        $newKey = $this->key($newIdentifier, $instance);

        if (!isset($this->carts[$oldKey])) {
            return false;
        }

        $this->carts[$newKey] = $this->carts[$oldKey];
        unset($this->carts[$oldKey]);

        return true;
    }

    public function flush(): void
    {
        $this->carts = [];
    }

    public function getInstances(string $identifier): array
    {
        $instances = [];
        foreach (array_keys($this->carts) as $key) {
            if (str_starts_with((string) $key, $identifier . ':')) {
                $instances[] = substr((string) $key, strlen($identifier) + 1);
            }
        }

        return $instances;
    }

    public function forgetIdentifier(string $identifier): void
    {
        foreach (array_keys($this->carts) as $key) {
            if (str_starts_with((string) $key, $identifier . ':')) {
                unset($this->carts[$key]);
            }
        }
    }

    private function key(string $identifier, string $instance): string
    {
        return $identifier . ':' . $instance;
    }

    /**
     * @return array<string, mixed>
     */
    private function getCart(string $identifier, string $instance): array
    {
        return $this->carts[$this->key($identifier, $instance)] ?? [];
    }

    private function ensureCart(string $identifier, string $instance): void
    {
        $key = $this->key($identifier, $instance);
        if (!isset($this->carts[$key])) {
            $now = CarbonImmutable::now()->toIso8601String();
            $this->carts[$key] = [
                'id' => Str::uuid()->toString(),
                'items' => [],
                'conditions' => [],
                'metadata' => [],
                'version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'expires_at' => null,
            ];
        }
    }
}
