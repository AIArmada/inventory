<?php

declare(strict_types=1);

namespace AIArmada\Cart\Services;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Contracts\RulesFactoryInterface;
use AIArmada\Cart\Models\CartItem;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;
use Throwable;

/**
 * Built-in rules factory for dynamic cart conditions.
 *
 * This factory provides general-purpose rules for cart operations including:
 * - Inventory availability checks
 * - Customer eligibility rules
 * - Time-based conditions
 * - Cart value thresholds
 * - Product/category restrictions
 */
final class BuiltInRulesFactory implements RulesFactoryInterface
{
    /**
     * @var array<int, string>
     */
    private const SUPPORTED_KEYS = [
        'always-true',
        'always-false',
        'has-any-item',
        'min-items',
        'max-items',
        'min-quantity',
        'max-quantity',
        'subtotal-at-least',
        'subtotal-below',
        'subtotal-between',
        'total-at-least',
        'total-below',
        'total-between',
        'has-item',
        'missing-item',
        'item-list-includes-any',
        'item-list-includes-all',
        'has-metadata',
        'metadata-equals',
        'metadata-not-equals',
        'metadata-in',
        'metadata-contains',
        'metadata-flag-true',
        'customer-tag',
        'currency-is',
        'cart-condition-exists',
        'cart-condition-type-exists',
        'day-of-week',
        'date-window',
        'time-window',
        'item-attribute-equals',
        'item-attribute-in',
        'item-quantity-at-least',
        'item-quantity-at-most',
        'item-price-at-least',
        'item-price-at-most',
        'item-total-at-least',
        'item-total-at-most',
        'item-has-condition',
        'item-id-prefix',
    ];

    public function createRules(string $key, array $metadata = []): array
    {
        $context = $this->resolveContext($metadata);

        return match ($key) {
            'always-true' => $this->alwaysTrueRule(),
            'always-false' => $this->alwaysFalseRule(),
            'has-any-item' => $this->hasAnyItemRule(),
            'min-items' => $this->minItemsRule($this->intValue($context, 'min')),
            'max-items' => $this->maxItemsRule($this->intValue($context, 'max')),
            'min-quantity' => $this->minQuantityRule($this->intValue($context, 'min')),
            'max-quantity' => $this->maxQuantityRule($this->intValue($context, 'max')),
            'subtotal-at-least' => $this->subtotalAtLeastRule($this->floatValue($context, 'amount')),
            'subtotal-below' => $this->subtotalBelowRule($this->floatValue($context, 'amount')),
            'subtotal-between' => $this->subtotalBetweenRule($this->floatValue($context, 'min'), $this->floatValue($context, 'max')),
            'total-at-least' => $this->totalAtLeastRule($this->floatValue($context, 'amount')),
            'total-below' => $this->totalBelowRule($this->floatValue($context, 'amount')),
            'total-between' => $this->totalBetweenRule($this->floatValue($context, 'min'), $this->floatValue($context, 'max')),
            'has-item' => $this->hasItemRule($this->stringValue($context, 'id')),
            'missing-item' => $this->missingItemRule($this->stringValue($context, 'id')),
            'item-list-includes-any' => $this->itemListIncludesAnyRule($this->arrayValue($context, 'ids')),
            'item-list-includes-all' => $this->itemListIncludesAllRule($this->arrayValue($context, 'ids')),
            'has-metadata' => $this->hasMetadataRule($this->stringValue($context, 'key')),
            'metadata-equals' => $this->metadataEqualsRule($this->stringValue($context, 'key'), $context['value'] ?? null),
            'metadata-not-equals' => $this->metadataNotEqualsRule($this->stringValue($context, 'key'), $context['value'] ?? null),
            'metadata-in' => $this->metadataInRule($this->stringValue($context, 'key'), $this->arrayValue($context, 'values')),
            'metadata-contains' => $this->metadataContainsRule($this->stringValue($context, 'key'), $context['contains'] ?? null),
            'metadata-flag-true' => $this->metadataFlagTrueRule($this->stringValue($context, 'key')),
            'customer-tag' => $this->customerTagRule($this->stringValue($context, 'tag'), $this->stringValue($context, 'metadata_key', 'customer_tags')),
            'currency-is' => $this->currencyIsRule($this->stringValue($context, 'currency')),
            'cart-condition-exists' => $this->cartConditionExistsRule($this->stringValue($context, 'condition')),
            'cart-condition-type-exists' => $this->cartConditionTypeExistsRule($this->stringValue($context, 'type')),
            'day-of-week' => $this->dayOfWeekRule($this->arrayValue($context, 'days')),
            'date-window' => $this->dateWindowRule($this->stringValue($context, 'start'), $this->stringValue($context, 'end')),
            'time-window' => $this->timeWindowRule($this->stringValue($context, 'start'), $this->stringValue($context, 'end')),
            'item-attribute-equals' => $this->itemAttributeEqualsRule($this->stringValue($context, 'attribute'), $context['value'] ?? null),
            'item-attribute-in' => $this->itemAttributeInRule($this->stringValue($context, 'attribute'), $this->arrayValue($context, 'values')),
            'item-quantity-at-least' => $this->itemQuantityAtLeastRule($this->intValue($context, 'quantity')),
            'item-quantity-at-most' => $this->itemQuantityAtMostRule($this->intValue($context, 'quantity')),
            'item-price-at-least' => $this->itemPriceAtLeastRule($this->floatValue($context, 'amount')),
            'item-price-at-most' => $this->itemPriceAtMostRule($this->floatValue($context, 'amount')),
            'item-total-at-least' => $this->itemTotalAtLeastRule($this->floatValue($context, 'amount')),
            'item-total-at-most' => $this->itemTotalAtMostRule($this->floatValue($context, 'amount')),
            'item-has-condition' => $this->itemHasConditionRule($this->stringValue($context, 'condition')),
            'item-id-prefix' => $this->itemIdPrefixRule($this->stringValue($context, 'prefix')),
            default => throw new InvalidArgumentException("Unsupported rules factory key: {$key}"),
        };
    }

