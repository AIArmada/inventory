<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Pricing\Models\PriceList;
use AIArmada\Pricing\Models\Promotion;

describe('Pricing owner scoping', function (): void {
    beforeEach(function (): void {
        config()->set('pricing.owner.enabled', true);
    });

    it('scopes PriceList owner=null to global-only records', function (): void {
        $ownerA = User::query()->create([
            'name' => 'Owner A',
            'email' => 'pricing-owner-a@example.com',
            'password' => 'secret',
        ]);

        $global = PriceList::query()->create([
            'name' => 'Global',
            'slug' => 'global-price-list',
            'currency' => 'MYR',
            'owner_type' => null,
            'owner_id' => null,
        ]);

        $owned = PriceList::query()->create([
            'name' => 'Owned',
            'slug' => 'owned-price-list',
            'currency' => 'MYR',
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => $ownerA->getKey(),
        ]);

        $corrupt = PriceList::query()->create([
            'name' => 'Corrupt',
            'slug' => 'corrupt-price-list',
            'currency' => 'MYR',
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => null,
        ]);

        $ids = PriceList::query()->forOwner(null)->pluck('id')->all();

        expect($ids)
            ->toContain($global->id)
            ->not->toContain($owned->id)
            ->not->toContain($corrupt->id);
    });

    it('scopes Promotion owner=null to global-only records', function (): void {
        $ownerA = User::query()->create([
            'name' => 'Owner A',
            'email' => 'pricing-owner-a-2@example.com',
            'password' => 'secret',
        ]);

        $global = Promotion::query()->create([
            'name' => 'Global Promo',
            'discount_value' => 10,
            'owner_type' => null,
            'owner_id' => null,
        ]);

        $owned = Promotion::query()->create([
            'name' => 'Owned Promo',
            'discount_value' => 10,
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => $ownerA->getKey(),
        ]);

        $corrupt = Promotion::query()->create([
            'name' => 'Corrupt Promo',
            'discount_value' => 10,
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => null,
        ]);

        $ids = Promotion::query()->forOwner(null)->pluck('id')->all();

        expect($ids)
            ->toContain($global->id)
            ->not->toContain($owned->id)
            ->not->toContain($corrupt->id);
    });

    it('includes global records when includeGlobal=true and owner is provided', function (): void {
        $ownerA = User::query()->create([
            'name' => 'Owner A',
            'email' => 'pricing-owner-a-3@example.com',
            'password' => 'secret',
        ]);

        $global = PriceList::query()->create([
            'name' => 'Global 2',
            'slug' => 'global-2-price-list',
            'currency' => 'MYR',
            'owner_type' => null,
            'owner_id' => null,
        ]);

        $owned = PriceList::query()->create([
            'name' => 'Owned 2',
            'slug' => 'owned-2-price-list',
            'currency' => 'MYR',
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => $ownerA->getKey(),
        ]);

        $ids = PriceList::query()->forOwner($ownerA, true)->pluck('id')->all();

        expect($ids)->toContain($global->id)->toContain($owned->id);
    });
});
