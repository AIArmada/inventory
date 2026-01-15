<?php

declare(strict_types=1);

namespace AIArmada\Cart\Conditions;

use AIArmada\Cart\Contracts\RulesFactoryInterface;
use AIArmada\Cart\Services\BuiltInRulesFactory;

/**
 * Pre-built condition presets for common cart scenarios.
 *
 * These presets provide ready-to-use CartCondition instances without
 * needing to understand the complex builder/targeting system.
 *
 * @example
 * ```php
 * // Simple usage - free shipping over RM100
 * $cart->condition(ConditionPresets::freeShippingOver(10000));
 *
 * // Percentage discount with minimum cart value
 * $cart->condition(ConditionPresets::percentageDiscountWithMinimum(10, 5000));
 *
 * // Time-limited sale
 * $cart->condition(ConditionPresets::flashSaleDiscount(20, '2024-01-01', '2024-01-02'));
 * ```
 */
final class ConditionPresets
{
    private static ?RulesFactoryInterface $rulesFactory = null;

    // =========================================================================
    // CART-LEVEL DISCOUNTS
    // =========================================================================

    /**
     * Percentage discount on cart subtotal.
     *
     * @param  int|float  $percentage  Discount percentage (e.g., 10 for 10%)
     * @param  string  $name  Optional condition name
     */
    public static function percentageDiscount(
        int | float $percentage,
        string $name = 'Percentage Discount'
    ): CartCondition {
        return new CartCondition(
            name: $name,
            type: 'discount',
            target: TargetPresets::cartSubtotal(),
            value: "-{$percentage}%",
            attributes: ['preset' => 'percentage_discount'],
            order: 10
        );
    }

    /**
     * Fixed amount discount on cart subtotal.
     *
     * @param  int  $amountCents  Discount amount in cents
     * @param  string  $name  Optional condition name
     */
    public static function fixedDiscount(
        int $amountCents,
        string $name = 'Fixed Discount'
    ): CartCondition {
        return new CartCondition(
            name: $name,
            type: 'discount',
            target: TargetPresets::cartSubtotal(),
            value: -$amountCents,
            attributes: ['preset' => 'fixed_discount'],
            order: 10
        );
    }

    /**
     * Percentage discount that only applies when cart subtotal meets minimum.
     *
     * @param  int|float  $percentage  Discount percentage
     * @param  int  $minimumCents  Minimum cart subtotal in cents
     * @param  string  $name  Optional condition name
     */
    public static function percentageDiscountWithMinimum(
        int | float $percentage,
        int $minimumCents,
        string $name = 'Percentage Discount'
    ): CartCondition {
        return new CartCondition(
            name: $name,
            type: 'discount',
            target: TargetPresets::cartSubtotal(),
            value: "-{$percentage}%",
            attributes: [
                'preset' => 'percentage_discount_minimum',
                'minimum_cents' => $minimumCents,
            ],
            order: 10,
            rules: self::getRulesFactory()->createRules('subtotal-at-least', ['amount' => $minimumCents])
        );
    }

    /**
     * Fixed discount that only applies when cart subtotal meets minimum.
     *
     * @param  int  $amountCents  Discount amount in cents
     * @param  int  $minimumCents  Minimum cart subtotal in cents
     * @param  string  $name  Optional condition name
     */
    public static function fixedDiscountWithMinimum(
        int $amountCents,
        int $minimumCents,
        string $name = 'Fixed Discount'
    ): CartCondition {
        return new CartCondition(
            name: $name,
            type: 'discount',
            target: TargetPresets::cartSubtotal(),
            value: -$amountCents,
            attributes: [
                'preset' => 'fixed_discount_minimum',
                'minimum_cents' => $minimumCents,
            ],
            order: 10,
            rules: self::getRulesFactory()->createRules('subtotal-at-least', ['amount' => $minimumCents])
        );
    }

    // =========================================================================
    // SHIPPING CONDITIONS
    // =========================================================================

    /**
     * Free shipping (100% discount on shipping).
     *
     * @param  string  $name  Optional condition name
     */
    public static function freeShipping(string $name = 'Free Shipping'): CartCondition
    {
        return new CartCondition(
            name: $name,
            type: 'shipping_discount',
            target: TargetPresets::cartShipping(),
            value: '-100%',
            attributes: ['preset' => 'free_shipping'],
            order: 100
        );
    }

