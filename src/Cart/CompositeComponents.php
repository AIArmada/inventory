<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Cart;

use AIArmada\Cart\Models\CartItem;
use AIArmada\CommerceSupport\Support\CompositeComponents as ComponentSnapshot;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\CommerceSupport\Support\OwnerScopeConfig;
use AIArmada\Inventory\Contracts\InventoryableInterface;
use AIArmada\Inventory\Traits\HasInventory;
use AIArmada\Orders\Models\OrderItem;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Resolves inventory for a frozen component snapshot.
 *
 * @phpstan-import-type Component from ComponentSnapshot
 *
 * A line containing the `components` key is a composite sale line. Its
 * parent is commercial identity only; inventory work always targets the
 * component models listed in the snapshot.
 */
final class CompositeComponents
{
    public const string ATTRIBUTE = 'components';

    /**
     * Return the component snapshot for a cart line, or null when the line
     * is not composite.
     *
     * @return list<Component>|null
     */
    public static function fromCartItem(CartItem $item): ?array
    {
        if (! $item->attributes->has(self::ATTRIBUTE)) {
            return null;
        }

        return ComponentSnapshot::read($item->attributes->get(self::ATTRIBUTE));
    }

    /**
     * Return the component snapshot for an order line, or null when the line
     * is not composite.
     *
     * @return list<Component>|null
     */
    public static function fromOrderItem(OrderItem $item): ?array
    {
        $metadata = $item->metadata;

        if (! is_array($metadata) || ! array_key_exists(self::ATTRIBUTE, $metadata)) {
            return null;
        }

        return ComponentSnapshot::read($metadata[self::ATTRIBUTE]);
    }

    /**
     * Resolve the actual inventory model for a component snapshot entry.
     *
     * Resolution only accepts explicitly inventoryable model classes and
     * always goes through that model's owner scope. A missing or foreign
     * model resolves to null instead of falling back to the parent.
     *
     * @param  Component  $component
     */
    public static function resolveInventoryModel(array $component): ?Model
    {
        $type = $component['inventoryable_type'];
        $class = Relation::getMorphedModel($type) ?? $type;

        return self::isAllowedInventoryableClass($class)
            ? self::findOwned($class, $component['inventoryable_id'])
            : null;
    }

    /**
     * Check whether a model is a composite parent that owns no stock.
     */
    public static function isCompositeModel(Model $model): bool
    {
        $type = $model->getAttribute('type');

        if ($type instanceof BackedEnum) {
            $type = $type->value;
        }

        return $type === 'composite';
    }

    public static function tracksInventory(Model $model): bool
    {
        if (method_exists($model, 'tracksInventory')) {
            return (bool) $model->tracksInventory();
        }

        return true;
    }

    /**
     * @param  class-string<Model>  $class
     */
    private static function findOwned(string $class, string $id): ?Model
    {
        /** @var Model $probe */
        $probe = new $class;

        /** @var Builder<Model> $query */
        $query = $class::query();

        self::applyOwnerScope($query, $probe);

        return $query->whereKey($id)->first();
    }

    /**
     * @param  Builder<Model>  $query
     */
    private static function applyOwnerScope(Builder $query, Model $model): void
    {
        if (! method_exists($model, 'ownerScopeConfig')) {
            return;
        }

        /** @var mixed $config */
        $config = $model->ownerScopeConfig();

        if (! $config instanceof OwnerScopeConfig || ! $config->enabled) {
            return;
        }

        $owner = OwnerContext::resolve();

        // Leave the model's existing global scope in place when there is no
        // explicit context; it will raise the canonical owner error instead of
        // silently running global work.
        if ($owner === null && ! OwnerContext::isExplicitGlobal()) {
            return;
        }

        $query->withoutGlobalScope(OwnerScope::class);

        OwnerQuery::applyToEloquentBuilder(
            $query,
            $owner,
            $config->includeGlobal,
            $config->ownerTypeColumn,
            $config->ownerIdColumn,
        );
    }

    /**
     * @phpstan-assert-if-true class-string<Model> $class
     */
    private static function isAllowedInventoryableClass(string $class): bool
    {
        if (! class_exists($class) || ! is_a($class, Model::class, true)) {
            return false;
        }

        $candidates = array_filter([
            config('inventory.models.variant'),
            config('inventory.models.product'),
            class_exists('AIArmada\\Products\\Models\\Variant') ? 'AIArmada\\Products\\Models\\Variant' : null,
            class_exists('AIArmada\\Products\\Models\\Product') ? 'AIArmada\\Products\\Models\\Product' : null,
        ], static fn (mixed $candidate): bool => is_string($candidate) && class_exists($candidate));

        foreach ($candidates as $candidate) {
            if ($class === $candidate || is_a($class, $candidate, true)) {
                return true;
            }
        }

        if (is_a($class, InventoryableInterface::class, true)) {
            return true;
        }

        if (interface_exists('AIArmada\\Products\\Contracts\\Inventoryable')
            && is_a($class, 'AIArmada\\Products\\Contracts\\Inventoryable', true)) {
            return true;
        }

        return in_array(HasInventory::class, class_uses_recursive($class), true);
    }
}
