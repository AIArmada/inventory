<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentPricing\Resources\PriceListResource;
use AIArmada\FilamentPricing\Resources\PromotionResource;
use AIArmada\Pricing\Models\PriceList;
use AIArmada\Pricing\Models\Promotion;
use Illuminate\Database\Eloquent\Model;

uses(TestCase::class);

it('scopes filament pricing resources to the resolved owner (including global)', function (): void {
    config()->set('pricing.owner.enabled', true);

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'owner-a-pricing@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'owner-b-pricing@example.com',
        'password' => 'secret',
    ]);

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new class($ownerA) implements OwnerResolverInterface
    {
        public function __construct(private readonly ?Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    $globalList = PriceList::create([
        'name' => 'Global',
        'slug' => 'global',
        'currency' => 'MYR',
        'owner_type' => null,
        'owner_id' => null,
    ]);

    $ownerAList = PriceList::create([
        'name' => 'A',
        'slug' => 'a',
        'currency' => 'MYR',
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]);

    $ownerBList = PriceList::create([
        'name' => 'B',
        'slug' => 'b',
        'currency' => 'MYR',
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
    ]);

    $globalPromo = Promotion::create([
        'name' => 'Global Promo',
        'code' => 'GLOBAL-P',
        'type' => 'percentage',
        'discount_value' => 10,
        'is_active' => true,
        'owner_type' => null,
        'owner_id' => null,
    ]);

    $ownerAPromo = Promotion::create([
        'name' => 'A Promo',
        'code' => 'A-P',
        'type' => 'percentage',
        'discount_value' => 10,
        'is_active' => true,
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]);

    $ownerBPromo = Promotion::create([
        'name' => 'B Promo',
        'code' => 'B-P',
        'type' => 'percentage',
        'discount_value' => 10,
        'is_active' => true,
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
    ]);

    $priceListIds = PriceListResource::getEloquentQuery()->pluck('id')->all();
    expect($priceListIds)->toContain($globalList->id, $ownerAList->id)
        ->not->toContain($ownerBList->id);

    $promotionIds = PromotionResource::getEloquentQuery()->pluck('id')->all();
    expect($promotionIds)->toContain($globalPromo->id, $ownerAPromo->id)
        ->not->toContain($ownerBPromo->id);
});

it('returns strict global-only when owner resolver returns null', function (): void {
    config()->set('pricing.owner.enabled', true);

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new class implements OwnerResolverInterface
    {
        public function resolve(): ?Model
        {
            return null;
        }
    });

    $globalList = PriceList::create([
        'name' => 'Global 2',
        'slug' => 'global-2',
        'currency' => 'MYR',
        'owner_type' => null,
        'owner_id' => null,
    ]);

    $scopedIds = PriceListResource::getEloquentQuery()->pluck('id')->all();

    expect($scopedIds)->toContain($globalList->id);
});
