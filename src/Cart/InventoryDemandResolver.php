<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Cart;

use AIArmada\Cart\Models\CartItem;
use AIArmada\Orders\Models\OrderItem;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Builds one aggregated demand row per actual inventory model.
 *
 * Composite lines expand to their component quantities multiplied by the
 * number of packages. Standalone lines for the same model merge into the
 * same row so allocation and deduction happen exactly once for that stock
 * identity.
 */
/** @phpstan-import-type Component from \AIArmada\CommerceSupport\Support\CompositeComponents */
final class InventoryDemandResolver
{
    /**
     * @param  iterable<CartItem>  $items
     * @return array<string, array{
     *     model: Model,
     *     quantity: int,
     *     sources: array<string, array{name: string, quantity: int}>
     * }>
     */
    public static function forCart(iterable $items): array
    {
        $demand = [];

        foreach ($items as $item) {
            if ($item instanceof CartItem) {
                self::addCartItem($demand, $item);
            }
        }

        return self::sorted($demand);
    }

    /**
     * @param  iterable<OrderItem>  $items
     * @return array<string, array{
     *     model: Model,
     *     quantity: int,
     *     sources: array<string, array{name: string, quantity: int}>
     * }>
     */
    public static function forOrder(iterable $items): array
    {
        $demand = [];

        foreach ($items as $item) {
            $components = CompositeComponents::fromOrderItem($item);

            if ($components === null) {
                self::addOrdinaryOrderItem($demand, $item);

                continue;
            }

            self::addComponents($demand, $components, $item->quantity, (string) $item->getKey(), $item->name);
        }

        return self::sorted($demand);
    }

    /**
     * @param  array<string, array{
     *     model: Model,
     *     quantity: int,
     *     sources: array<string, array{name: string, quantity: int}>
     * }>  $demand
     */
    private static function addCartItem(array &$demand, CartItem $item): void
    {
        $components = CompositeComponents::fromCartItem($item);

        if ($components !== null) {
            self::addComponents($demand, $components, (int) $item->quantity, (string) $item->id, $item->name);

            return;
        }

        $model = $item->getAssociatedModel();

        if (! $model instanceof Model || CompositeComponents::isCompositeModel($model)) {
            return;
        }

        self::addDemand($demand, $model, (int) $item->quantity, (string) $item->id, $item->name);
    }

    /**
     * @param  array<string, array{
     *     model: Model,
     *     quantity: int,
     *     sources: array<string, array{name: string, quantity: int}>
     * }>  $demand
     */
    private static function addOrdinaryOrderItem(array &$demand, OrderItem $item): void
    {
        $model = $item->purchasable;

        if (! $model instanceof Model || CompositeComponents::isCompositeModel($model)) {
            return;
        }

        if (! CompositeComponents::tracksInventory($model)) {
            return;
        }

        self::addDemand(
            $demand,
            $model,
            (int) $item->quantity,
            (string) $item->getKey(),
            $item->name,
        );
    }

    /**
     * @param  array<string, array{
     *     model: Model,
     *     quantity: int,
     *     sources: array<string, array{name: string, quantity: int}>
     * }>  $demand
     * @param  list<Component>  $components
     */
    private static function addComponents(
        array &$demand,
        array $components,
        int $packageQuantity,
        string $sourceId,
        string $sourceName,
    ): void {
        if ($packageQuantity <= 0) {
            return;
        }

        foreach ($components as $component) {
            if (! $component['tracks_inventory']) {
                continue;
            }

            $model = CompositeComponents::resolveInventoryModel($component);

            if (! $model instanceof Model) {
                throw new RuntimeException('Inventory for a composite component could not be resolved.');
            }

            $quantity = $component['quantity'] * $packageQuantity;

            self::addDemand($demand, $model, $quantity, $sourceId, $sourceName);
        }
    }

    /**
     * @param  array<string, array{
     *     model: Model,
     *     quantity: int,
     *     sources: array<string, array{name: string, quantity: int}>
     * }>  $demand
     */
    private static function addDemand(
        array &$demand,
        Model $model,
        int $quantity,
        string $sourceId,
        string $sourceName,
    ): void {
        if ($quantity <= 0) {
            return;
        }

        $key = $model->getMorphClass() . ':' . $model->getKey();

        if (! isset($demand[$key])) {
            $demand[$key] = [
                'model' => $model,
                'quantity' => 0,
                'sources' => [],
            ];
        }

        $demand[$key]['quantity'] += $quantity;

        $source = $demand[$key]['sources'][$sourceId] ?? [
            'name' => $sourceName,
            'quantity' => 0,
        ];
        $source['quantity'] += $quantity;
        $demand[$key]['sources'][$sourceId] = $source;
    }

    /**
     * @param  array<string, array{
     *     model: Model,
     *     quantity: int,
     *     sources: array<string, array{name: string, quantity: int}>
     * }>  $demand
     * @return array<string, array{
     *     model: Model,
     *     quantity: int,
     *     sources: array<string, array{name: string, quantity: int}>
     * }>
     */
    private static function sorted(array $demand): array
    {
        ksort($demand);

        foreach ($demand as &$entry) {
            ksort($entry['sources']);
        }

        unset($entry);

        return $demand;
    }
}
