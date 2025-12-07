<?php

declare(strict_types=1);

namespace AIArmada\Cart\Commands\Handlers;

use AIArmada\Cart\CartManager;
use AIArmada\Cart\Commands\ClearCartCommand;

/**
 * Handler for ClearCartCommand.
 */
final class ClearCartHandler
{
    public function __construct(
        private readonly CartManager $cartManager
    ) {}

    /**
     * Handle the command via __invoke for Laravel's command bus.
     */
    public function __invoke(ClearCartCommand $command): void
    {
        $this->handle($command);
    }

    /**
     * Handle the clear cart command.
     */
    public function handle(ClearCartCommand $command): void
    {
        $cart = $this->cartManager
            ->setIdentifier($command->identifier)
            ->setInstance($command->instance)
            ->getCurrentCart();

        $cart->clear();
    }
}
