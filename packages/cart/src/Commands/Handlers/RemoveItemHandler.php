<?php

declare(strict_types=1);

namespace AIArmada\Cart\Commands\Handlers;

use AIArmada\Cart\CartManager;
use AIArmada\Cart\Commands\RemoveItemCommand;

/**
 * Handler for RemoveItemCommand.
 */
final class RemoveItemHandler
{
    public function __construct(
        private readonly CartManager $cartManager
    ) {}

    /**
     * Handle the command via __invoke for Laravel's command bus.
     */
    public function __invoke(RemoveItemCommand $command): bool
    {
        return $this->handle($command);
    }

    /**
     * Handle the remove item command.
     *
     * @return bool True if item was removed, false if not found
     */
    public function handle(RemoveItemCommand $command): bool
    {
        $cart = $this->cartManager
            ->setIdentifier($command->identifier)
            ->setInstance($command->instance)
            ->getCurrentCart();

        $item = $cart->get($command->itemId);

        if ($item === null) {
            return false;
        }

        $cart->remove($command->itemId);

        return true;
    }
}
