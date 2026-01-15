<?php

declare(strict_types=1);

namespace AIArmada\Cart\Traits;

use AIArmada\Cart\Events\MetadataAdded;
use AIArmada\Cart\Events\MetadataBatchAdded;
use AIArmada\Cart\Events\MetadataCleared;
use AIArmada\Cart\Events\MetadataRemoved;

trait ManagesMetadata
{
    /**
     * Store metadata for the cart
     */
    public function setMetadata(string $key, mixed $value): static
    {
        $this->storage->putMetadata($this->getIdentifier(), $this->instance(), $key, $value);

        $this->dispatchEvent(new MetadataAdded($key, $value, $this));

        return $this;
    }

    /**
     * Retrieve metadata from the cart
     */
    public function getMetadata(string $key, mixed $default = null): mixed
    {
        return $this->storage->getMetadata($this->getIdentifier(), $this->instance(), $key) ?? $default;
    }

    /**
     * Retrieve all metadata from the cart
     *
     * @return array<string, mixed>
     */
    public function getAllMetadata(): array
    {
        return $this->storage->getAllMetadata($this->getIdentifier(), $this->instance());
    }

    /**
     * Check if metadata key exists
     */
    public function hasMetadata(string $key): bool
    {
        return $this->storage->getMetadata($this->getIdentifier(), $this->instance(), $key) !== null;
    }

    /**
     * Remove metadata by setting it to null
     */
    public function removeMetadata(string $key): static
    {
        $this->storage->putMetadata($this->getIdentifier(), $this->instance(), $key, null);

        $this->dispatchEvent(new MetadataRemoved($key, $this));

        return $this;
    }

    /**
     * Set multiple metadata values at once
     *
     * @param  array<string, mixed>  $metadata
     */
    public function setMetadataBatch(array $metadata): static
    {
        if (empty($metadata)) {
            return $this;
        }

        $this->storage->putMetadataBatch($this->getIdentifier(), $this->instance(), $metadata);

        $this->dispatchEvent(new MetadataBatchAdded($metadata, $this));

        return $this;
    }

    /**
     * Clear all metadata from the cart
     */
    public function clearMetadata(): static
    {
        $this->storage->clearMetadata($this->getIdentifier(), $this->instance());

        $this->dispatchEvent(new MetadataCleared($this));

        return $this;
    }
}