    public function canCreateRules(string $key): bool
    {
        return in_array($key, self::SUPPORTED_KEYS, true);
    }

    public function getAvailableKeys(): array
    {
        return self::SUPPORTED_KEYS;
    }

    /**
     * @param  array<mixed>  $metadata
     * @return array<mixed>
     */
    private function resolveContext(array $metadata): array
    {
        $context = $metadata['context'] ?? $metadata;

        if (! is_array($context)) {
            throw new InvalidArgumentException('Rules factory metadata context must be an array.');
        }

        return $context;
    }

    /**
     * @param  array<mixed>  $context
     */
    private function stringValue(array $context, string $key, ?string $default = null): string
    {
        if (! array_key_exists($key, $context)) {
            if ($default !== null) {
                return $default;
            }

            throw new InvalidArgumentException("Missing context value [{$key}] for built-in rule.");
        }

        $value = $context[$key];

        if (! is_string($value)) {
            throw new InvalidArgumentException("Context value [{$key}] must be a string.");
        }

        return $value;
    }

    /**
     * @param  array<mixed>  $context
     */
    private function intValue(array $context, string $key): int
    {
        $value = $this->requireContextValue($context, $key);

        if (! is_numeric($value)) {
            throw new InvalidArgumentException("Context value [{$key}] must be numeric.");
        }

        return (int) $value;
    }

    /**
     * @param  array<mixed>  $context
     */
    private function floatValue(array $context, string $key): float
    {
        $value = $this->requireContextValue($context, $key);

        if (! is_numeric($value)) {
            throw new InvalidArgumentException("Context value [{$key}] must be numeric.");
        }

        return (float) $value;
    }

