<?php

declare(strict_types=1);

namespace AIArmada\Cart\Traits;

use AIArmada\Cart\Collections\CartCollection;
use AIArmada\Cart\Models\CartItem;
use Exception;
use Illuminate\Database\Eloquent\Model;

trait ManagesStorage
{
    /**
     * Get all cart items (with dynamic condition evaluation)
     */
    public function getItems(): CartCollection
    {
        // Ensure dynamic conditions are evaluated before returning items
        // This is necessary for item-level dynamic conditions to be applied
        if (method_exists($this, 'evaluateDynamicConditionsIfDirty')) {
            $this->evaluateDynamicConditionsIfDirty();
        }

        return $this->getItemsFromStorage();
    }

    /**
     * Get items directly from storage (no dynamic condition evaluation)
     *
     * Used internally to avoid infinite recursion when evaluating
     * dynamic conditions that need access to item data.
     */
    protected function getItemsFromStorage(): CartCollection
    {
        $items = $this->storage->getItems($this->getIdentifier(), $this->instance());

        if (! $items || ! is_array($items)) { // @phpstan-ignore function.alreadyNarrowedType
            return new CartCollection;
        }

        // Convert array back to CartCollection
        $collection = new CartCollection;
        foreach ($items as $itemData) {
            if (is_array($itemData) && isset($itemData['id'])) {
                // Handle associated model restoration
                $associatedModel = null;
                if (isset($itemData['associated_model'])) {
                    $associatedModel = $this->restoreAssociatedModel($itemData['associated_model']);
                }

                $item = new CartItem(
                    $itemData['id'],
                    $itemData['name'],
                    (int) $itemData['price'], // Ensure price is int (cents)
                    $itemData['quantity'],
                    $itemData['attributes'] ?? [],
                    $itemData['conditions'] ?? [],
                    $associatedModel
                );
                $collection->put($item->id, $item);
            }
        }

        return $collection;
    }

    /**
     * Check if cart is empty
     */
    public function isEmpty(): bool
    {
        return $this->getItems()->isEmpty();
    }

    /**
     * Get complete cart content (items, conditions, totals, etc.)
     * Includes all database columns for complete snapshots and auditing
     *
     * @return array<string, mixed>
     */
    public function content(): array
    {
        return [
            'id' => $this->getId(),
            'identifier' => $this->getIdentifier(),
            'instance' => $this->instanceName,
            'version' => $this->getVersion(),
            'metadata' => $this->storage->getAllMetadata($this->getIdentifier(), $this->instance()),
            'items' => $this->getItems()->toArray(),
            'conditions' => $this->getConditions()->toArray(),
            'subtotal' => $this->getRawSubtotal(),
            'total' => $this->getRawTotal(),
            'quantity' => $this->getTotalQuantity(),
            'count' => $this->countItems(), // Number of unique items, not total quantity
            'is_empty' => $this->isEmpty(),
            'created_at' => $this->storage->getCreatedAt($this->getIdentifier(), $this->instance()),
            'updated_at' => $this->storage->getUpdatedAt($this->getIdentifier(), $this->instance()),
        ];
    }

    /**
     * Get complete cart content (alias for content())
     *
     * @return array<string, mixed>
     */
    public function getContent(): array
    {
        return $this->content();
    }

    /**
     * Convert cart to array (alias for content())
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->content();
    }

    /**
     * Save cart items to storage
     */
    private function save(CartCollection $items): void
    {
        $itemsArray = $items->toArray();
        $this->storage->putItems($this->getIdentifier(), $this->instance(), $itemsArray);
    }

    /**
     * Restore associated model from array format
     */
    private function restoreAssociatedModel(mixed $associatedData): object | string | null
    {
        if (is_string($associatedData)) {
            return $associatedData;
        }

        if (is_array($associatedData) && isset($associatedData['class'])) {
            $className = $associatedData['class'];
            if (! class_exists($className)) {
                return null;
            }

            // If we have an ID and the class is an Eloquent model, fetch it
            if (isset($associatedData['id']) && is_subclass_of($className, Model::class)) {
                try {
                    return $className::find($associatedData['id']);
                } catch (Exception) {
                    // If fetch fails, return just the class name
                    return $className;
                }
            }

            // Fallback to just returning the class name
            return $className;
        }

        return null;
    }
}
