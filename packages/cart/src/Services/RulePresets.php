<?php

declare(strict_types=1);

namespace AIArmada\Cart\Services;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Models\CartItem;
use Carbon\CarbonImmutable;

/**
 * Pre-built rule presets for common cart validation scenarios.
 *
 * These presets provide ready-to-use rule closures without needing
 * to understand the BuiltInRulesFactory or rule creation details.
 *
 * Rules are returned as arrays of callables that can be passed directly
 * to CartCondition or used with dynamic conditions.
 *
 * @example
 * ```php
 * // Create condition with minimum cart value rule
 * $cart->registerDynamicCondition(
 *     condition: ConditionPresets::percentageDiscount(10),
 *     ruleFactoryKey: null,
 *     metadata: [],
 *     rules: RulePresets::minimumCartValue(5000)
 * );
 *
 * // Combine multiple rules
 * $rules = array_merge(
 *     RulePresets::minimumCartValue(5000),
 *     RulePresets::requireWeekend()
 * );
 * ```
 */
final class RulePresets
{
    // =========================================================================
    // CART VALUE RULES
    // =========================================================================

    /**
     * Require minimum cart subtotal.
     *
     * @param  int  $minimumCents  Minimum subtotal in cents
     * @return array<callable>
     */
    public static function minimumCartValue(int $minimumCents): array
    {
        return [
            static fn (Cart $cart, ?CartItem $item = null): bool => $cart->getRawSubtotalWithoutConditions() >= $minimumCents,
        ];
    }

    /**
     * Require cart subtotal below maximum.
     *
     * @param  int  $maximumCents  Maximum subtotal in cents
     * @return array<callable>
     */
    public static function maximumCartValue(int $maximumCents): array
    {
        return [
            static fn (Cart $cart, ?CartItem $item = null): bool => $cart->getRawSubtotalWithoutConditions() < $maximumCents,
        ];
    }

    /**
     * Require cart subtotal within range.
     *
     * @param  int  $minCents  Minimum subtotal in cents
     * @param  int  $maxCents  Maximum subtotal in cents
     * @return array<callable>
     */
    public static function cartValueBetween(int $minCents, int $maxCents): array
    {
        return [
            static fn (Cart $cart, ?CartItem $item = null): bool => $cart->getRawSubtotalWithoutConditions() >= $minCents
                && $cart->getRawSubtotalWithoutConditions() <= $maxCents,
        ];
    }

    // =========================================================================
    // QUANTITY RULES
    // =========================================================================

    /**
     * Require minimum total quantity of items.
     *
     * @param  int  $minimum  Minimum quantity
     * @return array<callable>
     */
    public static function minimumQuantity(int $minimum): array
    {
        return [
            static fn (Cart $cart, ?CartItem $item = null): bool => $cart->getTotalQuantity() >= $minimum,
        ];
    }

    /**
     * Require maximum total quantity of items.
     *
     * @param  int  $maximum  Maximum quantity
     * @return array<callable>
     */
    public static function maximumQuantity(int $maximum): array
    {
        return [
            static fn (Cart $cart, ?CartItem $item = null): bool => $cart->getTotalQuantity() <= $maximum,
        ];
    }

    /**
     * Require minimum number of distinct items.
     *
     * @param  int  $minimum  Minimum item count
     * @return array<callable>
     */
    public static function minimumItems(int $minimum): array
    {
        return [
            static fn (Cart $cart, ?CartItem $item = null): bool => $cart->countItems() >= $minimum,
        ];
    }

    /**
     * Require maximum number of distinct items.
     *
     * @param  int  $maximum  Maximum item count
     * @return array<callable>
     */
    public static function maximumItems(int $maximum): array
    {
        return [
            static fn (Cart $cart, ?CartItem $item = null): bool => $cart->countItems() <= $maximum,
        ];
    }

    // =========================================================================
    // PRODUCT/ITEM RULES
    // =========================================================================