    /**
     * @param  array<mixed>  $context
     * @return array<mixed>
     */
    private function arrayValue(array $context, string $key): array
    {
        $value = $this->requireContextValue($context, $key);

        if (! is_array($value)) {
            throw new InvalidArgumentException("Context value [{$key}] must be an array.");
        }

        return $value;
    }

    /**
     * @param  array<mixed>  $context
     */
    private function requireContextValue(array $context, string $key): mixed
    {
        if (! array_key_exists($key, $context)) {
            throw new InvalidArgumentException("Missing context value [{$key}] for built-in rule.");
        }

        return $context[$key];
    }

    /**
     * @return array<callable>
     */
    private function alwaysTrueRule(): array
    {
        return [
            static fn (Cart $cart, ?CartItem $item = null): bool => true,
        ];
    }

    /**
     * @return array<callable>
     */
    private function alwaysFalseRule(): array
    {
        return [
            static fn (Cart $cart, ?CartItem $item = null): bool => false,
        ];
    }

    /**
     * @return array<callable>
     */
    private function hasAnyItemRule(): array
    {
        return [
            static fn (Cart $cart, ?CartItem $item = null): bool => ! $cart->isEmpty(),
        ];
    }

    /**
     * @return array<callable>
     */
    private function minItemsRule(int $minimum): array
    {
        return [
            static fn (Cart $cart, ?CartItem $item = null): bool => $cart->countItems() >= $minimum,
        ];
    }

    /**
     * @return array<callable>
     */
    private function maxItemsRule(int $maximum): array
    {
        return [
            static fn (Cart $cart, ?CartItem $item = null): bool => $cart->countItems() <= $maximum,
        ];
    }

    /**
     * @return array<callable>
     */
    private function minQuantityRule(int $minimum): array
    {
        return [
            static fn (Cart $cart, ?CartItem $item = null): bool => $cart->getTotalQuantity() >= $minimum,
        ];
    }

    /**
     * @return array<callable>
     */
    private function maxQuantityRule(int $maximum): array
    {
        return [
            static fn (Cart $cart, ?CartItem $item = null): bool => $cart->getTotalQuantity() <= $maximum,
        ];
    }

    /**
     * @return array<callable>
     */
    private function subtotalAtLeastRule(float $amount): array
    {
        return [
            static fn (Cart $cart, ?CartItem $item = null): bool => $cart->getRawSubtotalWithoutConditions() >= $amount,
        ];
    }

    /**
     * @return array<callable>
     */
    private function subtotalBelowRule(float $amount): array
    {
        return [
            static fn (Cart $cart, ?CartItem $item = null): bool => $cart->getRawSubtotalWithoutConditions() < $amount,
        ];
    }

    /**
     * @return array<callable>
     */
    private function subtotalBetweenRule(float $min, float $max): array
    {
        if ($max < $min) {
            throw new InvalidArgumentException('Context value [max] must be greater than or equal to [min].');
        }

        return [
            static fn (Cart $cart, ?CartItem $item = null): bool => $cart->getRawSubtotalWithoutConditions() >= $min && $cart->getRawSubtotalWithoutConditions() <= $max,
        ];
    }

    /**
     * @return array<callable>
     */
    private function totalAtLeastRule(float $amount): array
    {
        return [
            static fn (Cart $cart, ?CartItem $item = null): bool => $cart->getRawTotalWithoutConditions() >= $amount,
        ];
    }

    /**
     * @return array<callable>
     */
    private function totalBelowRule(float $amount): array
    {
        return [
            static fn (Cart $cart, ?CartItem $item = null): bool => $cart->getRawTotalWithoutConditions() < $amount,
        ];
    }

    /**
     * @return array<callable>
     */
    private function totalBetweenRule(float $min, float $max): array
    {
        if ($max < $min) {
            throw new InvalidArgumentException('Context value [max] must be greater than or equal to [min].');
        }

        return [
            static fn (Cart $cart, ?CartItem $item = null): bool => $cart->getRawTotalWithoutConditions() >= $min && $cart->getRawTotalWithoutConditions() <= $max,
        ];
    }

