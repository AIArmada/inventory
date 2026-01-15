<?php

declare(strict_types=1);

namespace AIArmada\Cart\Traits;

use AIArmada\Cart\Collections\CartCollection;
use AIArmada\Cart\Enums\EmptyCartBehavior;
use AIArmada\Cart\Events\CartCreated;
use AIArmada\Cart\Events\ItemAdded;
use AIArmada\Cart\Events\ItemRemoved;
use AIArmada\Cart\Events\ItemUpdated;
use AIArmada\Cart\Exceptions\InvalidCartItemException;
use AIArmada\Cart\Exceptions\UnknownModelException;
use AIArmada\Cart\Models\CartItem;
use AIArmada\CommerceSupport\Support\MoneyNormalizer;

trait ManagesItems
{
    /**
     * Add item(s) to the cart
     *
     * @param  string|int|array<array<string, mixed>>  $id
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>|object|null  $conditions
     */
    public function add(
        string | int | array $id,
        ?string $name = null,
        float | int | string | null $price = null,
        int $quantity = 1,
        array $attributes = [],
        array | object | null $conditions = null,
        string | object | null $associatedModel = null
    ): CartItem | CartCollection {
        // Handle array input - distinguish between single item and multiple items
        if (is_array($id)) {
            // If array has 'id' key, it's a single item array
            // Otherwise, it's an array of items
            if (isset($id['id'])) {
                // Single item array: ['id' => '...', 'name' => '...', ...]
                /** @var string|null $name */
                $name = isset($id['name']) && is_string($id['name']) ? $id['name'] : null;
                /** @var float|int|string|null $price */
                $price = $id['price'] ?? null;
                /** @var int $quantity */
                $quantity = isset($id['quantity']) && is_int($id['quantity']) ? $id['quantity'] : 1;
                /** @var array<string, mixed> $attributes */
                $attributes = isset($id['attributes']) && is_array($id['attributes']) ? $id['attributes'] : [];
                /** @var array<string, mixed>|object|null $conditions */
                $conditions = $id['conditions'] ?? null;
                /** @var object|string|null $associatedModel */
                $associatedModel = $id['associated_model'] ?? null;

                /** @var string|int $itemId */
                $itemId = $id['id'];

                return $this->addItemInternal(
                    $itemId,
                    $name,
                    $price,
                    $quantity,
                    $attributes,
                    $conditions,
                    $associatedModel
                );
            }

            // Multiple items: [['id' => '...'], ['id' => '...']]
            return $this->addMultiple($id);
        }

        return $this->addItemInternal($id, $name, $price, $quantity, $attributes, $conditions, $associatedModel);
    }

    /**
     * Update cart item
     *
     * @param  array<string, mixed>  $data
     */
    public function update(string | int $id, array $data): ?CartItem
    {
        // Normalize ID to string for consistent handling
        $id = (string) $id;

        $cartItems = $this->getItems();

        if (! $cartItems->has($id)) {
            return null;
        }

        $item = $cartItems->get($id);
        assert($item !== null, 'Item should exist since we checked has()');

        // Handle quantity updates
        if (isset($data['quantity'])) {
            $quantity = $data['quantity'];

            if (is_array($quantity)) {
                // Absolute quantity update
                $newQuantity = $quantity['value'] ?? 0;
            } else {
                // Relative quantity update (default behavior)
                $newQuantity = $item->quantity + $quantity;
            }

            // Check for removal BEFORE creating new CartItem to avoid exceptions
            if ($newQuantity <= 0) {
                return $this->remove($id);
            }

            $item = $item->setQuantity($newQuantity);
        }

        // Update other properties
        foreach (['name', 'price', 'attributes'] as $property) {
            if (isset($data[$property])) {
                $method = 'set' . ucfirst($property);
                $value = $property === 'price' ? $this->normalizePrice($data[$property]) : $data[$property];
                $item = $item->$method($value);
            }
        }

        // Update cart
        $cartItems->put($id, $item);
        $this->save($cartItems);

        // Invalidate pipeline cache after cart modification
        $this->invalidatePipelineCacheIfEnabled();

        $this->dispatchEvent(new ItemUpdated($item, $this));

        // Mark dynamic conditions dirty - evaluation deferred until totals requested
        $this->markDynamicConditionsDirty();

        return $item;
    }

    /**
     * Remove item from cart
     */
    public function remove(string | int $id): ?CartItem
    {
        // Normalize ID to string for consistent handling
        $id = (string) $id;

        $cartItems = $this->getItems();

        if (! $cartItems->has($id)) {
            return null;
        }

        $item = $cartItems->get($id);
        assert($item !== null, 'Item should exist since we checked has()');
        $cartItems->forget($id);
        $this->save($cartItems);

        // Invalidate pipeline cache after cart modification
        $this->invalidatePipelineCacheIfEnabled();

        $this->dispatchEvent(new ItemRemoved($item, $this));

        // Mark dynamic conditions dirty - evaluation deferred until totals requested
        $this->markDynamicConditionsDirty();

        // Handle empty cart based on configured behavior
        if ($cartItems->isEmpty()) {
            $this->handleEmptyCart();
        }

        return $item;
    }

