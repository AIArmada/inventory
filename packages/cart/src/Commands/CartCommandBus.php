<?php

declare(strict_types=1);

namespace AIArmada\Cart\Commands;

use AIArmada\Cart\Commands\Handlers\AddItemHandler;
use AIArmada\Cart\Commands\Handlers\ApplyConditionHandler;
use AIArmada\Cart\Commands\Handlers\ClearCartHandler;
use AIArmada\Cart\Commands\Handlers\RemoveItemHandler;
use AIArmada\Cart\Commands\Handlers\UpdateItemQuantityHandler;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Command bus for cart CQRS operations.
 *
 * Routes commands to their appropriate handlers.
 * Can be used directly or integrated with Laravel's command bus.
 */
final class CartCommandBus
{
    /**
     * Map of command classes to handler classes.
     *
     * @var array<class-string, class-string>
     */
    private const array HANDLERS = [
        AddItemCommand::class => AddItemHandler::class,
        UpdateItemQuantityCommand::class => UpdateItemQuantityHandler::class,
        RemoveItemCommand::class => RemoveItemHandler::class,
        ApplyConditionCommand::class => ApplyConditionHandler::class,
        ClearCartCommand::class => ClearCartHandler::class,
    ];

    public function __construct(
        private readonly Container $container
    ) {}

    /**
     * Dispatch a command to its handler.
     *
     * @param  object  $command  The command to dispatch
     * @return mixed The result from the handler
     *
     * @throws InvalidArgumentException If no handler is registered for the command
     */
    public function dispatch(object $command): mixed
    {
        $commandClass = $command::class;

        if (! isset(self::HANDLERS[$commandClass])) {
            throw new InvalidArgumentException(
                "No handler registered for command: {$commandClass}"
            );
        }

        $handlerClass = self::HANDLERS[$commandClass];
        $handler = $this->container->make($handlerClass);

        return $handler->handle($command);
    }

    /**
     * Check if a handler exists for a command.
     */
    public function hasHandler(string $commandClass): bool
    {
        return isset(self::HANDLERS[$commandClass]);
    }

    /**
     * Get all registered command types.
     *
     * @return array<class-string>
     */
    public function getRegisteredCommands(): array
    {
        return array_keys(self::HANDLERS);
    }
}
