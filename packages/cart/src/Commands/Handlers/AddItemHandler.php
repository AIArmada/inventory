<?php

declare(strict_types=1);

namespace AIArmada\Cart\Commands\Handlers;

use AIArmada\Cart\Cart;
use AIArmada\Cart\CartManager;
use AIArmada\Cart\Commands\AddItemCommand;
use AIArmada\Cart\Models\CartItem;

/**
 * Handler for AddItemCommand.
 *
 * Encapsulates the logic for adding items to cart,
 * enabling command/query separation (CQRS).
 */
final class AddItemHandler
{
    public function __construct(
        private readonly CartManager $cartManager
    ) {}

    /**
     * Handle the command via __invoke for Laravel's command bus.
     */
    public function __invoke(AddItemCommand $command): CartItem
    {
        return $this->handle($command);
    }

    /**
     * Handle the add item command.
     *
     * @return CartItem The added or updated cart item
     */
    public function handle(AddItemCommand $command): CartItem
    {
        $cart = $this->cartManager
            ->setIdentifier($command->identifier)
            ->setInstance($command->instance)
            ->getCurrentCart();

        return $cart->add(
            id: $command->itemId,
            name: $command->itemName,
            price: $command->priceInCents,
            quantity: $command->quantity,
            attributes: $command->attributes,
            associatedModel: $command->associatedModel,
        );
    }
}