    /**
     * Get cart item by ID
     */
    public function get(string | int $id): ?CartItem
    {
        // Ensure dynamic conditions are evaluated before returning item
        // This is necessary for item-level dynamic conditions to be applied
        $this->evaluateDynamicConditionsIfDirty();

        // Normalize ID to string for consistent handling
        $id = (string) $id;

        return $this->getItems()->get($id);
    }

    /**
     * Check if cart has item with given ID
     */
    public function has(string | int $id): bool
    {
        // Normalize ID to string for consistent handling
        $id = (string) $id;

        return $this->getItems()->has($id);
    }

    /**
     * Search cart content with callback
     */
    public function search(callable $callback): CartCollection
    {
        // Ensure dynamic conditions are evaluated before searching
        $this->evaluateDynamicConditionsIfDirty();

        return $this->getItems()->filter($callback);
    }

    /**
     * Internal method to add a single item (bypasses rate limit check for recursive calls).
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>|object|null  $conditions
     */
    private function addItemInternal(
        string | int $id,
        ?string $name,
        float | int | string | null $price,
        int $quantity,
        array $attributes,
        array | object | null $conditions,
        string | object | null $associatedModel
    ): CartItem {
        // Normalize ID to string for consistent handling
        $id = (string) $id;

        // Create cart item
        $item = $this->createCartItem([
            'id' => $id,
            'name' => $name,
            'price' => $this->normalizePrice($price),
            'quantity' => $quantity,
            'attributes' => $attributes,
            'conditions' => $conditions,
            'associated_model' => $associatedModel,
        ]);

        // Check if item already exists in cart
        $cartItems = $this->getItems();
        $isFirstItem = $cartItems->isEmpty();

        if ($cartItems->has($id)) {
            // Update existing item quantity
            $existingItem = $cartItems->get($id);
            assert($existingItem !== null, 'Item should exist since we checked has()');
            $item = $item->setQuantity($existingItem->quantity + $quantity);
        }

        // Store in cart
        $cartItems->put($id, $item);
        $this->save($cartItems);

        // Invalidate pipeline cache after cart modification
        $this->invalidatePipelineCacheIfEnabled();

        // Dispatch CartCreated event only when adding the first item to an empty cart
        if ($isFirstItem) {
            $this->dispatchEvent(new CartCreated($this));
        }

        // Dispatch ItemAdded event
        $this->dispatchEvent(new ItemAdded($item, $this));

        // Mark dynamic conditions dirty - evaluation deferred until totals requested
        $this->markDynamicConditionsDirty();

        return $item;
    }

    /**
     * Invalidate pipeline cache if the trait is available.
     */
    private function invalidatePipelineCacheIfEnabled(): void
    {
        $this->invalidatePipelineCache();
    }

    /**
     * Handle cart when it becomes empty based on configured behavior.
     */
    private function handleEmptyCart(): void
    {
        $behavior = EmptyCartBehavior::tryFrom(
            config('cart.empty_cart_behavior', 'destroy')
        ) ?? EmptyCartBehavior::Destroy;

        match ($behavior) {
            EmptyCartBehavior::Destroy => $this->destroy(),
            EmptyCartBehavior::Clear => $this->clear(),
            EmptyCartBehavior::Preserve => null, // Do nothing, keep conditions and metadata
        };
    }

    /**
     * Add multiple items to cart
     *
     * @param  array<array<string, mixed>>  $items
     */
    private function addMultiple(array $items): CartCollection
    {
        $cartItems = new CartCollection;

        foreach ($items as $item) {
            $cartItem = $this->addItemInternal(
                $item['id'],
                $item['name'] ?? null,
                $item['price'] ?? null,
                $item['quantity'] ?? 1,
                $item['attributes'] ?? [],
                $item['conditions'] ?? null,
                $item['associated_model'] ?? null
            );

            $cartItems->put($cartItem->id, $cartItem);
        }

        return $cartItems;
    }

    /**
     * Create a cart item from array data
     *
     * @param  array<string, mixed>  $data
     */
    private function createCartItem(array $data): CartItem
    {
        $this->validateCartItem($data);

        return new CartItem(
            id: $data['id'],
            name: $data['name'],
            price: $data['price'],
            quantity: $data['quantity'],
            attributes: $data['attributes'] ?? [],
            conditions: $data['conditions'] ?? [],
            associatedModel: $data['associated_model'] ?? null
        );
    }

    /**
     * Validate cart item data
     *
     * @param  array<string, mixed>  $data
     */
    private function validateCartItem(array $data): void
    {
        if (empty($data['id'])) {
            throw new InvalidCartItemException('Cart item ID is required');
        }

        if (empty($data['name'])) {
            throw new InvalidCartItemException('Cart item name is required');
        }

        if (! is_numeric($data['price']) || $data['price'] < 0) {
            throw new InvalidCartItemException('Cart item price must be a positive number');
        }

        if (! is_int($data['quantity']) || $data['quantity'] < 1) {
            throw new InvalidCartItemException('Cart item quantity must be a positive integer');
        }

        // Validate associated model if provided
        if (isset($data['associated_model']) && is_string($data['associated_model'])) {
            if (! class_exists($data['associated_model'])) {
                throw new UnknownModelException("Model {$data['associated_model']} does not exist");
            }
        }
    }

    /**
     * Normalize price input to cents (integer) using the centralized MoneyNormalizer.
     */
    private function normalizePrice(float | int | string | null $price): int
    {
        return MoneyNormalizer::toCents($price);
    }
}
