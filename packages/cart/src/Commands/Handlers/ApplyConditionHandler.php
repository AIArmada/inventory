<?php

declare(strict_types=1);

namespace AIArmada\Cart\Commands\Handlers;

use AIArmada\Cart\CartManager;
use AIArmada\Cart\Commands\ApplyConditionCommand;
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Conditions\ConditionTarget;

/**
 * Handler for ApplyConditionCommand.
 */
final class ApplyConditionHandler
{
    public function __construct(
        private readonly CartManager $cartManager
    ) {}

    /**
     * Handle the command via __invoke for Laravel's command bus.
     */
    public function __invoke(ApplyConditionCommand $command): CartCondition
    {
        return $this->handle($command);
    }

    /**
     * Handle the apply condition command.
     */
    public function handle(ApplyConditionCommand $command): CartCondition
    {
        $cart = $this->cartManager
            ->setIdentifier($command->identifier)
            ->setInstance($command->instance)
            ->getCurrentCart();

        $condition = new CartCondition(
            name: $command->conditionName,
            type: $command->conditionType,
            target: ConditionTarget::from($command->target),
            value: $command->value,
            attributes: $command->attributes,
            order: $command->order
        );

        $cart->addCondition($condition);

        return $condition;
    }
}
