<?php

declare(strict_types=1);

namespace AIArmada\Cart\Traits;

use AIArmada\Cart\Events\CartCleared;
use AIArmada\Cart\Events\CartDestroyed;
use AIArmada\Cart\Storage\StorageInterface;

trait ManagesInstances
{
    /**
     * Get the current instance name
     */
    public function instance(): string
    {
        return $this->instanceName;
    }

    /**
     * Alias for instance() - provides consistent API naming.
     */
    public function getInstanceName(): string
    {
        return $this->instance();
    }

    /**
     * Get the storage interface
     */
    public function storage(): StorageInterface
    {
        return $this->storage;
    }

    /**
     * Set the current cart instance
     */
    public function setInstance(string $name): static
    {
        return new static(
            $this->storage,
            $this->identifier,
            $this->events,
            $name,
            $this->eventsEnabled,
            $this->conditionResolver
        );
    }

    /**
     * Check if a cart exists in storage
     *
     * @param  string|null  $identifier  Cart identifier (defaults to current)
     * @param  string|null  $instance  Instance name (defaults to current)
     */
    public function exists(?string $identifier = null, ?string $instance = null): bool
    {
        $identifier ??= $this->getIdentifier();
        $instance ??= $this->instance();

        return $this->storage->has($identifier, $instance);
    }

    /**
     * Completely remove cart from storage
     *
     * Unlike clear() which empties items/conditions/metadata but keeps the cart structure,
     * destroy() completely removes the cart from storage and dispatches CartDestroyed event.
     *
     * @param  string|null  $identifier  Cart identifier (defaults to current)
     * @param  string|null  $instance  Instance name (defaults to current)
     */
    public function destroy(?string $identifier = null, ?string $instance = null): void
    {
        $identifier ??= $this->getIdentifier();
        $instance ??= $this->instance();

        // Remove cart completely from storage
        $this->storage->forget($identifier, $instance);

        // Dispatch CartDestroyed event
        $this->dispatchEvent(new CartDestroyed($identifier, $instance));
    }

    /**
     * Get all cart instances for an identifier
     *
     * @param  string|null  $identifier  Cart identifier (defaults to current)
     * @return array<string> Array of instance names
     */
    public function instances(?string $identifier = null): array
    {
        $identifier ??= $this->getIdentifier();

        return $this->storage->getInstances($identifier);
    }

    /**
     * Clear the entire cart (items, conditions, metadata) but preserve the cart structure
     *
     * This method removes all items, conditions, and metadata from the cart while keeping
     * the cart entity itself in storage. This is useful when an admin wants to manually
     * refill a cart from scratch while maintaining the same cart identifier and instance.
     *
     * Unlike destroy() which completely removes the cart from storage, clear() resets
     * the cart to an empty state ready for new content.
     *
     * @param  string|null  $identifier  Cart identifier (defaults to current)
     * @param  string|null  $instance  Instance name (defaults to current)
     */
    public function clear(?string $identifier = null, ?string $instance = null): bool
    {
        $identifier ??= $this->getIdentifier();
        $instance ??= $this->instance();

        // Clear everything in a single storage operation
        $this->storage->clearAll($identifier, $instance);

        // Invalidate pipeline cache after clearing cart
        $this->invalidatePipelineCache();

        // Dispatch CartCleared event
        $this->dispatchEvent(new CartCleared($this));

        return true;
    }
}
