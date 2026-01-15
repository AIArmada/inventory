<?php

declare(strict_types=1);

namespace AIArmada\Cart\Models;

use AIArmada\Cart\Collections\CartConditionCollection;
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Exceptions\InvalidCartItemException;
use AIArmada\CommerceSupport\Contracts\Payment\LineItemInterface;
use AIArmada\CommerceSupport\Support\MoneyNormalizer;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Collection;
use JsonSerializable;

final readonly class CartItem implements Arrayable, Jsonable, JsonSerializable, LineItemInterface
{
    use Traits\AssociatedModelTrait;
    use Traits\AttributeTrait;
    use Traits\ConditionTrait;
    use Traits\MoneyTrait;
    use Traits\SerializationTrait;
    use Traits\ValidationTrait;

    public string $id;

    public CartConditionCollection $conditions;

    /** @var Collection<string, mixed> */
    public Collection $attributes;

    public int $price;

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>|Collection<string, CartCondition>  $conditions
     */
    public function __construct(
        string | int $id,
        public string $name,
        int | float | string $price,
        public int $quantity,
        array $attributes = [],
        array | Collection $conditions = [],
        public string | object | null $associatedModel = null
    ) {
        // Normalize ID to string for consistent handling
        $this->id = (string) $id;

        $this->attributes = new Collection($attributes);
        $this->conditions = $this->normalizeConditions($conditions);

        // Store price as integer cents
        $this->price = $this->normalizeToInt($price);

        $this->validateCartItem();
    }

    /**
     * Set item quantity
     */
    public function setQuantity(int $quantity): static
    {
        if ($quantity < 1) {
            throw new InvalidCartItemException('Quantity must be at least 1');
        }

        return new self(
            $this->id,
            $this->name,
            $this->price,
            $quantity,
            $this->attributes->toArray(),
            $this->conditions->toArray(),
            $this->associatedModel
        );
    }

    /**
     * Check if two cart items are equal
     */
    public function equals(self $other): bool
    {
        return $this->id === $other->id;
    }

    /**
     * Create a CartItem from an array representation.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        return new static(
            id: $data['id'] ?? '',
            name: $data['name'] ?? '',
            price: (int) ($data['price'] ?? 0),
            quantity: (int) ($data['quantity'] ?? 1),
            attributes: $data['attributes'] ?? [],
            conditions: $data['conditions'] ?? [],
            associatedModel: $data['associated_model'] ?? null
        );
    }

    /**
     * Create a copy of the item with modified properties
     *
     * @param  array<string, mixed>  $attributes
     */
    public function with(array $attributes): static
    {
        return new static(
            $attributes['id'] ?? $this->id,
            $attributes['name'] ?? $this->name,
            $attributes['price'] ?? $this->price,
            $attributes['quantity'] ?? $this->quantity,
            $attributes['attributes'] ?? $this->attributes->toArray(),
            $attributes['conditions'] ?? $this->conditions->toArray(),
            $attributes['associated_model'] ?? $this->associatedModel
        );
    }

    /**
     * Create a copy with multiple property changes in a single operation.
     *
     * This is more efficient than chaining multiple setX() calls,
     * as it creates only one new instance instead of N instances.
     *
     * @param  array{name?: string, price?: int|float|string, quantity?: int, attributes?: array<string, mixed>}  $changes
     */
    public function withBatch(array $changes): static
    {
        $name = $this->name;
        $price = $this->price;
        $quantity = $this->quantity;
        $attributes = $this->attributes->toArray();

        if (isset($changes['name'])) {
            $name = $changes['name'];
        }

        if (isset($changes['price'])) {
            $price = $this->normalizeToInt($changes['price']);
        }

        if (isset($changes['quantity'])) {
            $quantity = $changes['quantity'];
        }

        if (isset($changes['attributes'])) {
            $attributes = array_merge($attributes, $changes['attributes']);
        }

        return new static(
            $this->id,
            $name,
            $price,
            $quantity,
            $attributes,
            $this->conditions->toArray(),
            $this->associatedModel
        );
    }

    /**
     * Normalize price to integer cents using the centralized MoneyNormalizer.
     */
    private function normalizeToInt(int | float | string $price): int
    {
        return MoneyNormalizer::toCents($price);
    }
}
