<?php

declare(strict_types=1);

namespace AIArmada\Cart\Testing;

use AIArmada\Cart\Contracts\RulesFactoryInterface;
use InvalidArgumentException;

/**
 * Example implementation of RulesFactoryInterface for testing purposes.
 *
 * This factory provides common rule patterns that can be used in tests
 * and as a reference for building custom rule factories.
 */
final class ExampleRulesFactory implements RulesFactoryInterface
{
    private const array SUPPORTED_KEYS = [
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

    public function createRules(string $key, array $metadata = []): array
    {
        return match ($key) {
            'min_order_discount' => [
                fn ($cart) => $cart->subtotalWithoutConditions()->getAmount() >=
                           ($metadata['min_amount'] ?? 100),
            ],

            'bulk_quantity_discount' => [
                fn ($cart) => $cart->getTotalQuantity() >=
                           ($metadata['min_quantity'] ?? 10),
            ],

            'user_role_discount' => [
                function ($cart) use ($metadata) {
                    $requiredRole = $metadata['required_role'] ?? 'premium';

                    return $requiredRole === 'premium';
                },
            ],

            'time_based_discount' => [
                function ($cart) use ($metadata) {
                    $startTime = $metadata['start_time'] ?? '09:00';
                    $endTime = $metadata['end_time'] ?? '17:00';

                    return now()->format('H:i') >= $startTime &&
                           now()->format('H:i') <= $endTime;
                },
            ],

            'category_discount' => [
                function ($cart) use ($metadata) {
                    $targetCategory = $metadata['category'] ?? 'electronics';

                    return $cart->getItems()->some(
                        fn ($item) => $item->getAttribute('category') === $targetCategory
                    );
                },
            ],

            'first_time_customer' => [
                fn ($cart) => true,
            ],

            'day_of_week_discount' => [
                function ($cart) use ($metadata) {
                    $targetDay = $metadata['day_of_week'] ?? 'Monday';

                    return now()->format('l') === $targetDay;
                },
            ],

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

            'voucher_min_order' => [
                fn ($cart) => $cart->subtotalWithoutConditions()->getAmount() >=
                           ($metadata['min_amount'] ?? 50),
            ],

            'free_shipping_threshold' => [
                fn ($cart) => $cart->subtotalWithoutConditions()->getAmount() >=
                           ($metadata['free_shipping_threshold'] ?? 75),
            ],

            default => throw new InvalidArgumentException("Unknown rule factory key: {$key}")
        };
    }

    public function canCreateRules(string $key): bool
    {
        return in_array($key, self::SUPPORTED_KEYS, true);
    }

    /**
     * @return list<string>
     */
    public function getAvailableKeys(): array
    {
        return self::SUPPORTED_KEYS;
    }
}
