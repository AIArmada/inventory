<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Support;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Cashier\Checkout\CartCheckoutBuilder;
use AIArmada\Cashier\GatewayManager;

/**
 * Cart manager decorator that adds payment/checkout capabilities.
 *
 * This proxy extends the cart manager with checkout functionality:
 * - `checkout()` - Create a checkout builder for the current cart
 * - `checkoutWithGateway()` - Checkout using specific gateway
 */
final class CartManagerWithPayment implements CartManagerInterface
{
    private CartManagerInterface $cart;

    private function __construct(CartManagerInterface $cart)
    {
        $this->cart = $cart;
    }

    /**
     * Forward any method calls to the underlying cart manager.
     *
     * @param  array<mixed>  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->cart->{$method}(...$arguments);
    }

    public static function fromCartManager(CartManagerInterface $cart): self
    {
        if ($cart instanceof self) {
            return $cart;
        }

        return new self($cart);
    }

    /**
     * Create a checkout builder for the current cart.
     */
    public function checkout(?string $identifier = null, ?string $gateway = null): CartCheckoutBuilder
    {
        $cart = $this->get($identifier);

        /** @var GatewayManager $gatewayManager */
        $gatewayManager = app(GatewayManager::class);

        $gatewayInstance = $gateway
            ? $gatewayManager->gateway($gateway)
            : $gatewayManager->gateway();

        return new CartCheckoutBuilder($cart, $gatewayInstance);
    }

    /**
     * Create a checkout builder with a specific gateway.
     */
    public function checkoutWithGateway(string $gateway, ?string $identifier = null): CartCheckoutBuilder
    {
        return $this->checkout($identifier, $gateway);
    }

    // ========================================
    // Delegate all CartManagerInterface methods
    // ========================================

    public function get(?string $identifier = null): Cart
    {
        return $this->cart->get($identifier);
    }

    public function has(?string $identifier = null): bool
    {
        return $this->cart->has($identifier);
    }

    public function create(?string $identifier = null): Cart
    {
        return $this->cart->create($identifier);
    }

    public function add(
        string | int $id,
        string $name,
        int $price,
        int $quantity = 1,
        array $attributes = [],
        ?array $conditions = null,
        ?string $identifier = null
    ): Cart {
        return $this->cart->add($id, $name, $price, $quantity, $attributes, $conditions, $identifier);
    }

    public function update(
        string | int $id,
        int | array $quantityOrAttributes,
        ?string $identifier = null
    ): Cart {
        return $this->cart->update($id, $quantityOrAttributes, $identifier);
    }

    public function remove(string | int $id, ?string $identifier = null): Cart
    {
        return $this->cart->remove($id, $identifier);
    }

    public function clear(?string $identifier = null): Cart
    {
        return $this->cart->clear($identifier);
    }

    public function destroy(?string $identifier = null): void
    {
        $this->cart->destroy($identifier);
    }

    public function items(?string $identifier = null): \AIArmada\Cart\Collections\CartCollection
    {
        return $this->cart->items($identifier);
    }

    public function isEmpty(?string $identifier = null): bool
    {
        return $this->cart->isEmpty($identifier);
    }

    public function count(?string $identifier = null): int
    {
        return $this->cart->count($identifier);
    }

    public function totalQuantity(?string $identifier = null): int
    {
        return $this->cart->totalQuantity($identifier);
    }

    public function subtotal(?string $identifier = null): int
    {
        return $this->cart->subtotal($identifier);
    }

    public function total(?string $identifier = null): int
    {
        return $this->cart->total($identifier);
    }

    public function setMetadata(string $key, mixed $value, ?string $identifier = null): Cart
    {
        return $this->cart->setMetadata($key, $value, $identifier);
    }

    public function getMetadata(?string $key = null, ?string $identifier = null): mixed
    {
        return $this->cart->getMetadata($key, $identifier);
    }

    public function removeMetadata(string $key, ?string $identifier = null): Cart
    {
        return $this->cart->removeMetadata($key, $identifier);
    }

    public function addCondition(mixed $condition, ?string $identifier = null): Cart
    {
        return $this->cart->addCondition($condition, $identifier);
    }

    public function removeCondition(string $name, ?string $identifier = null): Cart
    {
        return $this->cart->removeCondition($name, $identifier);
    }

    public function conditions(?string $identifier = null): \AIArmada\Cart\Collections\CartConditionCollection
    {
        return $this->cart->conditions($identifier);
    }

    public function addItemCondition(
        string | int $id,
        mixed $condition,
        ?string $identifier = null
    ): Cart {
        return $this->cart->addItemCondition($id, $condition, $identifier);
    }

    public function removeItemCondition(
        string | int $id,
        string $conditionName,
        ?string $identifier = null
    ): Cart {
        return $this->cart->removeItemCondition($id, $conditionName, $identifier);
    }

    public function mergeFromSession(?string $identifier = null): Cart
    {
        return $this->cart->mergeFromSession($identifier);
    }

    public function persist(?string $identifier = null): Cart
    {
        return $this->cart->persist($identifier);
    }

    // ========================================
    // Additional CartManagerInterface methods
    // ========================================

    public function getCurrentCart(): Cart
    {
        return $this->cart->getCurrentCart();
    }

    public function getCartInstance(string $name, ?string $identifier = null): Cart
    {
        return $this->cart->getCartInstance($name, $identifier);
    }

    public function instance(): string
    {
        return $this->cart->instance();
    }

    public function setInstance(string $name): static
    {
        $this->cart->setInstance($name);

        return $this;
    }

    public function setIdentifier(string $identifier): static
    {
        $this->cart->setIdentifier($identifier);

        return $this;
    }

    public function forgetIdentifier(): static
    {
        $this->cart->forgetIdentifier();

        return $this;
    }

    public function forOwner(\Illuminate\Database\Eloquent\Model $owner): static
    {
        return new self($this->cart->forOwner($owner));
    }

    public function getOwnerType(): ?string
    {
        return $this->cart->getOwnerType();
    }

    public function getOwnerId(): string | int | null
    {
        return $this->cart->getOwnerId();
    }

    public function session(?string $sessionKey = null): \AIArmada\Cart\Storage\StorageInterface
    {
        return $this->cart->session($sessionKey);
    }

    public function getById(string $uuid): ?Cart
    {
        return $this->cart->getById($uuid);
    }

    public function swap(string $oldIdentifier, string $newIdentifier, string $instance = 'default'): bool
    {
        return $this->cart->swap($oldIdentifier, $newIdentifier, $instance);
    }
}