    /**
     * Require specific product in cart.
     *
     * @param  string  $productId  Product ID
     * @return array<callable>
     */
    public static function requireProduct(string $productId): array
    {
        return [
            static fn (Cart $cart, ?CartItem $item = null): bool => $cart->has($productId),
        ];
    }

    /**
     * Block if specific product is in cart.
     *
     * @param  string  $productId  Product ID
     * @return array<callable>
     */
    public static function excludeProduct(string $productId): array
    {
        return [
            static fn (Cart $cart, ?CartItem $item = null): bool => ! $cart->has($productId),
        ];
    }

    /**
     * Require any of the specified products in cart.
     *
     * @param  array<string>  $productIds  Product IDs
     * @return array<callable>
     */
    public static function requireAnyProduct(array $productIds): array
    {
        return [
            static function (Cart $cart, ?CartItem $item = null) use ($productIds): bool {
                foreach ($productIds as $id) {
                    if ($cart->has($id)) {
                        return true;
                    }
                }

                return false;
            },
        ];
    }

    /**
     * Require all specified products in cart.
     *
     * @param  array<string>  $productIds  Product IDs
     * @return array<callable>
     */
    public static function requireAllProducts(array $productIds): array
    {
        return [
            static function (Cart $cart, ?CartItem $item = null) use ($productIds): bool {
                foreach ($productIds as $id) {
                    if (! $cart->has($id)) {
                        return false;
                    }
                }

                return true;
            },
        ];
    }

    /**
     * Require products with specific ID prefix.
     *
     * @param  string  $prefix  ID prefix (e.g., 'promo-', 'bundle-')
     * @return array<callable>
     */
    public static function requireProductPrefix(string $prefix): array
    {
        return [
            static fn (Cart $cart, ?CartItem $item = null): bool => $cart->getItems()
                ->contains(static fn (CartItem $cartItem): bool => str_starts_with($cartItem->id, $prefix)),
        ];
    }

    // =========================================================================
    // TIME-BASED RULES
    // =========================================================================

    /**
     * Only valid during specific date range.
     *
     * @param  string  $startDate  Start date (parseable by Carbon)
     * @param  string  $endDate  End date (parseable by Carbon)
     * @return array<callable>
     */
    public static function dateRange(string $startDate, string $endDate): array
    {
        $start = CarbonImmutable::parse($startDate)->startOfDay();
        $end = CarbonImmutable::parse($endDate)->endOfDay();

        return [
            static fn (Cart $cart, ?CartItem $item = null): bool => CarbonImmutable::now()->betweenIncluded($start, $end),
        ];
    }

    /**
     * Only valid during specific time window (daily).
     *
     * @param  string  $startTime  Start time (HH:MM format)
     * @param  string  $endTime  End time (HH:MM format)
     * @return array<callable>
     */
    public static function timeWindow(string $startTime, string $endTime): array
    {
        [$startHour, $startMinute] = array_map('intval', explode(':', $startTime));
        [$endHour, $endMinute] = array_map('intval', explode(':', $endTime));

        $startMinutes = ($startHour * 60) + $startMinute;
        $endMinutes = ($endHour * 60) + $endMinute;

        return [
            static function (Cart $cart, ?CartItem $item = null) use ($startMinutes, $endMinutes): bool {
                $now = CarbonImmutable::now();
                $currentMinutes = ($now->hour * 60) + $now->minute;

                if ($startMinutes <= $endMinutes) {
                    return $currentMinutes >= $startMinutes && $currentMinutes <= $endMinutes;
                }

                return $currentMinutes >= $startMinutes || $currentMinutes <= $endMinutes;
            },
        ];
    }

    /**
     * Only valid on weekends (Saturday and Sunday).
     *
     * @return array<callable>
     */
    public static function requireWeekend(): array
    {
        return [
            static fn (Cart $cart, ?CartItem $item = null): bool => CarbonImmutable::now()->isWeekend(),
        ];
    }

    /**
     * Only valid on weekdays (Monday to Friday).
     *
     * @return array<callable>
     */
    public static function requireWeekday(): array
    {
        return [
            static fn (Cart $cart, ?CartItem $item = null): bool => CarbonImmutable::now()->isWeekday(),
        ];
    }