    /**
     * Free shipping when cart subtotal meets minimum.
     *
     * @param  int  $minimumCents  Minimum cart subtotal in cents
     * @param  string  $name  Optional condition name
     */
    public static function freeShippingOver(
        int $minimumCents,
        string $name = 'Free Shipping'
    ): CartCondition {
        return new CartCondition(
            name: $name,
            type: 'shipping_discount',
            target: TargetPresets::cartShipping(),
            value: '-100%',
            attributes: [
                'preset' => 'free_shipping_over',
                'minimum_cents' => $minimumCents,
            ],
            order: 100,
            rules: self::getRulesFactory()->createRules('subtotal-at-least', ['amount' => $minimumCents])
        );
    }

    /**
     * Flat rate shipping fee.
     *
     * @param  int  $amountCents  Shipping fee in cents
     * @param  string  $name  Optional condition name
     */
    public static function flatRateShipping(
        int $amountCents,
        string $name = 'Shipping Fee'
    ): CartCondition {
        return new CartCondition(
            name: $name,
            type: 'shipping',
            target: TargetPresets::cartShipping(),
            value: $amountCents,
            attributes: ['preset' => 'flat_rate_shipping'],
            order: 50
        );
    }

    /**
     * Percentage discount on shipping.
     *
     * @param  int|float  $percentage  Discount percentage
     * @param  string  $name  Optional condition name
     */
    public static function shippingDiscount(
        int | float $percentage,
        string $name = 'Shipping Discount'
    ): CartCondition {
        return new CartCondition(
            name: $name,
            type: 'shipping_discount',
            target: TargetPresets::cartShipping(),
            value: "-{$percentage}%",
            attributes: ['preset' => 'shipping_discount'],
            order: 100
        );
    }

    // =========================================================================
    // TAX CONDITIONS
    // =========================================================================

    /**
     * Standard tax rate on taxable amount.
     *
     * @param  int|float  $percentage  Tax percentage (e.g., 6 for 6% SST)
     * @param  string  $name  Optional condition name
     */
    public static function taxRate(
        int | float $percentage,
        string $name = 'Tax'
    ): CartCondition {
        return new CartCondition(
            name: $name,
            type: 'tax',
            target: TargetPresets::cartTax(),
            value: "+{$percentage}%",
            attributes: ['preset' => 'tax_rate'],
            order: 200
        );
    }

    /**
     * Tax exemption (removes tax).
     *
     * @param  string  $name  Optional condition name
     */
    public static function taxExempt(string $name = 'Tax Exempt'): CartCondition
    {
        return new CartCondition(
            name: $name,
            type: 'tax_exemption',
            target: TargetPresets::cartTax(),
            value: '-100%',
            attributes: ['preset' => 'tax_exempt'],
            order: 250
        );
    }

    // =========================================================================
    // FEES & SURCHARGES
    // =========================================================================

    /**
     * Fixed service fee.
     *
     * @param  int  $amountCents  Fee amount in cents
     * @param  string  $name  Optional condition name
     */
    public static function serviceFee(
        int $amountCents,
        string $name = 'Service Fee'
    ): CartCondition {
        return new CartCondition(
            name: $name,
            type: 'fee',
            target: TargetPresets::cartGrandTotal(),
            value: $amountCents,
            attributes: ['preset' => 'service_fee'],
            order: 300
        );
    }

    /**
     * Percentage-based surcharge.
     *
     * @param  int|float  $percentage  Surcharge percentage
     * @param  string  $name  Optional condition name
     */
    public static function surcharge(
        int | float $percentage,
        string $name = 'Surcharge'
    ): CartCondition {
        return new CartCondition(
            name: $name,
            type: 'surcharge',
            target: TargetPresets::cartGrandTotal(),
            value: "+{$percentage}%",
            attributes: ['preset' => 'surcharge'],
            order: 300
        );
    }

    /**
     * Small order fee for orders below minimum.
     *
     * @param  int  $feeCents  Fee amount in cents
     * @param  int  $minimumCents  Minimum order to avoid fee
     * @param  string  $name  Optional condition name
     */
    public static function smallOrderFee(
        int $feeCents,
        int $minimumCents,
        string $name = 'Small Order Fee'
    ): CartCondition {
        return new CartCondition(
            name: $name,
            type: 'fee',
            target: TargetPresets::cartGrandTotal(),
            value: $feeCents,
            attributes: [
                'preset' => 'small_order_fee',
                'minimum_cents' => $minimumCents,
            ],
            order: 300,
            rules: self::getRulesFactory()->createRules('subtotal-below', ['amount' => $minimumCents])
        );
    }

    // =========================================================================
    // TIME-BASED CONDITIONS
    // =========================================================================