    /**
     * @return array<callable>
     */
    private function hasItemRule(string $id): array
    {
        return [
            static fn (Cart $cart, ?CartItem $item = null): bool => $cart->has($id),
        ];
    }

    /**
     * @return array<callable>
     */
    private function missingItemRule(string $id): array
    {
        return [
            static fn (Cart $cart, ?CartItem $item = null): bool => ! $cart->has($id),
        ];
    }

    /**
     * @param  array<mixed>  $ids
     * @return array<callable>
     */
    private function itemListIncludesAnyRule(array $ids): array
    {
        $expected = array_map(static fn (mixed $value): string => (string) $value, $ids);

        return [
            static function (Cart $cart, ?CartItem $item = null) use ($expected): bool {
                $cartIds = $cart->getItems()->keys()->map(static fn (mixed $value): string => (string) $value);

                return $cartIds->intersect($expected)->isNotEmpty();
            },
        ];
    }

    /**
     * @param  array<mixed>  $ids
     * @return array<callable>
     */
    private function itemListIncludesAllRule(array $ids): array
    {
        $expected = array_map(static fn (mixed $value): string => (string) $value, $ids);

        return [
            static function (Cart $cart, ?CartItem $item = null) use ($expected): bool {
                $cartIds = $cart->getItems()->keys()->map(static fn (mixed $value): string => (string) $value);

                return empty(array_diff($expected, $cartIds->all()));
            },
        ];
    }

    /**
     * @return array<callable>
     */
    private function hasMetadataRule(string $key): array
    {
        return [
            static fn (Cart $cart, ?CartItem $item = null): bool => $cart->hasMetadata($key),
        ];
    }

    /**
     * @return array<callable>
     */
    private function metadataEqualsRule(string $key, mixed $value): array
    {
        return [
            static fn (Cart $cart, ?CartItem $item = null): bool => $cart->getMetadata($key) === $value,
        ];
    }

    /**
     * @return array<callable>
     */
    private function metadataNotEqualsRule(string $key, mixed $value): array
    {
        return [
            static fn (Cart $cart, ?CartItem $item = null): bool => $cart->getMetadata($key) !== $value,
        ];
    }

    /**
     * @param  array<mixed>  $values
     * @return array<callable>
     */
    private function metadataInRule(string $key, array $values): array
    {
        return [
            static function (Cart $cart, ?CartItem $item = null) use ($key, $values): bool {
                $metadata = $cart->getMetadata($key);

                return in_array($metadata, $values, true);
            },
        ];
    }

    /**
     * @return array<callable>
     */
    private function metadataContainsRule(string $key, mixed $needle): array
    {
        return [
            static function (Cart $cart, ?CartItem $item = null) use ($key, $needle): bool {
                $metadata = $cart->getMetadata($key);

                if (is_array($metadata)) {
                    return in_array($needle, $metadata, true);
                }

                if (is_string($metadata)) {
                    return is_string($needle) ? str_contains($metadata, $needle) : false;
                }

                return false;
            },
        ];
    }

    /**
     * @return array<callable>
     */
    private function metadataFlagTrueRule(string $key): array
    {
        return [
            static fn (Cart $cart, ?CartItem $item = null): bool => (bool) $cart->getMetadata($key) === true,
        ];
    }

    /**
     * @return array<callable>
     */
    private function customerTagRule(string $tag, string $metadataKey): array
    {
        return [
            static function (Cart $cart, ?CartItem $item = null) use ($tag, $metadataKey): bool {
                $metadata = $cart->getMetadata($metadataKey);

                if (is_array($metadata)) {
                    return in_array($tag, $metadata, true);
                }

                if (is_string($metadata)) {
                    $segments = array_map('trim', explode(',', $metadata));

                    return in_array($tag, $segments, true);
                }

                return false;
            },
        ];
    }

