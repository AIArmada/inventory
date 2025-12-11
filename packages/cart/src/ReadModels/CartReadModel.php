<?php

declare(strict_types=1);

namespace AIArmada\Cart\ReadModels;

use DateTimeInterface;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\ConnectionInterface;

/**
 * Cart read model optimized for query operations.
 *
 * Provides denormalized views of cart data for fast reads.
 * Updated via projectors listening to domain events.
 */
final class CartReadModel
{
    private const string CACHE_PREFIX = 'cart:read:';

    private const int CACHE_TTL = 3600; // 1 hour

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly CacheRepository $cache
    ) {}

    /**
     * Get cart summary by ID.
     *
     * @return array{
     *     id: string,
     *     identifier: string,
     *     instance: string,
     *     item_count: int,
     *     total_quantity: int,
     *     subtotal_cents: int,
     *     total_cents: int,
     *     savings_cents: int,
     *     condition_count: int,
     *     created_at: string,
     *     updated_at: string
     * }|null
     */
    public function getCartSummary(string $cartId): ?array
    {
        $cacheKey = self::CACHE_PREFIX . $cartId . ':summary';

        /** @var array{id: string, identifier: string, instance: string, item_count: int, total_quantity: int, subtotal_cents: int, total_cents: int, savings_cents: int, condition_count: int, created_at: string, updated_at: string}|null $cached */
        $cached = $this->cache->get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $result = $this->buildCartSummary($cartId);

        if ($result !== null) {
            $this->cache->put($cacheKey, $result, self::CACHE_TTL);
        }

        return $result;
    }

    /**
     * Get full cart details with items and conditions.
     *
     * @return array<string, mixed>|null
     */
    public function getCartDetails(string $cartId): ?array
    {
        $tableName = config('cart.database.table', 'carts');

        $cart = $this->connection->table($tableName)
            ->where('id', $cartId)
            ->first();

        if ($cart === null) {
            return null;
        }

        $items = is_string($cart->items) ? json_decode($cart->items, true) : ($cart->items ?? []);
        $conditions = is_string($cart->conditions) ? json_decode($cart->conditions, true) : ($cart->conditions ?? []);
        $metadata = is_string($cart->metadata) ? json_decode($cart->metadata, true) : ($cart->metadata ?? []);

        return [
            'id' => $cart->id,
            'identifier' => $cart->identifier,
            'instance' => $cart->instance,
            'items' => array_values($items),
            'conditions' => array_values($conditions),
            'metadata' => $metadata,
            'version' => $cart->version ?? 1,
            'created_at' => $cart->created_at,
            'updated_at' => $cart->updated_at,
        ];
    }

    /**
     * Get abandoned carts for recovery campaigns.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAbandonedCarts(
        DateTimeInterface $olderThan,
        ?int $minValueCents = null,
        int $limit = 100
    ): array {
        $tableName = config('cart.database.table', 'carts');

        $query = $this->connection->table($tableName)
            ->whereNotNull('checkout_abandoned_at')
            ->where('checkout_abandoned_at', '<=', $olderThan)
            ->whereNull('recovered_at')
            ->orderBy('checkout_abandoned_at', 'desc')
            ->limit($limit);

        $carts = $query->get();

        $results = [];
        foreach ($carts as $cart) {
            $items = is_string($cart->items) ? json_decode($cart->items, true) : ($cart->items ?? []);

            $totalCents = 0;
            $totalQuantity = 0;
            foreach ($items as $item) {
                $qty = $item['quantity'] ?? 1;
                $price = $item['price'] ?? 0;
                $totalQuantity += $qty;
                $totalCents += $price * $qty;
            }

            if ($minValueCents !== null && $totalCents < $minValueCents) {
                continue;
            }

            $results[] = [
                'id' => $cart->id,
                'identifier' => $cart->identifier,
                'instance' => $cart->instance,
                'item_count' => count($items),
                'total_quantity' => $totalQuantity,
                'total_cents' => $totalCents,
                'checkout_abandoned_at' => $cart->checkout_abandoned_at,
                'recovery_attempts' => $cart->recovery_attempts ?? 0,
            ];
        }

        return $results;
    }

    /**
     * Search carts with filters.
     *
     * @return array{data: array<int, array<string, mixed>>, total: int}
     */
    public function searchCarts(
        ?string $identifier = null,
        ?string $instance = null,
        ?DateTimeInterface $createdAfter = null,
        ?DateTimeInterface $createdBefore = null,
        ?int $minItems = null,
        int $limit = 50,
        int $offset = 0
    ): array {
        $tableName = config('cart.database.table', 'carts');

        $query = $this->connection->table($tableName);

        if ($identifier !== null) {
            $query->where('identifier', 'like', "%{$identifier}%");
        }

        if ($instance !== null) {
            $query->where('instance', $instance);
        }

        if ($createdAfter !== null) {
            $query->where('created_at', '>=', $createdAfter);
        }

        if ($createdBefore !== null) {
            $query->where('created_at', '<=', $createdBefore);
        }

        $total = $query->count();

        $carts = $query
            ->orderBy('updated_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        $results = [];
        foreach ($carts as $cart) {
            $items = is_string($cart->items) ? json_decode($cart->items, true) : ($cart->items ?? []);
            $itemCount = count($items);

            if ($minItems !== null && $itemCount < $minItems) {
                continue;
            }

            $results[] = [
                'id' => $cart->id,
                'identifier' => $cart->identifier,
                'instance' => $cart->instance,
                'item_count' => $itemCount,
                'created_at' => $cart->created_at,
                'updated_at' => $cart->updated_at,
            ];
        }

        return [
            'data' => $results,
            'total' => $total,
        ];
    }

    /**
     * Get cart statistics for dashboard.
     *
     * @return array{
     *     active_carts: int,
     *     abandoned_carts: int,
     *     recovered_carts: int,
     *     total_value_cents: int,
     *     avg_items_per_cart: float
     * }
     */
    public function getCartStatistics(DateTimeInterface $since): array
    {
        $tableName = config('cart.database.table', 'carts');

        $activeCarts = $this->connection->table($tableName)
            ->where('updated_at', '>=', $since)
            ->whereNull('checkout_abandoned_at')
            ->count();

        $abandonedCarts = $this->connection->table($tableName)
            ->whereNotNull('checkout_abandoned_at')
            ->where('checkout_abandoned_at', '>=', $since)
            ->count();

        $recoveredCarts = $this->connection->table($tableName)
            ->whereNotNull('recovered_at')
            ->where('recovered_at', '>=', $since)
            ->count();

        // Calculate total value and average items from recent carts
        $recentCarts = $this->connection->table($tableName)
            ->where('updated_at', '>=', $since)
            ->get(['items']);

        $totalValueCents = 0;
        $totalItems = 0;
        $cartCount = count($recentCarts);

        foreach ($recentCarts as $cart) {
            $items = is_string($cart->items) ? json_decode($cart->items, true) : ($cart->items ?? []);
            $totalItems += count($items);

            foreach ($items as $item) {
                $qty = $item['quantity'] ?? 1;
                $price = $item['price'] ?? 0;
                $totalValueCents += $price * $qty;
            }
        }

        return [
            'active_carts' => $activeCarts,
            'abandoned_carts' => $abandonedCarts,
            'recovered_carts' => $recoveredCarts,
            'total_value_cents' => $totalValueCents,
            'avg_items_per_cart' => $cartCount > 0 ? round($totalItems / $cartCount, 2) : 0.0,
        ];
    }

    /**
     * Invalidate cached cart data.
     */
    public function invalidateCache(string $cartId): void
    {
        $this->cache->forget(self::CACHE_PREFIX . $cartId . ':summary');
    }

    /**
     * Build cart summary from database.
     *
     * @return array{
     *     id: string,
     *     identifier: string,
     *     instance: string,
     *     item_count: int,
     *     total_quantity: int,
     *     subtotal_cents: int,
     *     total_cents: int,
     *     savings_cents: int,
     *     condition_count: int,
     *     created_at: string,
     *     updated_at: string
     * }|null
     */
    private function buildCartSummary(string $cartId): ?array
    {
        $tableName = config('cart.database.table', 'carts');

        $cart = $this->connection->table($tableName)
            ->where('id', $cartId)
            ->first();

        if ($cart === null) {
            return null;
        }

        $items = is_string($cart->items) ? json_decode($cart->items, true) : ($cart->items ?? []);
        $conditions = is_string($cart->conditions) ? json_decode($cart->conditions, true) : ($cart->conditions ?? []);

        $itemCount = count($items);
        $totalQuantity = 0;
        $subtotalCents = 0;

        foreach ($items as $item) {
            $qty = $item['quantity'] ?? 1;
            $price = $item['price'] ?? 0;
            $totalQuantity += $qty;
            $subtotalCents += $price * $qty;
        }

        $savingsCents = 0;
        foreach ($conditions as $condition) {
            if (($condition['type'] ?? '') === 'discount') {
                $value = $condition['value'] ?? '0';
                if (str_contains($value, '%')) {
                    $savingsCents += (int) ($subtotalCents * (float) str_replace('%', '', $value) / 100);
                } else {
                    $savingsCents += (int) abs((float) $value * 100);
                }
            }
        }

        return [
            'id' => $cart->id,
            'identifier' => $cart->identifier,
            'instance' => $cart->instance,
            'item_count' => $itemCount,
            'total_quantity' => $totalQuantity,
            'subtotal_cents' => $subtotalCents,
            'total_cents' => max(0, $subtotalCents - $savingsCents),
            'savings_cents' => $savingsCents,
            'condition_count' => count($conditions),
            'created_at' => $cart->created_at,
            'updated_at' => $cart->updated_at,
        ];
    }
}
