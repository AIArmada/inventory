<?php

declare(strict_types=1);

namespace AIArmada\Cart\Examples;

use AIArmada\Cart\Contracts\RulesFactoryInterface;
use InvalidArgumentException;

/**
 * Example implementation of RulesFactoryInterface.
 *
 * This shows how applications can implement rule factories to enable
 * dynamic condition persistence. Copy this class to your application
 * and customize the rules to match your business logic.
 */
class ExampleRulesFactory implements RulesFactoryInterface
{
    /**
     * Create rules for a dynamic condition by factory key.
     *
     * @param  string  $key  The rule factory key
     * @param  array<string, mixed>  $metadata  Additional condition metadata
     * @return array<callable> Array of rule closures
     *
     * @throws InvalidArgumentException When the factory key is not supported
     */
    public function createRules(string $key, array $metadata = []): array
    {
        return match ($key) {
            // Minimum order amount discount
            'min_order_discount' => [
                fn ($cart) => $cart->subtotalWithoutConditions()->getAmount() >=
                           ($metadata['min_amount'] ?? 100),
            ],

            // Bulk quantity discount
            'bulk_quantity_discount' => [
                fn ($cart) => $cart->getTotalQuantity() >=
                           ($metadata['min_quantity'] ?? 10),
            ],

            // User role-based discount (requires authentication)
            'user_role_discount' => [
                function ($cart) use ($metadata) {
                    // Example: Role-based discount
                    // Replace with your actual authentication/role checking logic
                    $requiredRole = $metadata['required_role'] ?? 'premium';

                    return $requiredRole === 'premium'; // Placeholder - always true for premium
                },
            ],

            // Time-based discount (e.g., happy hour)
            'time_based_discount' => [
                function ($cart) use ($metadata) {
                    $startTime = $metadata['start_time'] ?? '09:00';
                    $endTime = $metadata['end_time'] ?? '17:00';

                    return now()->format('H:i') >= $startTime &&
                           now()->format('H:i') <= $endTime;
                },
            ],

            // Category-specific discount
            'category_discount' => [
                function ($cart) use ($metadata) {
                    $targetCategory = $metadata['category'] ?? 'electronics';

                    return $cart->getItems()->some(
                        fn ($item) => $item->getAttribute('category') === $targetCategory
                    );
                },
            ],

            // First-time customer discount
            'first_time_customer' => [
                function ($cart) {
                    // Example: First-time customer discount
                    // Replace with your actual order checking logic
                    return true; // Placeholder - always apply for demo
                },
            ],

            // Day of week discount (e.g., Monday blues discount)
            'day_of_week_discount' => [
                function ($cart) use ($metadata) {
                    $targetDay = $metadata['day_of_week'] ?? 'Monday';

                    return now()->format('l') === $targetDay;
                },
            ],

            // Seasonal discount
            'seasonal_discount' => [
                function ($cart) use ($metadata) {
                    $season = $metadata['season'] ?? 'winter';

                    $currentMonth = (int) now()->format('n');

                    return match ($season) {
                        'spring' => in_array($currentMonth, [3, 4, 5]),
                        'summer' => in_array($currentMonth, [6, 7, 8]),
                        'autumn' => in_array($currentMonth, [9, 10, 11]),
                        'winter' => in_array($currentMonth, [12, 1, 2]),
                        default => false,
                    };
                },
            ],

            // Voucher with minimum order requirement
            'voucher_min_order' => [
                fn ($cart) => $cart->subtotalWithoutConditions()->getAmount() >=
                           ($metadata['min_amount'] ?? 50),
            ],

            // Free shipping threshold
            'free_shipping_threshold' => [
                fn ($cart) => $cart->subtotalWithoutConditions()->getAmount() >=
                           ($metadata['free_shipping_threshold'] ?? 75),
            ],

            default => throw new InvalidArgumentException("Unknown rule factory key: {$key}")
        };
    }

    /**
     * Check if factory can create rules for the given key.
     *
     * @param  string  $key  The rule factory key to check
     * @return bool True if the factory can create rules for this key
     */
    public function canCreateRules(string $key): bool
    {
        return in_array($key, $this->getAvailableKeys());
    }

    /**
     * Get all available rule factory keys.
     *
     * @return array<string> List of supported rule factory keys
     */
    public function getAvailableKeys(): array
    {
        return [
            'min_order_discount',
            'bulk_quantity_discount',
            'user_role_discount',
            'time_based_discount',
            'category_discount',
            'first_time_customer',
            'day_of_week_discount',
            'seasonal_discount',
            'voucher_min_order',
            'free_shipping_threshold',
        ];
    }
}