    /**
     * @return array<callable>
     */
    private function currencyIsRule(string $currency): array
    {
        return [
            static fn (Cart $cart, ?CartItem $item = null): bool => mb_strtoupper(config('cart.money.default_currency', 'USD')) === mb_strtoupper($currency),
        ];
    }

    /**
     * @return array<callable>
     */
    private function cartConditionExistsRule(string $condition): array
    {
        return [
            static fn (Cart $cart, ?CartItem $item = null): bool => $cart->getConditions()->has($condition),
        ];
    }

    /**
     * @return array<callable>
     */
    private function cartConditionTypeExistsRule(string $type): array
    {
        return [
            static fn (Cart $cart, ?CartItem $item = null): bool => $cart->getConditions()->byType($type)->isNotEmpty(),
        ];
    }

    /**
     * @param  array<mixed>  $days
     * @return array<callable>
     */
    private function dayOfWeekRule(array $days): array
    {
        $normalized = [];

        foreach ($days as $day) {
            if (is_numeric($day)) {
                $normalized[] = ((int) $day) % 7;

                continue;
            }

            $lookup = match (mb_strtolower((string) $day)) {
                'sun', 'sunday' => CarbonInterface::SUNDAY,
                'mon', 'monday' => CarbonInterface::MONDAY,
                'tue', 'tuesday' => CarbonInterface::TUESDAY,
                'wed', 'wednesday' => CarbonInterface::WEDNESDAY,
                'thu', 'thursday' => CarbonInterface::THURSDAY,
                'fri', 'friday' => CarbonInterface::FRIDAY,
                'sat', 'saturday' => CarbonInterface::SATURDAY,
                default => null,
            };

            if ($lookup === null) {
                throw new InvalidArgumentException('Invalid day name provided for day-of-week rule.');
            }

            $normalized[] = $lookup;
        }

        $normalized = array_values(array_unique($normalized));

        if ($normalized === []) {
            throw new InvalidArgumentException('At least one valid day must be provided for day-of-week rule.');
        }

        return [
            static fn (Cart $cart, ?CartItem $item = null): bool => in_array(CarbonImmutable::now()->dayOfWeek, $normalized, true),
        ];
    }

    /**
     * @return array<callable>
     */
    private function dateWindowRule(string $start, string $end): array
    {
        try {
            $startDate = CarbonImmutable::parse($start);
            $endDate = CarbonImmutable::parse($end);
        } catch (Throwable $exception) {
            throw new InvalidArgumentException('Invalid date window context provided.', 0, $exception);
        }

        if ($endDate->lessThan($startDate)) {
            throw new InvalidArgumentException('Context value [end] must be after or equal to [start].');
        }

        return [
            static fn (Cart $cart, ?CartItem $item = null): bool => CarbonImmutable::now()->betweenIncluded($startDate, $endDate),
        ];
    }

    /**
     * @return array<callable>
     */
    private function timeWindowRule(string $start, string $end): array
    {
        $startMinutes = $this->parseMinutes($start, 'start');
        $endMinutes = $this->parseMinutes($end, 'end');

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

    private function parseMinutes(string $time, string $key): int
    {
        if (! preg_match('/^\d{2}:\d{2}$/', $time)) {
            throw new InvalidArgumentException("Context value [{$key}] must be in HH:MM format.");
        }

        [$hour, $minute] = array_map('intval', explode(':', $time));

        if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
            throw new InvalidArgumentException("Context value [{$key}] must be a valid time.");
        }

        return ($hour * 60) + $minute;
    }

    /**
     * @return array<callable>
     */
    private function itemAttributeEqualsRule(string $attribute, mixed $value): array
    {
        return [
            static function (Cart $cart, ?CartItem $item = null) use ($attribute, $value): bool {
                if ($item instanceof CartItem) {
                    return $item->attributes->has($attribute) && $item->attributes->get($attribute) === $value;
                }

                return $cart->getItems()->contains(
                    static fn (CartItem $cartItem): bool => $cartItem->attributes->has($attribute) && $cartItem->attributes->get($attribute) === $value
                );
            },
        ];
    }

