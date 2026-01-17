<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Services;

use AIArmada\Cart\Cart as BaseCart;
use AIArmada\Cart\Conditions\CartCondition as BaseCartCondition;
use AIArmada\Cart\Conditions\Pipeline\ConditionPipeline;
use AIArmada\Cart\Conditions\Pipeline\ConditionPipelineContext;
use AIArmada\Cart\Models\CartItem as BaseCartItem;
use AIArmada\FilamentCart\Models\Cart;
use AIArmada\FilamentCart\Models\CartCondition;
use AIArmada\FilamentCart\Models\CartItem;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class NormalizedCartSynchronizer
{
    public function syncFromCart(BaseCart $cart): void
    {
        DB::transaction(function () use ($cart): void {
            $identifier = $cart->getIdentifier();
            $instance = $cart->instance();

            $owner = Cart::resolveCurrentOwner();
            $ownerKey = Cart::resolveOwnerKey($owner);

            $items = $cart->getItems();
            $conditions = $cart->getStoredConditions();

            $currency = $this->resolveCurrency();

            $cartModel = Cart::query()->forOwner($owner)->firstOrNew([
                'identifier' => $identifier,
                'instance' => $instance,
                'owner_key' => $ownerKey,
            ]);

            if (Cart::ownerScopingEnabled() && $owner !== null) {
                $cartModel->assignOwner($owner);
            }

            $cartModel->items = $items->isEmpty() ? null : $items->toArray();
            $cartModel->conditions = $conditions->isEmpty() ? null : $conditions->toArray();
            $cartModel->items_count = $cart->countItems();
            $cartModel->quantity = $cart->getTotalQuantity();
            $pipeline = new ConditionPipeline;
            $pipelineResult = $pipeline->process(new ConditionPipelineContext($cart, $conditions));

            $subtotalWithoutConditions = (int) $cart->subtotalWithoutConditions()->getAmount();
            $cartModel->subtotal = $subtotalWithoutConditions;
            $cartModel->total = $pipelineResult->total();
            $cartModel->savings = max(0, $subtotalWithoutConditions - $cartModel->total);
            $cartModel->currency = $currency;
            $cartModel->save();

            $itemModels = $this->syncItems($cartModel, $items);
            $this->syncConditions($cartModel, $conditions, $itemModels, $items->all());
        });
    }

    /**
     * Delete the normalized cart representation from database
     * This is called when the cart is destroyed or cleared
     */
    public function deleteNormalizedCart(string $identifier, string $instance): void
    {
        $owner = Cart::resolveCurrentOwner();
        $ownerKey = Cart::resolveOwnerKey($owner);

        $cartModel = Cart::query()->forOwner($owner)
            ->where('identifier', $identifier)
            ->where('instance', $instance)
            ->where('owner_key', $ownerKey)
            ->first();

        if (! $cartModel) {
            return;
        }

        CartCondition::query()->where('cart_id', $cartModel->id)->delete();
        CartItem::query()->where('cart_id', $cartModel->id)->delete();
        $cartModel->delete();
    }

    /**
     * @return array<string, CartItem>
     */
    /** @param Collection<int, BaseCartItem> $items */
    private function syncItems(Cart $cartModel, Collection $items): array
    {
        $persisted = [];
        $storedItemIds = [];

        foreach ($items as $item) {
            \assert($item instanceof BaseCartItem);

            $attributes = $item->attributes->toArray();
            $conditions = $item->conditions->isEmpty() ? null : $item->conditions->toArray();

            $cartItemModel = CartItem::query()->updateOrCreate(
                [
                    'cart_id' => $cartModel->id,
                    'item_id' => $item->id,
                ],
                [
                    'name' => $item->name,
                    'price' => (int) $item->getRawPriceWithoutConditions(),
                    'quantity' => $item->quantity,
                    'attributes' => empty($attributes) ? null : $attributes,
                    'conditions' => $conditions,
                    'associated_model' => $this->resolveAssociatedModel($item->associatedModel),
                ]
            );

            $persisted[$item->id] = $cartItemModel;
            $storedItemIds[] = $item->id;
        }

        if ($storedItemIds !== []) {
            CartItem::query()
                ->where('cart_id', $cartModel->id)
                ->whereNotIn('item_id', $storedItemIds)
                ->delete();
        } else {
            CartItem::query()->where('cart_id', $cartModel->id)->delete();
        }

        return $persisted;
    }

    private function syncConditions(Cart $cartModel, Collection $conditions, array $itemModels, array $originalItems): void
    {
        $persistedKeys = [];

        foreach ($conditions as $condition) {
            \assert($condition instanceof BaseCartCondition);
            $persistedKeys[] = $this->persistCondition(
                cartModel: $cartModel,
                condition: $condition,
                cartItemModel: null,
                itemId: null
            );
        }

        foreach ($originalItems as $item) {
            \assert($item instanceof BaseCartItem);

            if (! isset($itemModels[$item->id])) {
                continue;
            }

            foreach ($item->conditions as $condition) {
                \assert($condition instanceof BaseCartCondition);

                $persistedKeys[] = $this->persistCondition(
                    cartModel: $cartModel,
                    condition: $condition,
                    cartItemModel: $itemModels[$item->id],
                    itemId: $item->id
                );
            }
        }

        $existing = CartCondition::query()
            ->where('cart_id', $cartModel->id)
            ->get(['id', 'name', 'item_id', 'cart_item_id']);

        foreach ($existing as $existingCondition) {
            $key = $existingCondition->cart_item_id === null
                ? $this->conditionKey($existingCondition->name)
                : $this->conditionKey($existingCondition->name, $existingCondition->item_id);

            if (! in_array($key, $persistedKeys, true)) {
                $existingCondition->delete();
            }
        }
    }

    private function persistCondition(
        Cart $cartModel,
        BaseCartCondition $condition,
        ?CartItem $cartItemModel,
        ?string $itemId
    ): string {
        $data = $condition->toArray();

        $targetDefinition = $condition->getTargetDefinition();

        CartCondition::query()->updateOrCreate(
            [
                'cart_id' => $cartModel->id,
                'cart_item_id' => $cartItemModel?->id,
                'name' => $condition->getName(),
                'item_id' => $itemId,
            ],
            [
                'type' => $data['type'],
                'target' => $targetDefinition->toDsl(),
                'target_definition' => $targetDefinition->toArray(),
                'value' => (string) $data['value'],
                'order' => $data['order'],
                'attributes' => Arr::get($data, 'attributes') ?: null,
                'operator' => $data['operator'] ?? null,
                'is_charge' => (bool) ($data['is_charge'] ?? false),
                'is_dynamic' => (bool) ($data['is_dynamic'] ?? false),
                'is_discount' => (bool) ($data['is_discount'] ?? false),
                'is_percentage' => (bool) ($data['is_percentage'] ?? false),
                'is_global' => (bool) ($data['attributes']['is_global'] ?? false),
                'parsed_value' => isset($data['parsed_value']) ? (string) $data['parsed_value'] : null,
                'rules' => $data['rules'] ?? null,
            ]
        );

        return $this->conditionKey($condition->getName(), $itemId);
    }

    private function conditionKey(string $name, ?string $itemId = null): string
    {
        return $itemId === null ? "cart:{$name}" : "item:{$itemId}:{$name}";
    }

    private function resolveCurrency(): string
    {
        return mb_strtoupper(config('cart.money.default_currency', 'USD'));
    }

    private function resolveAssociatedModel(string | object | null $associatedModel): ?string
    {
        if (is_string($associatedModel)) {
            return $associatedModel;
        }

        return $associatedModel ? get_class($associatedModel) : null;
    }
}
