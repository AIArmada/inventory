<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Services;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Models\Condition;

/**
 * Validates cart conditions before checkout.
 *
 * This service ensures that all conditions in the cart are still valid
 * and active before processing payment. It removes deactivated global
 * conditions and returns information about what was removed.
 */
final class CartConditionValidator
{
    /**
     * Validate cart conditions and remove deactivated ones.
     *
     * This should be called before checkout to ensure:
     * - All global conditions are still active
     * - Time-limited promotions haven't expired
     * - Prices are accurate at payment time
     *
     * @return array{
     *     is_valid: bool,
     *     removed_conditions: array<string>,
     *     price_changed: bool,
     *     old_total: float,
     *     new_total: float
     * }
     */
    public function validateAndClean(Cart $cart): array
    {
        $oldTotal = $cart->total();
        $removedConditions = [];

        // Get all condition names that are marked as global in the cart
        $globalConditionNames = $this->getGlobalConditionNames($cart);

        if ($globalConditionNames !== []) {
            // Get names of currently active global conditions from database
            $activeGlobalNames = Condition::global()
                ->pluck('name')
                ->toArray();

            // Find conditions that are deactivated
            $deactivatedConditionNames = array_diff($globalConditionNames, $activeGlobalNames);

            // Remove deactivated conditions
            foreach ($deactivatedConditionNames as $conditionName) {
                $this->removeConditionFromCart($cart, $conditionName);
                $removedConditions[] = $conditionName;
            }
        }

        $newTotal = $cart->total();
        $priceChanged = ! $oldTotal->equals($newTotal);

        return [
            'is_valid' => $removedConditions === [],
            'removed_conditions' => $removedConditions,
            'price_changed' => $priceChanged,
            'old_total' => $oldTotal->getAmount(),
            'new_total' => $newTotal->getAmount(),
        ];
    }

    /**
     * Get all global condition names from cart.
     *
     * @return array<string>
     */
    private function getGlobalConditionNames(Cart $cart): array
    {
        $names = [];

        // Check cart-level conditions
        foreach ($cart->getConditions() as $condition) {
            if ($condition->getAttribute('is_global') === true) {
                $names[] = $condition->getName();
            }
        }

        // Check dynamic conditions
        foreach ($cart->getDynamicConditions() as $dynamicCondition) {
            if ($dynamicCondition->getAttribute('is_global') === true) {
                $names[] = $dynamicCondition->getName();
            }
        }

        return $names;
    }

    /**
     * Remove a condition from cart (handles both static and dynamic).
     */
    private function removeConditionFromCart(Cart $cart, string $conditionName): void
    {
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
