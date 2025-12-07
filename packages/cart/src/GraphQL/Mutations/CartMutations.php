<?php

declare(strict_types=1);

namespace AIArmada\Cart\GraphQL\Mutations;

use AIArmada\Cart\CartManager;
use AIArmada\Cart\Checkout\CheckoutSaga;
use AIArmada\Cart\Commands\AddItemCommand;
use AIArmada\Cart\Commands\ApplyConditionCommand;
use AIArmada\Cart\Commands\CartCommandBus;
use AIArmada\Cart\Commands\ClearCartCommand;
use AIArmada\Cart\Commands\RemoveItemCommand;
use AIArmada\Cart\Commands\UpdateItemQuantityCommand;
use Throwable;

/**
 * GraphQL Mutation resolvers for Cart.
 *
 * Provides mutation resolvers that can be used with Lighthouse or other GraphQL libraries.
 */
final class CartMutations
{
    public function __construct(
        private readonly CartManager $cartManager,
        private readonly CartCommandBus $commandBus
    ) {}

    /**
     * Get the mutations SDL.
     */
    public static function sdl(): string
    {
        return <<<'GRAPHQL'
extend type Mutation {
    "Add an item to cart"
    addToCart(input: AddToCartInput!): CartMutationResult!
    
    "Update item quantity"
    updateCartItem(input: UpdateCartItemInput!): CartMutationResult!
    
    "Remove an item from cart"
    removeFromCart(identifier: String!, instance: String = "default", itemId: ID!): CartMutationResult!
    
    "Apply a condition to cart"
    applyCondition(input: ApplyConditionInput!): CartMutationResult!
    
    "Remove a condition from cart"
    removeCondition(identifier: String!, instance: String = "default", conditionName: String!): CartMutationResult!
    
    "Clear all items from cart"
    clearCart(identifier: String!, instance: String = "default"): CartMutationResult!
    
    "Checkout cart"
    checkout(input: CheckoutInput!): CheckoutResult!
}

input AddToCartInput {
    identifier: String!
    instance: String = "default"
    itemId: ID!
    name: String!
    priceInCents: Int!
    quantity: Int = 1
    attributes: JSON
}

input UpdateCartItemInput {
    identifier: String!
    instance: String = "default"
    itemId: ID!
    quantity: Int!
}

input ApplyConditionInput {
    identifier: String!
    instance: String = "default"
    name: String!
    type: String!
    value: String!
    target: String = "cart@cart_subtotal/aggregate"
    order: Int = 0
}

input CheckoutInput {
    identifier: String!
    instance: String = "default"
    paymentMethod: String
    shippingAddress: JSON
    billingAddress: JSON
    metadata: JSON
}

type CartMutationResult {
    success: Boolean!
    cart: Cart
    errors: [CartError!]
}

type CheckoutResult {
    success: Boolean!
    orderId: ID
    orderNumber: String
    paymentUrl: String
    errors: [CartError!]
}

type CartError {
    code: String!
    message: String!
    field: String
}
GRAPHQL;
    }

