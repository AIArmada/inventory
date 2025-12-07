<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Listeners;

use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Conditions\ConditionTarget;
use AIArmada\Cart\Contracts\RulesFactoryInterface;
use AIArmada\Cart\Events\CartCreated;
use AIArmada\Cart\Events\ItemAdded;
use AIArmada\Cart\Events\ItemRemoved;
use AIArmada\Cart\Events\ItemUpdated;
use AIArmada\Cart\Models\Condition;
use AIArmada\FilamentCart\Services\CartInstanceManager;
use InvalidArgumentException;

final class ApplyGlobalConditions
{
    /**
     * Track if we're currently applying conditions to prevent infinite recursion
     */
    private static bool $applying = false;

    public function __construct(
        private RulesFactoryInterface $rulesFactory,
        private CartInstanceManager $cartInstances,
    ) {}

    /**
     * Handle cart created event.
     */
    public function handleCartCreated(CartCreated $event): void
    {
        if (! config('filament-cart.features.global_conditions', true)) {
            return;
        }

        if (self::$applying) {
            return;
        }

        $this->applyGlobalConditions($event->cart);
    }

    /**
     * Handle item changed events (added, updated, removed).
     */
    public function handleItemChanged(ItemAdded|ItemUpdated|ItemRemoved $event): void
    {
        if (! config('filament-cart.features.global_conditions', true)) {
            return;
        }

        if (self::$applying) {
            return;
        }

        $this->applyGlobalConditions($event->cart);
    }

    /**
     * Apply all global conditions to the cart.
     */
    private function applyGlobalConditions(\AIArmada\Cart\Cart $cart): void
    {
        self::$applying = true;

        try {
            $cart = $this->cartInstances->prepare($cart);

            // Remove deactivated global conditions from cart
            $this->removeDeactivatedGlobalConditions($cart);

            $globalConditions = Condition::global()->get();
            foreach ($globalConditions as $condition) {
                $targetDefinition = ConditionTarget::from($condition->target)->toArray();

                // Build condition data
                $conditionData = [
                    'name' => $condition->name,
                    'type' => $condition->type,
                    'target' => $condition->target,
                    'target_definition' => $targetDefinition,
                    'value' => $condition->value,
                    'order' => $condition->order,
                    'attributes' => [
                        'display_name' => $condition->display_name,
                        'description' => $condition->description,
                        'is_global' => true,
                    ],
                ];

                // Instantiate CartCondition
                $cartConditionClass = CartCondition::class;

                // Handle dynamic vs static conditions differently
                if ($condition->isDynamic()) {
                    $factoryKeys = $condition->getRuleFactoryKeys();

                    if ($factoryKeys === []) {
                        continue;
                    }

                    $context = $condition->getRuleContext();
                    $rules = $this->buildRuleCallables($factoryKeys, $context);
                    $conditionData['rules'] = $rules;

                    $cartCondition = $cartConditionClass::fromArray($conditionData);

                    if (! $cart->getDynamicConditions()->has($condition->name)) {
                        $cart->registerDynamicCondition(
                            $cartCondition,
                            ruleFactoryKey: count($factoryKeys) === 1 ? $factoryKeys[0] : $factoryKeys,
                            metadata: [
                                'context' => $context,
                            ]
                        );
                    }
                } else {
                    // Static condition - add only if not already present
                    if (! $cart->getConditions()->has($condition->name)) {
                        $cartCondition = $cartConditionClass::fromArray($conditionData);
                        $cart->addCondition($cartCondition);
                    }
                }
            }
        } finally {
            self::$applying = false;
        }
    }

    /**
     * Remove global conditions that have been deactivated.
     * This ensures time-limited promotions are removed from active carts when they expire.
     */
    private function removeDeactivatedGlobalConditions(\AIArmada\Cart\Cart $cart): void
    {
        // Get all condition names that are currently marked as global in the cart
        $globalConditionNames = [];

        // Check cart-level conditions
        foreach ($cart->getConditions() as $condition) {
            if ($condition->getAttribute('is_global') === true) {
                $globalConditionNames[] = $condition->getName();
            }
        }

        // Check dynamic conditions (they're stored separately)
        foreach ($cart->getDynamicConditions() as $dynamicCondition) {
            if ($dynamicCondition->getAttribute('is_global') === true) {
                $globalConditionNames[] = $dynamicCondition->getName();
            }
        }

        if ($globalConditionNames === []) {
            return; // No global conditions in cart, nothing to check
        }

        // Get names of currently active global conditions from database
        $activeGlobalNames = Condition::global()
            ->pluck('name')
            ->toArray();

        // Find conditions that are marked as global in cart but no longer active in database
        $deactivatedConditionNames = array_diff($globalConditionNames, $activeGlobalNames);

        // Remove deactivated conditions from cart
        foreach ($deactivatedConditionNames as $conditionName) {
            // Try removing from regular conditions
            if ($cart->getConditions()->has($conditionName)) {
                $cart->removeCondition($conditionName);
            }

            // Try removing from dynamic conditions
            if ($cart->getDynamicConditions()->has($conditionName)) {
                $cart->removeDynamicCondition($conditionName);
            }
        }
    }

    /**
     * @param  array<int, string>  $factoryKeys
     * @param  array<string, mixed>  $context
     * @return array<callable>
     */
    private function buildRuleCallables(array $factoryKeys, array $context): array
    {
        $rules = [];

        foreach ($factoryKeys as $factoryKey) {
            if (! $this->rulesFactory->canCreateRules($factoryKey)) {
                throw new InvalidArgumentException("Unsupported rule factory key [{$factoryKey}]");
            }

            $rules = array_merge(
                $rules,
                $this->rulesFactory->createRules($factoryKey, ['context' => $context])
            );
        }

        return $rules;
    }
}
