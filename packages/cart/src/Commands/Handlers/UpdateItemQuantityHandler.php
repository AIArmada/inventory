<?php

declare(strict_types=1);

namespace AIArmada\Cart\Commands\Handlers;

use AIArmada\Cart\CartManager;
use AIArmada\Cart\Commands\UpdateItemQuantityCommand;
use AIArmada\Cart\Models\CartItem;

/**
 * Handler for UpdateItemQuantityCommand.
 */
final class UpdateItemQuantityHandler
{
    public function __construct(
        private readonly CartManager $cartManager
    ) {}

    /**
     * Handle the command via __invoke for Laravel's command bus.
     */
    public function __invoke(UpdateItemQuantityCommand $command): ?CartItem
    {
        return $this->handle($command);
    }

    /**
     * Handle the update quantity command.
     *
     * @return CartItem|null The updated item or null if not found
     */
    public function handle(UpdateItemQuantityCommand $command): ?CartItem
    {
        $cart = $this->cartManager
            ->setIdentifier($command->identifier)
            ->setInstance($command->instance)
            ->getCurrentCart();

        $item = $cart->get($command->itemId);

        if ($item === null) {
            return null;
        }

        if ($command->newQuantity <= 0) {
            $cart->remove($command->itemId);

            return null;
        }

        return $cart->update($command->itemId, [
            'quantity' => $command->newQuantity,
        ]);
    }
}
