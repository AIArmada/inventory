<?php

declare(strict_types=1);

namespace AIArmada\Cart\Conditions\Pipeline;

use AIArmada\Cart\Collections\CartConditionCollection;
use AIArmada\Cart\Conditions\Enums\ConditionPhase;
use AIArmada\Cart\Conditions\Enums\ConditionScope;
use AIArmada\Cart\Conditions\Pipeline\Resolvers\CartScopeResolver;
use AIArmada\Cart\Conditions\Pipeline\Resolvers\ConditionScopeResolverInterface;
use AIArmada\Cart\Conditions\Pipeline\Resolvers\DefaultScopeResolver;

final class ConditionPipeline
{
    /**
     * @var array<string, callable(ConditionPipelinePhaseContext): int>
     */
    private array $phaseProcessors = [];

    /**
     * @var array<string, ConditionScopeResolverInterface>
     */
    private array $scopeResolvers = [];

    public function __construct()
    {
        $this->registerScopeResolver(ConditionScope::CART, new CartScopeResolver);
    }

    public function registerPhaseProcessor(ConditionPhase $phase, callable $processor): static
    {
        $this->phaseProcessors[$phase->value] = $processor;

        return $this;
    }

    public function registerScopeResolver(ConditionScope $scope, ConditionScopeResolverInterface $resolver): static
    {
        $this->scopeResolvers[$scope->value] = $resolver;

        return $this;
    }

    public function process(ConditionPipelineContext $context): ConditionPipelineResult
    {
        $conditions = $context->conditions();
        $amount = $context->initialAmount();
        $initialAmount = $amount;
        $phaseResults = [];

        foreach ($this->phasesInOrder() as $phase) {
            $phaseConditions = $conditions->byPhase($phase);
            $phaseContext = new ConditionPipelinePhaseContext(
                $phase,
                $amount,
                $phaseConditions,
                $context
            );

            $finalAmount = $this->resolvePhaseAmount($phaseContext);
            $phaseResults[$phase->value] = new ConditionPhaseResult(
                $phase,
                $amount,
                $finalAmount,
                $finalAmount - $amount,
                $phaseConditions->count()
            );

            $amount = $finalAmount;
        }

        return new ConditionPipelineResult($initialAmount, $amount, $phaseResults);
    }

    /**
     * @return list<ConditionPhase>
     */
    private function phasesInOrder(): array
    {
        $phases = ConditionPhase::cases();
        usort($phases, static fn (ConditionPhase $a, ConditionPhase $b) => $a->order() <=> $b->order());

        return $phases;
    }

    private function resolvePhaseAmount(ConditionPipelinePhaseContext $context): int
    {
        $processor = $this->phaseProcessors[$context->phase->value] ?? null;

        if ($processor !== null) {
            return (int) $processor($context);
        }

        if ($context->isEmpty()) {
            return $context->baseAmount;
        }

        return $this->applyScopes($context, $context->conditions);
    }

    private function applyScopes(ConditionPipelinePhaseContext $context, CartConditionCollection $conditions): int
    {
        $grouped = $conditions->groupByScope();
        $amount = $context->baseAmount;

        foreach ($this->scopesInOrder() as $scope) {
            /** @var CartConditionCollection|null $scopeConditions */
            $scopeConditions = $grouped->get($scope->value);

            if ($scopeConditions === null || $scopeConditions->isEmpty()) {
                continue;
            }

            $amount = $this->getResolverForScope($scope)->resolve(
                $context,
                $scope,
                $scopeConditions,
                $amount
            );
        }

        return $amount;
    }

    /**
     * @return list<ConditionScope>
     */
    private function scopesInOrder(): array
    {
        return ConditionScope::cases();
    }

    private function getResolverForScope(ConditionScope $scope): ConditionScopeResolverInterface
    {
        return $this->scopeResolvers[$scope->value]
            ??= new DefaultScopeResolver($scope);
    }
}
