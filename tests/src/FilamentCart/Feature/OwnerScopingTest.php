<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentCart\Models\Cart as CartSnapshot;
use AIArmada\FilamentCart\Models\CartCondition;
use AIArmada\FilamentCart\Models\CartItem;
use AIArmada\FilamentCart\Resources\CartConditionResource;
use AIArmada\FilamentCart\Resources\CartItemResource;
use AIArmada\FilamentCart\Resources\CartResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

final class StaticOwnerResolver implements OwnerResolverInterface
{
    public function __construct(private ?Model $owner)
    {
    }

    public function resolve(): ?Model
    {
        return $this->owner;
    }
}

it('scopes filament-cart snapshots and child resources by resolved owner', function (): void {
    config()->set('filament-cart.owner.enabled', true);
    config()->set('filament-cart.owner.include_global', false);

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'owner-a@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'owner-b@example.com',
        'password' => 'secret',
    ]);

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new StaticOwnerResolver($ownerA));

    $cartA = CartSnapshot::query()->create([
        'identifier' => 'same-id',
        'instance' => 'default',
        'currency' => 'USD',
        'items_count' => 1,
        'quantity' => 1,
        'subtotal' => 1000,
        'total' => 1000,
    ]);
    $cartA->assignOwner($ownerA)->save();

    $cartB = CartSnapshot::query()->create([
        'identifier' => 'same-id',
        'instance' => 'default',
        'currency' => 'USD',
        'items_count' => 2,
        'quantity' => 2,
        'subtotal' => 2000,
        'total' => 2000,
    ]);
    $cartB->assignOwner($ownerB)->save();

    $cartAItem = CartItem::query()->create([
        'cart_id' => $cartA->id,
        'item_id' => 'sku-a',
        'name' => 'Item A',
        'price' => 1000,
        'quantity' => 1,
    ]);

    CartItem::query()->create([
        'cart_id' => $cartB->id,
        'item_id' => 'sku-b',
        'name' => 'Item B',
        'price' => 1000,
        'quantity' => 2,
    ]);

    CartCondition::query()->create([
        'cart_id' => $cartA->id,
        'name' => 'discount-a',
        'type' => 'discount',
        'target' => 'cart@cart_subtotal/aggregate',
        'target_definition' => conditionTargetDefinition('cart@cart_subtotal/aggregate'),
        'value' => '-10%',
        'is_discount' => true,
        'is_percentage' => true,
        'order' => 1,
        'cart_item_id' => $cartAItem->id,
        'item_id' => $cartAItem->item_id,
    ]);

    CartCondition::query()->create([
        'cart_id' => $cartB->id,
        'name' => 'discount-b',
        'type' => 'discount',
        'target' => 'cart@cart_subtotal/aggregate',
        'target_definition' => conditionTargetDefinition('cart@cart_subtotal/aggregate'),
        'value' => '-5%',
        'is_discount' => true,
        'is_percentage' => true,
        'order' => 1,
    ]);

    expect(CartResource::getEloquentQuery()->count())->toBe(1);
    expect(CartResource::getEloquentQuery()->first()?->id)->toBe($cartA->id);

    expect(CartItemResource::getEloquentQuery()->count())->toBe(1);
    expect(CartItemResource::getEloquentQuery()->first()?->cart_id)->toBe($cartA->id);

    expect(CartConditionResource::getEloquentQuery()->count())->toBe(1);
    expect(CartConditionResource::getEloquentQuery()->first()?->cart_id)->toBe($cartA->id);
});