    /**
     * Add item to cart.
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function addToCart(mixed $root, array $args): array
    {
        $input = $args['input'];

        try {
            $command = AddItemCommand::fromArray([
                'identifier' => $input['identifier'],
                'instance' => $input['instance'] ?? 'default',
                'item_id' => $input['itemId'],
                'item_name' => $input['name'],
                'price_in_cents' => $input['priceInCents'],
                'quantity' => $input['quantity'] ?? 1,
                'attributes' => $input['attributes'] ?? [],
            ]);

            $this->commandBus->dispatch($command);

            $cart = $this->cartManager
                ->setIdentifier($input['identifier'])
                ->setInstance($input['instance'] ?? 'default')
                ->getCurrentCart();

            return [
                'success' => true,
                'cart' => $this->transformCart($cart),
                'errors' => [],
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'cart' => null,
                'errors' => [[
                    'code' => 'ADD_ITEM_FAILED',
                    'message' => $e->getMessage(),
                    'field' => null,
                ]],
            ];
        }
    }

    /**
     * Update cart item quantity.
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function updateCartItem(mixed $root, array $args): array
    {
        $input = $args['input'];

        try {
            $command = UpdateItemQuantityCommand::fromArray([
                'identifier' => $input['identifier'],
                'instance' => $input['instance'] ?? 'default',
                'item_id' => $input['itemId'],
                'new_quantity' => $input['quantity'],
            ]);

            $this->commandBus->dispatch($command);

            $cart = $this->cartManager
                ->setIdentifier($input['identifier'])
                ->setInstance($input['instance'] ?? 'default')
                ->getCurrentCart();

            return [
                'success' => true,
                'cart' => $this->transformCart($cart),
                'errors' => [],
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'cart' => null,
                'errors' => [[
                    'code' => 'UPDATE_ITEM_FAILED',
                    'message' => $e->getMessage(),
                    'field' => null,
                ]],
            ];
        }
    }

    /**
     * Remove item from cart.
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function removeFromCart(mixed $root, array $args): array
    {
        try {
            $command = RemoveItemCommand::fromArray([
                'identifier' => $args['identifier'],
                'instance' => $args['instance'] ?? 'default',
                'item_id' => $args['itemId'],
            ]);

            $this->commandBus->dispatch($command);

            $cart = $this->cartManager
                ->setIdentifier($args['identifier'])
                ->setInstance($args['instance'] ?? 'default')
                ->getCurrentCart();

            return [
                'success' => true,
                'cart' => $this->transformCart($cart),
                'errors' => [],
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'cart' => null,
                'errors' => [[
                    'code' => 'REMOVE_ITEM_FAILED',
                    'message' => $e->getMessage(),
                    'field' => null,
                ]],
            ];
        }
    }

    /**
     * Apply condition to cart.
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function applyCondition(mixed $root, array $args): array
    {
        $input = $args['input'];

        try {
            $command = ApplyConditionCommand::fromArray([
                'identifier' => $input['identifier'],
                'instance' => $input['instance'] ?? 'default',
                'condition_name' => $input['name'],
                'condition_type' => $input['type'],
                'value' => $input['value'],
                'target' => $input['target'] ?? 'cart@cart_subtotal/aggregate',
                'order' => $input['order'] ?? 0,
            ]);

            $this->commandBus->dispatch($command);

            $cart = $this->cartManager
                ->setIdentifier($input['identifier'])
                ->setInstance($input['instance'] ?? 'default')
                ->getCurrentCart();

            return [
                'success' => true,
                'cart' => $this->transformCart($cart),
                'errors' => [],
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'cart' => null,
                'errors' => [[
                    'code' => 'APPLY_CONDITION_FAILED',
                    'message' => $e->getMessage(),
                    'field' => null,
                ]],
            ];
        }
    }

    /**
     * Remove condition from cart.
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function removeCondition(mixed $root, array $args): array
    {
        try {
            $cart = $this->cartManager
                ->setIdentifier($args['identifier'])
                ->setInstance($args['instance'] ?? 'default')
                ->getCurrentCart();

            $cart->removeCondition($args['conditionName']);

            return [
                'success' => true,
                'cart' => $this->transformCart($cart),
                'errors' => [],
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'cart' => null,
                'errors' => [[
                    'code' => 'REMOVE_CONDITION_FAILED',
                    'message' => $e->getMessage(),
                    'field' => null,
                ]],
            ];
        }
    }

    /**
     * Clear cart.
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function clearCart(mixed $root, array $args): array
    {
        try {
            $command = ClearCartCommand::fromArray([
                'identifier' => $args['identifier'],
                'instance' => $args['instance'] ?? 'default',
            ]);

            $this->commandBus->dispatch($command);

            $cart = $this->cartManager
                ->setIdentifier($args['identifier'])
                ->setInstance($args['instance'] ?? 'default')
                ->getCurrentCart();

            return [
                'success' => true,
                'cart' => $this->transformCart($cart),
                'errors' => [],
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'cart' => null,
                'errors' => [[
                    'code' => 'CLEAR_CART_FAILED',
                    'message' => $e->getMessage(),
                    'field' => null,
                ]],
            ];
        }
    }

    /**
     * Checkout cart.
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function checkout(mixed $root, array $args): array
    {
        $input = $args['input'];

        try {
            $cart = $this->cartManager
                ->setIdentifier($input['identifier'])
                ->setInstance($input['instance'] ?? 'default')
                ->getCurrentCart();

            $saga = CheckoutSaga::for($cart)
                ->withContext([
                    'payment_method' => $input['paymentMethod'] ?? null,
                    'shipping_address' => $input['shippingAddress'] ?? null,
                    'billing_address' => $input['billingAddress'] ?? null,
                    'metadata' => $input['metadata'] ?? [],
                ]);

            $result = $saga->execute();

            return [
                'success' => $result->success,
                'orderId' => $result->getOrderId(),
                'orderNumber' => $result->get('order_number'),
                'paymentUrl' => $result->getPaymentUrl(),
                'errors' => [],
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'orderId' => null,
                'orderNumber' => null,
                'paymentUrl' => null,
                'errors' => [[
                    'code' => 'CHECKOUT_FAILED',
                    'message' => $e->getMessage(),
                    'field' => null,
                ]],
            ];
        }
    }

    /**
     * Transform cart to GraphQL response format.
     *
     * @return array<string, mixed>
     */
    private function transformCart(\AIArmada\Cart\Cart $cart): array
    {
        $currency = config('cart.money.default_currency', 'MYR');

        return [
            'id' => $cart->getId(),
            'identifier' => $cart->getIdentifier(),
            'instance' => $cart->instance(),
            'items' => $cart->getItems()->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'price' => [
                    'amount' => $item->price,
                    'currency' => $currency,
                    'formatted' => $item->getPrice()->format(),
                ],
                'quantity' => $item->quantity,
                'subtotal' => [
                    'amount' => $item->getRawSubtotal(),
                    'currency' => $currency,
                    'formatted' => $item->getSubtotal()->format(),
                ],
                'conditions' => [],
                'attributes' => $item->attributes->toArray(),
            ])->values()->toArray(),
            'itemCount' => $cart->countItems(),
            'totalQuantity' => $cart->getTotalQuantity(),
            'conditions' => [],
            'subtotal' => [
                'amount' => $cart->getRawSubtotal(),
                'currency' => $currency,
                'formatted' => $cart->subtotal()->format(),
            ],
            'total' => [
                'amount' => $cart->getRawTotal(),
                'currency' => $currency,
                'formatted' => $cart->total()->format(),
            ],
            'savings' => [
                'amount' => 0,
                'currency' => $currency,
                'formatted' => '0.00',
            ],
            'metadata' => $cart->getAllMetadata(),
            'version' => $cart->getVersion(),
            'createdAt' => $cart->getCreatedAt(),
            'updatedAt' => $cart->getUpdatedAt(),
        ];
    }
}