    /**
     * Flash sale discount for a specific date range.
     *
     * @param  int|float  $percentage  Discount percentage
     * @param  string  $startDate  Start date (Y-m-d or parseable format)
     * @param  string  $endDate  End date (Y-m-d or parseable format)
     * @param  string  $name  Optional condition name
     */
    public static function flashSaleDiscount(
        int | float $percentage,
        string $startDate,
        string $endDate,
        string $name = 'Flash Sale'
    ): CartCondition {
        return new CartCondition(
            name: $name,
            type: 'discount',
            target: TargetPresets::cartSubtotal(),
            value: "-{$percentage}%",
            attributes: [
                'preset' => 'flash_sale',
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            order: 5,
            rules: self::getRulesFactory()->createRules('date-window', [
                'start' => $startDate,
                'end' => $endDate,
            ])
        );
    }

    /**
     * Happy hour discount for specific time window.
     *
     * @param  int|float  $percentage  Discount percentage
     * @param  string  $startTime  Start time (HH:MM format)
     * @param  string  $endTime  End time (HH:MM format)
     * @param  string  $name  Optional condition name
     */
    public static function happyHourDiscount(
        int | float $percentage,
        string $startTime,
        string $endTime,
        string $name = 'Happy Hour'
    ): CartCondition {
        return new CartCondition(
            name: $name,
            type: 'discount',
            target: TargetPresets::cartSubtotal(),
            value: "-{$percentage}%",
            attributes: [
                'preset' => 'happy_hour',
                'start_time' => $startTime,
                'end_time' => $endTime,
            ],
            order: 5,
            rules: self::getRulesFactory()->createRules('time-window', [
                'start' => $startTime,
                'end' => $endTime,
            ])
        );
    }

    /**
     * Weekend discount (Saturday and Sunday).
     *
     * @param  int|float  $percentage  Discount percentage
     * @param  string  $name  Optional condition name
     */
    public static function weekendDiscount(
        int | float $percentage,
        string $name = 'Weekend Special'
    ): CartCondition {
        return new CartCondition(
            name: $name,
            type: 'discount',
            target: TargetPresets::cartSubtotal(),
            value: "-{$percentage}%",
            attributes: ['preset' => 'weekend_discount'],
            order: 5,
            rules: self::getRulesFactory()->createRules('day-of-week', ['days' => ['saturday', 'sunday']])
        );
    }

    // =========================================================================
    // CUSTOMER-BASED CONDITIONS
    // =========================================================================

    /**
     * Discount for customers with a specific tag.
     *
     * @param  string  $tag  Customer tag (e.g., 'vip', 'gold', 'member')
     * @param  int|float  $percentage  Discount percentage
     * @param  string  $name  Optional condition name
     * @param  string  $metadataKey  Cart metadata key storing customer tags
     */
    public static function customerTagDiscount(
        string $tag,
        int | float $percentage,
        string $name = 'Member Discount',
        string $metadataKey = 'customer_tags'
    ): CartCondition {
        return new CartCondition(
            name: $name,
            type: 'discount',
            target: TargetPresets::cartSubtotal(),
            value: "-{$percentage}%",
            attributes: [
                'preset' => 'customer_tag_discount',
                'customer_tag' => $tag,
            ],
            order: 15,
            rules: self::getRulesFactory()->createRules('customer-tag', [
                'tag' => $tag,
                'metadata_key' => $metadataKey,
            ])
        );
    }

    /**
     * VIP member discount.
     *
     * @param  int|float  $percentage  Discount percentage
     * @param  string  $name  Optional condition name
     */
    public static function vipDiscount(
        int | float $percentage,
        string $name = 'VIP Discount'
    ): CartCondition {
        return self::customerTagDiscount('vip', $percentage, $name);
    }

    // =========================================================================
    // ITEM-LEVEL CONDITIONS
    // =========================================================================

    /**
     * Per-item percentage discount.
     *
     * @param  int|float  $percentage  Discount percentage
     * @param  string  $name  Optional condition name
     */
    public static function itemPercentageDiscount(
        int | float $percentage,
        string $name = 'Item Discount'
    ): CartCondition {
        return new CartCondition(
            name: $name,
            type: 'item_discount',
            target: TargetPresets::itemsPerItem(),
            value: "-{$percentage}%",
            attributes: ['preset' => 'item_percentage_discount'],
            order: 10
        );
    }

    /**
     * Fixed per-item discount.
     *
     * @param  int  $amountCents  Discount amount per item in cents
     * @param  string  $name  Optional condition name
     */
    public static function itemFixedDiscount(
        int $amountCents,
        string $name = 'Item Discount'
    ): CartCondition {
        return new CartCondition(
            name: $name,
            type: 'item_discount',
            target: TargetPresets::itemsPerItem(),
            value: -$amountCents,
            attributes: ['preset' => 'item_fixed_discount'],
            order: 10
        );
    }

    // =========================================================================
    // BULK / QUANTITY CONDITIONS
    // =========================================================================

    /**
     * Bulk discount when minimum quantity is reached.
     *
     * @param  int  $minimumQuantity  Minimum total quantity
     * @param  int|float  $percentage  Discount percentage
     * @param  string  $name  Optional condition name
     */
    public static function bulkQuantityDiscount(
        int $minimumQuantity,
        int | float $percentage,
        string $name = 'Bulk Discount'
    ): CartCondition {
        return new CartCondition(
            name: $name,
            type: 'discount',
            target: TargetPresets::cartSubtotal(),
            value: "-{$percentage}%",
            attributes: [
                'preset' => 'bulk_quantity_discount',
                'minimum_quantity' => $minimumQuantity,
            ],
            order: 10,
            rules: self::getRulesFactory()->createRules('min-quantity', ['min' => $minimumQuantity])
        );
    }

    /**
     * Tiered discount based on cart subtotal.
     *
     * @param  array<int, int|float>  $tiers  Array of [threshold_cents => percentage]
     * @param  string  $name  Optional condition name
     * @return array<CartCondition> Array of mutually exclusive conditions
     */
    public static function tieredDiscount(
        array $tiers,
        string $name = 'Tiered Discount'
    ): array {
        krsort($tiers);

        $conditions = [];
        $previousThreshold = null;

        foreach ($tiers as $threshold => $percentage) {
            $rules = [
                ...self::getRulesFactory()->createRules('subtotal-at-least', ['amount' => $threshold]),
            ];

            if ($previousThreshold !== null) {
                $rules = array_merge(
                    $rules,
                    self::getRulesFactory()->createRules('subtotal-below', ['amount' => $previousThreshold])
                );
            }

            $conditions[] = new CartCondition(
                name: "{$name} ({$percentage}%)",
                type: 'discount',
                target: TargetPresets::cartSubtotal(),
                value: "-{$percentage}%",
                attributes: [
                    'preset' => 'tiered_discount',
                    'threshold_cents' => $threshold,
                    'percentage' => $percentage,
                ],
                order: 10,
                rules: $rules
            );

            $previousThreshold = $threshold;
        }

        return $conditions;
    }

    // =========================================================================
    // CONDITIONAL / PRODUCT-BASED
    // =========================================================================

    /**
     * Discount when specific product is in cart.
     *
     * @param  string  $productId  Product ID to check for
     * @param  int|float  $percentage  Discount percentage
     * @param  string  $name  Optional condition name
     */
    public static function discountWithProduct(
        string $productId,
        int | float $percentage,
        string $name = 'Product Discount'
    ): CartCondition {
        return new CartCondition(
            name: $name,
            type: 'discount',
            target: TargetPresets::cartSubtotal(),
            value: "-{$percentage}%",
            attributes: [
                'preset' => 'discount_with_product',
                'product_id' => $productId,
            ],
            order: 10,
            rules: self::getRulesFactory()->createRules('has-item', ['id' => $productId])
        );
    }

    /**
     * Discount when any of the specified products is in cart.
     *
     * @param  array<string>  $productIds  Product IDs to check for
     * @param  int|float  $percentage  Discount percentage
     * @param  string  $name  Optional condition name
     */
    public static function discountWithAnyProduct(
        array $productIds,
        int | float $percentage,
        string $name = 'Product Discount'
    ): CartCondition {
        return new CartCondition(
            name: $name,
            type: 'discount',
            target: TargetPresets::cartSubtotal(),
            value: "-{$percentage}%",
            attributes: [
                'preset' => 'discount_with_any_product',
                'product_ids' => $productIds,
            ],
            order: 10,
            rules: self::getRulesFactory()->createRules('item-list-includes-any', ['ids' => $productIds])
        );
    }

    // =========================================================================
    // INTERNAL HELPERS
    // =========================================================================

    /**
     * Get or create the rules factory instance.
     */
    private static function getRulesFactory(): RulesFactoryInterface
    {
        if (self::$rulesFactory === null) {
            self::$rulesFactory = new BuiltInRulesFactory;
        }

        return self::$rulesFactory;
    }

    /**
     * Set custom rules factory (for testing or custom implementations).
     */
    public static function setRulesFactory(RulesFactoryInterface $factory): void
    {
        self::$rulesFactory = $factory;
    }

    /**
     * Reset rules factory to default.
     */
    public static function resetRulesFactory(): void
    {
        self::$rulesFactory = null;
    }
}