    /**
     * @param  array<mixed>  $values
     * @return array<callable>
     */
    private function itemAttributeInRule(string $attribute, array $values): array
    {
        return [
            static function (Cart $cart, ?CartItem $item = null) use ($attribute, $values): bool {
                $acceptable = $values;

                $matches = static fn (CartItem $cartItem): bool => $cartItem->attributes->has($attribute)
                    && in_array($cartItem->attributes->get($attribute), $acceptable, true);

                if ($item instanceof CartItem) {
                    return $matches($item);
                }

                return $cart->getItems()->contains($matches);
            },
        ];
    }

    /**
     * @return array<callable>
     */
    private function itemQuantityAtLeastRule(int $quantity): array
    {
        return [
            static fn (Cart $cart, ?CartItem $item = null): bool => $item instanceof CartItem
                ? $item->quantity >= $quantity
                : $cart->getItems()->contains(static fn (CartItem $cartItem): bool => $cartItem->quantity >= $quantity),
        ];
    }

    /**
     * @return array<callable>
     */
    private function itemQuantityAtMostRule(int $quantity): array
    {
        return [
            static fn (Cart $cart, ?CartItem $item = null): bool => $item instanceof CartItem
                ? $item->quantity <= $quantity
                : $cart->getItems()->contains(static fn (CartItem $cartItem): bool => $cartItem->quantity <= $quantity),
        ];
    }

    /**
     * @return array<callable>
     */
    private function itemPriceAtLeastRule(float $amount): array
    {
        return [
            static fn (Cart $cart, ?CartItem $item = null): bool => $item instanceof CartItem
                ? $item->price >= $amount
                : $cart->getItems()->contains(static fn (CartItem $cartItem): bool => $cartItem->price >= $amount),
        ];
    }

    /**
     * @return array<callable>
     */
    private function itemPriceAtMostRule(float $amount): array
    {
        return [
            static fn (Cart $cart, ?CartItem $item = null): bool => $item instanceof CartItem
                ? $item->price <= $amount
                : $cart->getItems()->contains(static fn (CartItem $cartItem): bool => $cartItem->price <= $amount),
        ];
    }

    /**
     * @return array<callable>
     */
    private function itemTotalAtLeastRule(float $amount): array
    {
        return [
            static fn (Cart $cart, ?CartItem $item = null): bool => $item instanceof CartItem
                ? ($item->price * $item->quantity) >= $amount
                : $cart->getItems()->contains(static fn (CartItem $cartItem): bool => ($cartItem->price * $cartItem->quantity) >= $amount),
        ];
    }

    /**
     * @return array<callable>
     */
    private function itemTotalAtMostRule(float $amount): array
    {
        return [
            static fn (Cart $cart, ?CartItem $item = null): bool => $item instanceof CartItem
                ? ($item->price * $item->quantity) <= $amount
                : $cart->getItems()->contains(static fn (CartItem $cartItem): bool => ($cartItem->price * $cartItem->quantity) <= $amount),
        ];
    }

    /**
     * @return array<callable>
     */
    private function itemHasConditionRule(string $condition): array
    {
        return [
            static fn (Cart $cart, ?CartItem $item = null): bool => $item instanceof CartItem
                ? $item->conditions->has($condition)
                : $cart->getItems()->contains(static fn (CartItem $cartItem): bool => $cartItem->conditions->has($condition)),
        ];
    }

    /**
     * @return array<callable>
     */
    private function itemIdPrefixRule(string $prefix): array
    {
        return [
            static fn (Cart $cart, ?CartItem $item = null): bool => $item instanceof CartItem
                ? str_starts_with($item->id, $prefix)
                : $cart->getItems()->contains(static fn (CartItem $cartItem): bool => str_starts_with($cartItem->id, $prefix)),
        ];
    }
}
