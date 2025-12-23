<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Pricing\Models\PriceList;
use AIArmada\Pricing\Models\Promotion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

describe('Pricing owner scoping', function (): void {
    beforeEach(function (): void {
        config()->set('pricing.features.owner.enabled', true);
    });

    it('scopes PriceList owner=null to global-only records', function (): void {
        $ownerA = User::query()->create([
            'name' => 'Owner A',
            'email' => 'pricing-owner-a@example.com',
            'password' => 'secret',
        ]);

        $global = OwnerContext::withOwner(null, static fn () => PriceList::query()->create([
            'name' => 'Global',
            'slug' => 'global-price-list',
            'currency' => 'MYR',
            'owner_type' => null,
            'owner_id' => null,
        ]));

        $owned = OwnerContext::withOwner($ownerA, static fn () => PriceList::query()->create([
            'name' => 'Owned',
            'slug' => 'owned-price-list',
            'currency' => 'MYR',
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => $ownerA->getKey(),
        ]));

        $corruptId = (string) Str::uuid();
        DB::table((new PriceList)->getTable())->insert([
            'id' => $corruptId,
            'name' => 'Corrupt',
            'slug' => 'corrupt-price-list-' . uniqid(),
            'currency' => 'MYR',
            'priority' => 0,
            'is_default' => false,
            'is_active' => true,
            'customer_id' => null,
            'segment_id' => null,
            'starts_at' => null,
            'ends_at' => null,
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ids = PriceList::query()->forOwner(null)->pluck('id')->all();

        expect($ids)
            ->toContain($global->id)
            ->not->toContain($owned->id)
            ->not->toContain($corruptId);
    });

    it('scopes Promotion owner=null to global-only records', function (): void {
        $ownerA = User::query()->create([
            'name' => 'Owner A',
            'email' => 'pricing-owner-a-2@example.com',
            'password' => 'secret',
        ]);

        $global = OwnerContext::withOwner(null, static fn () => Promotion::query()->create([
            'name' => 'Global Promo',
            'discount_value' => 10,
            'owner_type' => null,
            'owner_id' => null,
        ]));

        $owned = OwnerContext::withOwner($ownerA, static fn () => Promotion::query()->create([
            'name' => 'Owned Promo',
            'discount_value' => 10,
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => $ownerA->getKey(),
        ]));

        $corruptId = (string) Str::uuid();
        DB::table((new Promotion)->getTable())->insert([
            'id' => $corruptId,
            'name' => 'Corrupt Promo',
            'code' => null,
            'description' => null,
            'type' => 'percentage',
            'discount_value' => 10,
            'priority' => 0,
            'is_stackable' => false,
            'is_active' => true,
            'usage_limit' => null,
            'usage_count' => 0,
            'min_purchase_amount' => null,
            'min_quantity' => null,
            'conditions' => null,
            'starts_at' => null,
            'ends_at' => null,
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ids = Promotion::query()->forOwner(null)->pluck('id')->all();

        expect($ids)
            ->toContain($global->id)
            ->not->toContain($owned->id)
            ->not->toContain($corruptId);
    });

    it('includes global records when includeGlobal=true and owner is provided', function (): void {
        $ownerA = User::query()->create([
            'name' => 'Owner A',
            'email' => 'pricing-owner-a-3@example.com',
            'password' => 'secret',
        ]);

        config()->set('pricing.features.owner.include_global', true);

        $global = OwnerContext::withOwner(null, static fn () => PriceList::query()->create([
            'name' => 'Global 2',
            'slug' => 'global-2-price-list',
            'currency' => 'MYR',
            'owner_type' => null,
            'owner_id' => null,
        ]));

        $owned = OwnerContext::withOwner($ownerA, static fn () => PriceList::query()->create([
            'name' => 'Owned 2',
            'slug' => 'owned-2-price-list',
            'currency' => 'MYR',
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => $ownerA->getKey(),
        ]));

        $ids = PriceList::query()->forOwner($ownerA, true)->pluck('id')->all();

        expect($ids)->toContain($global->id)->toContain($owned->id);
    });
});