    /**
     * Only valid on specific days of week.
     *
     * @param  array<int|string>  $days  Day numbers (0=Sunday) or names ('monday', 'tue', etc.)
     * @return array<callable>
     */
    public static function requireDaysOfWeek(array $days): array
    {
        $normalized = [];

        foreach ($days as $day) {
            if (is_numeric($day)) {
                $normalized[] = ((int) $day) % 7;

                continue;
            }

            $normalized[] = match (mb_strtolower((string) $day)) {
                'sun', 'sunday' => 0,
                'mon', 'monday' => 1,
                'tue', 'tuesday' => 2,
                'wed', 'wednesday' => 3,
                'thu', 'thursday' => 4,
                'fri', 'friday' => 5,
                'sat', 'saturday' => 6,
                default => -1,
            };
        }

        $normalized = array_filter($normalized, static fn (int $day): bool => $day >= 0);

        return [
            static fn (Cart $cart, ?CartItem $item = null): bool => in_array(CarbonImmutable::now()->dayOfWeek, $normalized, true),
        ];
    }

    // =========================================================================
    // CUSTOMER RULES
    // =========================================================================

    /**
     * Require customer to have specific tag.
     *
     * @param  string  $tag  Customer tag
     * @param  string  $metadataKey  Cart metadata key storing tags
     * @return array<callable>
     */
    public static function requireCustomerTag(string $tag, string $metadataKey = 'customer_tags'): array
    {
        return [
            static function (Cart $cart, ?CartItem $item = null) use ($tag, $metadataKey): bool {
                $tags = $cart->getMetadata($metadataKey);

                if (is_array($tags)) {
                    return in_array($tag, $tags, true);
                }

                if (is_string($tags)) {
                    return in_array($tag, array_map('trim', explode(',', $tags)), true);
                }

                return false;
            },
        ];
    }

    /**
     * Require customer to have any of specified tags.
     *
     * @param  array<string>  $tags  Customer tags
     * @param  string  $metadataKey  Cart metadata key storing tags
     * @return array<callable>
     */
    public static function requireAnyCustomerTag(array $tags, string $metadataKey = 'customer_tags'): array
    {
        return [
            static function (Cart $cart, ?CartItem $item = null) use ($tags, $metadataKey): bool {
                $customerTags = $cart->getMetadata($metadataKey);

                if (is_array($customerTags)) {
                    return ! empty(array_intersect($tags, $customerTags));
                }

                if (is_string($customerTags)) {
                    $customerTagArray = array_map('trim', explode(',', $customerTags));

                    return ! empty(array_intersect($tags, $customerTagArray));
                }

                return false;
            },
        ];
    }

    /**
     * Require VIP customer.
     *
     * @return array<callable>
     */
    public static function requireVip(): array
    {
        return self::requireCustomerTag('vip');
    }

    /**
     * Block guest customers (require authenticated).
     *
     * @param  string  $metadataKey  Cart metadata key storing user ID
     * @return array<callable>
     */
    public static function requireAuthenticated(string $metadataKey = 'user_id'): array
    {
        return [
            static fn (Cart $cart, ?CartItem $item = null): bool => $cart->hasMetadata($metadataKey)
                && $cart->getMetadata($metadataKey) !== null,
        ];
    }

    // =========================================================================
    // METADATA RULES
    // =========================================================================

    /**
     * Require specific metadata key to exist.
     *
     * @param  string  $key  Metadata key
     * @return array<callable>
     */
    public static function requireMetadata(string $key): array
    {
        return [
            static fn (Cart $cart, ?CartItem $item = null): bool => $cart->hasMetadata($key),
        ];
    }

    /**
     * Require metadata key to have specific value.
     *
     * @param  string  $key  Metadata key
     * @param  mixed  $value  Expected value
     * @return array<callable>
     */
    public static function requireMetadataValue(string $key, mixed $value): array
    {
        return [
            static fn (Cart $cart, ?CartItem $item = null): bool => $cart->getMetadata($key) === $value,
        ];
    }

