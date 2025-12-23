<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Pricing\Contracts\Priceable;
use AIArmada\Pricing\Models\Price;
use AIArmada\Pricing\Models\PriceList;
use AIArmada\Pricing\Services\PriceCalculator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;

function bindPricingOwner(?Model $owner): void
{
    app()->bind(OwnerResolverInterface::class, fn () => new class($owner) implements OwnerResolverInterface
    {
        public function __construct(private ?Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });
}

it('blocks cross-tenant reads and writes for owned price lists/prices', function (): void {
    config()->set('pricing.features.owner.enabled', true);
    config()->set('pricing.features.owner.include_global', false);

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'pricing-owner-a-xt@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'pricing-owner-b-xt@example.com',
        'password' => 'secret',
    ]);

    bindPricingOwner($ownerB);

    $listB = PriceList::query()->create([
        'name' => 'List B',
        'slug' => 'list-b-xt',
        'currency' => 'MYR',
        'is_active' => true,
    ]);

    $item = new class implements Priceable
    {
        public function getBuyableIdentifier(): string
        {
            return 'item-xt';
        }

        public function getBasePrice(): int
        {
            return 10_000;
        }

        public function getComparePrice(): ?int
        {
            return null;
        }

        public function isOnSale(): bool
        {
            return false;
        }

        public function getDiscountPercentage(): ?float
        {
            return null;
        }
    };

    Price::query()->create([
        'price_list_id' => $listB->id,
        'priceable_type' => get_class($item),
        'priceable_id' => $item->getBuyableIdentifier(),
        'amount' => 5_000,
        'currency' => 'MYR',
    ]);

    bindPricingOwner($ownerA);

    $calculator = new PriceCalculator;

    $result = $calculator->calculate($item, 1, ['price_list_id' => $listB->id]);

    expect($result->finalPrice)->toBe(10_000);

    expect(fn () => Price::query()->create([
        'price_list_id' => $listB->id,
        'priceable_type' => get_class($item),
        'priceable_id' => $item->getBuyableIdentifier(),
        'amount' => 4_000,
        'currency' => 'MYR',
    ]))
        ->toThrow(AuthorizationException::class);
});

it('blocks writing and deleting owned rows when owner is unresolved', function (): void {
    config()->set('pricing.features.owner.enabled', true);
    config()->set('pricing.features.owner.include_global', false);

    $owner = User::query()->create([
        'name' => 'Owner',
        'email' => 'pricing-owner-null-context@example.com',
        'password' => 'secret',
    ]);

    // No owner context
    bindPricingOwner(null);

    $ownedList = new PriceList([
        'name' => 'Owned List',
        'slug' => 'owned-null-context',
        'currency' => 'MYR',
        'is_active' => true,
    ]);
    $ownedList->owner_type = $owner->getMorphClass();
    $ownedList->owner_id = (string) $owner->getKey();

    expect(fn () => $ownedList->save())
        ->toThrow(AuthorizationException::class);

    // Create an owned row under a real owner context...
    bindPricingOwner($owner);
    $list = PriceList::query()->create([
        'name' => 'List',
        'slug' => 'list-null-context',
        'currency' => 'MYR',
        'is_active' => true,
    ]);

    // ...then ensure it cannot be deleted without owner context.
    bindPricingOwner(null);

    expect(fn () => $list->delete())
        ->toThrow(AuthorizationException::class);
});
