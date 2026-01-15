<?php

declare(strict_types=1);

namespace AIArmada\Cart\Conditions\Pipeline\Resolvers;

use AIArmada\Cart\Collections\CartConditionCollection;
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Conditions\Enums\ConditionScope;
use AIArmada\Cart\Conditions\Pipeline\ConditionPipelinePhaseContext;

final class CartScopeResolver implements ConditionScopeResolverInterface
{
    public function supports(ConditionScope $scope): bool
    {
        return $scope === ConditionScope::CART;
    }

    public function resolve(
        ConditionPipelinePhaseContext $phaseContext,
        ConditionScope $scope,
        CartConditionCollection $conditions,
        int $currentAmount
    ): int {
        return $conditions
            ->sortByOrder()
            ->reduce(
                static fn (int $amount, CartCondition $condition) => $condition->apply($amount),
                $currentAmount
            );
    }
}