    /**
     * Require metadata flag to be true.
     *
     * @param  string  $key  Metadata key
     * @return array<callable>
     */
    public static function requireFlag(string $key): array
    {
        return [
            static fn (Cart $cart, ?CartItem $item = null): bool => $cart->getMetadata($key) === true,
        ];
    }

    /**
     * Block if metadata flag is true.
     *
     * @param  string  $key  Metadata key
     * @return array<callable>
     */
    public static function blockIfFlag(string $key): array
    {
        return [
            static fn (Cart $cart, ?CartItem $item = null): bool => $cart->getMetadata($key) !== true,
        ];
    }

    // =========================================================================
    // CART STATE RULES
    // =========================================================================

    /**
     * Require cart to not be empty.
     *
     * @return array<callable>
     */
    public static function requireNonEmpty(): array
    {
        return [
            static fn (Cart $cart, ?CartItem $item = null): bool => ! $cart->isEmpty(),
        ];
    }

    /**
     * Block if specific condition already exists.
     *
     * @param  string  $conditionName  Condition name
     * @return array<callable>
     */
    public static function blockIfConditionExists(string $conditionName): array
    {
        return [
            static fn (Cart $cart, ?CartItem $item = null): bool => ! $cart->getConditions()->has($conditionName),
        ];
    }

    /**
     * Require specific condition to exist.
     *
     * @param  string  $conditionName  Condition name
     * @return array<callable>
     */
    public static function requireCondition(string $conditionName): array
    {
        return [
            static fn (Cart $cart, ?CartItem $item = null): bool => $cart->getConditions()->has($conditionName),
        ];
    }

    /**
     * Block if condition type already exists.
     *
     * @param  string  $conditionType  Condition type
     * @return array<callable>
     */
    public static function blockIfConditionTypeExists(string $conditionType): array
    {
        return [
            static fn (Cart $cart, ?CartItem $item = null): bool => $cart->getConditions()->byType($conditionType)->isEmpty(),
        ];
    }

    // =========================================================================
    // UTILITY RULES
    // =========================================================================

    /**
     * Always pass (useful for testing or placeholder).
     *
     * @return array<callable>
     */
    public static function always(): array
    {
        return [
            static fn (Cart $cart, ?CartItem $item = null): bool => true,
        ];
    }

    /**
     * Always fail (useful for testing or blocking).
     *
     * @return array<callable>
     */
    public static function never(): array
    {
        return [
            static fn (Cart $cart, ?CartItem $item = null): bool => false,
        ];
    }

    /**
     * Combine multiple rule sets with AND logic.
     *
     * @param  array<callable>  ...$ruleSets  Multiple rule arrays
     * @return array<callable>
     */
    public static function all(array ...$ruleSets): array
    {
        return array_merge(...$ruleSets);
    }

    /**
     * Combine multiple rule sets with OR logic.
     *
     * @param  array<callable>  ...$ruleSets  Multiple rule arrays
     * @return array<callable>
     */
    public static function any(array ...$ruleSets): array
    {
        return [
            static function (Cart $cart, ?CartItem $item = null) use ($ruleSets): bool {
                foreach ($ruleSets as $ruleSet) {
                    $allPassed = true;

                    foreach ($ruleSet as $rule) {
                        if (! $rule($cart, $item)) {
                            $allPassed = false;

                            break;
                        }
                    }

                    if ($allPassed) {
                        return true;
                    }
                }

                return false;
            },
        ];
    }

    /**
     * Negate a rule set.
     *
     * @param  array<callable>  $rules  Rules to negate
     * @return array<callable>
     */
    public static function not(array $rules): array
    {
        return [
            static function (Cart $cart, ?CartItem $item = null) use ($rules): bool {
                foreach ($rules as $rule) {
                    if (! $rule($cart, $item)) {
                        return true;
                    }
                }

                return false;
            },
        ];
    }
}
