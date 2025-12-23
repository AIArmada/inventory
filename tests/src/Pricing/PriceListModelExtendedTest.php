<?php

declare(strict_types=1);

use AIArmada\Pricing\Models\Price;
use AIArmada\Pricing\Models\PriceList;
use AIArmada\Pricing\Models\PriceTier;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Support\Carbon;

describe('PriceList Model - Extended Tests', function (): void {
    describe('getTable', function (): void {
        it('returns configured table name', function (): void {
            $priceList = new PriceList;

            expect($priceList->getTable())->toBe(config('pricing.database.tables.price_lists', 'price_lists'));
        });
    });

    describe('isActive', function (): void {
        it('returns false when is_active is false', function (): void {
            $priceList = PriceList::create([
                'name' => 'Inactive List',
                'slug' => 'inactive-' . uniqid(),
                'currency' => 'MYR',
                'is_active' => false,
            ]);

            expect($priceList->isActive())->toBeFalse();
        });

        it('returns true when is_active is true and no date restrictions', function (): void {
            $priceList = PriceList::create([
                'name' => 'Active List',
                'slug' => 'active-' . uniqid(),
                'currency' => 'MYR',
                'is_active' => true,
            ]);

            expect($priceList->isActive())->toBeTrue();
        });

        it('returns true when within date range', function (): void {
            $priceList = PriceList::create([
                'name' => 'Scheduled List',
                'slug' => 'scheduled-' . uniqid(),
                'currency' => 'MYR',
                'is_active' => true,
                'starts_at' => Carbon::now()->subDay(),
                'ends_at' => Carbon::now()->addDay(),
            ]);

            expect($priceList->isActive())->toBeTrue();
        });

        it('returns false when before start date', function (): void {
            $priceList = PriceList::create([
                'name' => 'Future List',
                'slug' => 'future-' . uniqid(),
                'currency' => 'MYR',
                'is_active' => true,
                'starts_at' => Carbon::now()->addWeek(),
            ]);

            expect($priceList->isActive())->toBeFalse();
        });

        it('returns false when after end date', function (): void {
            $priceList = PriceList::create([
                'name' => 'Expired List',
                'slug' => 'expired-' . uniqid(),
                'currency' => 'MYR',
                'is_active' => true,
                'starts_at' => Carbon::now()->subWeek(),
                'ends_at' => Carbon::now()->subDay(),
            ]);

            expect($priceList->isActive())->toBeFalse();
        });
    });

    describe('scopes', function (): void {
        it('filters active price lists', function (): void {
            $prefix = uniqid();

            PriceList::create([
                'name' => 'Active',
                'slug' => "active-scope-{$prefix}",
                'currency' => 'MYR',
                'is_active' => true,
            ]);

            PriceList::create([
                'name' => 'Inactive',
                'slug' => "inactive-scope-{$prefix}",
                'currency' => 'MYR',
                'is_active' => false,
            ]);

            PriceList::create([
                'name' => 'Future',
                'slug' => "future-scope-{$prefix}",
                'currency' => 'MYR',
                'is_active' => true,
                'starts_at' => Carbon::now()->addWeek(),
            ]);

            $active = PriceList::where('slug', 'like', "%-scope-{$prefix}")->active()->get();

            expect($active)->toHaveCount(1)
                ->and($active->first()->name)->toBe('Active');
        });

        it('filters default price lists', function (): void {
            $prefix = uniqid();

            PriceList::create([
                'name' => 'Default',
                'slug' => "default-scope-{$prefix}",
                'currency' => 'MYR',
                'is_default' => true,
                'is_active' => true,
            ]);

            PriceList::create([
                'name' => 'Non-Default',
                'slug' => "non-default-scope-{$prefix}",
                'currency' => 'MYR',
                'is_default' => false,
                'is_active' => true,
            ]);

            $defaults = PriceList::where('slug', 'like', "%-scope-{$prefix}")->default()->get();

            expect($defaults)->toHaveCount(1)
                ->and($defaults->first()->name)->toBe('Default');
        });

        it('filters by owner when enabled', function (): void {
            // Note: This test only checks that forOwner scope runs without errors
            // Full owner testing requires migrations with owner columns
            config(['pricing.features.owner.enabled' => false]);

            $prefix = uniqid();

            PriceList::create([
                'name' => 'Test List',
                'slug' => "owned-{$prefix}",
                'currency' => 'MYR',
                'is_active' => true,
            ]);

            // This test verifies the scope exists and runs
            $lists = PriceList::where('slug', 'like', "%-{$prefix}")->forOwner(null)->get();
            expect($lists)->toHaveCount(1);
        });
    });

    describe('relationships', function (): void {
        it('has many prices', function (): void {
            $priceList = PriceList::create([
                'name' => 'List With Prices',
                'slug' => 'with-prices-' . uniqid(),
                'currency' => 'MYR',
                'is_active' => true,
            ]);

            Price::create([
                'price_list_id' => $priceList->id,
                'priceable_type' => 'TestProduct',
                'priceable_id' => 'prod-1-' . uniqid(),
                'amount' => 5000,
                'currency' => 'MYR',
            ]);

            Price::create([
                'price_list_id' => $priceList->id,
                'priceable_type' => 'TestProduct',
                'priceable_id' => 'prod-2-' . uniqid(),
                'amount' => 6000,
                'currency' => 'MYR',
            ]);

            expect($priceList->prices)->toHaveCount(2);
        });

        it('has many tiers', function (): void {
            $priceList = PriceList::create([
                'name' => 'List With Tiers',
                'slug' => 'with-tiers-' . uniqid(),
                'currency' => 'MYR',
                'is_active' => true,
            ]);

            PriceTier::create([
                'price_list_id' => $priceList->id,
                'tierable_type' => 'TestProduct',
                'tierable_id' => 'prod-tier-' . uniqid(),
                'min_quantity' => 10,
                'amount' => 900,
                'currency' => 'MYR',
            ]);

            expect($priceList->tiers)->toHaveCount(1);
        });
    });

    describe('cascades', function (): void {
        it('deletes related prices and tiers on delete', function (): void {
            $priceList = PriceList::create([
                'name' => 'To Delete',
                'slug' => 'to-delete-' . uniqid(),
                'currency' => 'MYR',
                'is_active' => true,
            ]);

            $priceId = Price::create([
                'price_list_id' => $priceList->id,
                'priceable_type' => 'TestProduct',
                'priceable_id' => 'del-prod-' . uniqid(),
                'amount' => 5000,
                'currency' => 'MYR',
            ])->id;

            $tierId = PriceTier::create([
                'price_list_id' => $priceList->id,
                'tierable_type' => 'TestProduct',
                'tierable_id' => 'del-tier-' . uniqid(),
                'min_quantity' => 10,
                'amount' => 900,
                'currency' => 'MYR',
            ])->id;

            $priceList->delete();

            expect(Price::find($priceId))->toBeNull()
                ->and(PriceTier::find($tierId))->toBeNull();
        });
    });

    describe('default attributes', function (): void {
        it('has default priority of 0', function (): void {
            $priceList = new PriceList;

            expect($priceList->priority)->toBe(0);
        });

        it('has default is_default of false', function (): void {
            $priceList = new PriceList;

            expect($priceList->is_default)->toBeFalse();
        });

        it('has default is_active of true', function (): void {
            $priceList = new PriceList;

            expect($priceList->is_active)->toBeTrue();
        });
    });

    describe('forOwner scope', function (): void {
        it('returns all records when owner feature is disabled', function (): void {
            config(['pricing.features.owner.enabled' => false]);

            $prefix = uniqid();

            PriceList::create([
                'name' => 'Test List 1',
                'slug' => "test1-{$prefix}",
                'currency' => 'MYR',
                'is_active' => true,
            ]);

            PriceList::create([
                'name' => 'Test List 2',
                'slug' => "test2-{$prefix}",
                'currency' => 'MYR',
                'is_active' => true,
            ]);

            $lists = PriceList::where('slug', 'like', "test%-{$prefix}")->forOwner(null)->get();

            expect($lists)->toHaveCount(2);
        });

        it('returns global records when no owner provided and feature enabled', function (): void {
            config(['pricing.features.owner.enabled' => true]);

            $prefix = uniqid();

            // Global record (no owner)
            OwnerContext::withOwner(null, static fn () => PriceList::query()->create([
                'name' => 'Global List',
                'slug' => "global-{$prefix}",
                'currency' => 'MYR',
                'is_active' => true,
            ]));

            // Owned record
            $otherOwner = new class extends Illuminate\Database\Eloquent\Model
            {
                public $incrementing = false;

                protected $keyType = 'string';
            };
            $otherOwner->id = 'store-123';
            $otherOwner->setTable('stores');

            OwnerContext::withOwner($otherOwner, static fn () => PriceList::query()->create([
                'name' => 'Owned List',
                'slug' => "owned-{$prefix}",
                'currency' => 'MYR',
                'is_active' => true,
            ]));

            $lists = PriceList::where('slug', 'like', "%-{$prefix}")->forOwner(null)->get();

            expect($lists)->toHaveCount(1)
                ->and($lists->first()->name)->toBe('Global List');
        });

        it('returns owned and global records when owner provided with includeGlobal true', function (): void {
            config(['pricing.features.owner.enabled' => true]);
            config(['pricing.features.owner.include_global' => true]);

            $prefix = uniqid();
            $ownerId = 'owner-' . uniqid();

            // Create a mock owner model
            $owner = new class extends Illuminate\Database\Eloquent\Model
            {
                public $incrementing = false;

                protected $keyType = 'string';
            };
            $owner->id = $ownerId;
            $owner->setTable('stores');

            // Global record
            OwnerContext::withOwner(null, static fn () => PriceList::query()->create([
                'name' => 'Global List',
                'slug' => "global-{$prefix}",
                'currency' => 'MYR',
                'is_active' => true,
            ]));

            // Owned record matching owner
            OwnerContext::withOwner($owner, static fn () => PriceList::query()->create([
                'name' => 'Owned List',
                'slug' => "owned-{$prefix}",
                'currency' => 'MYR',
                'is_active' => true,
            ]));

            // Different owner
            $otherOwner = new class extends Illuminate\Database\Eloquent\Model
            {
                public $incrementing = false;

                protected $keyType = 'string';
            };
            $otherOwner->id = 'other-store-' . uniqid();
            $otherOwner->setTable('stores');

            OwnerContext::withOwner($otherOwner, static fn () => PriceList::query()->create([
                'name' => 'Other Owner List',
                'slug' => "other-{$prefix}",
                'currency' => 'MYR',
                'is_active' => true,
            ]));

            $lists = PriceList::where('slug', 'like', "%-{$prefix}")->forOwner($owner, true)->get();

            expect($lists)->toHaveCount(2);
        });

        it('returns only owned records when includeGlobal is false', function (): void {
            config(['pricing.features.owner.enabled' => true]);

            $prefix = uniqid();
            $ownerId = 'owner-' . uniqid();

            // Create a mock owner model
            $owner = new class extends Illuminate\Database\Eloquent\Model
            {
                public $incrementing = false;

                protected $keyType = 'string';
            };
            $owner->id = $ownerId;
            $owner->setTable('stores');

            // Global record
            OwnerContext::withOwner(null, static fn () => PriceList::query()->create([
                'name' => 'Global List',
                'slug' => "global-{$prefix}",
                'currency' => 'MYR',
                'is_active' => true,
            ]));

            // Owned record matching owner
            OwnerContext::withOwner($owner, static fn () => PriceList::query()->create([
                'name' => 'Owned List',
                'slug' => "owned-{$prefix}",
                'currency' => 'MYR',
                'is_active' => true,
            ]));

            $lists = PriceList::where('slug', 'like', "%-{$prefix}")->forOwner($owner, false)->get();

            expect($lists)->toHaveCount(1)
                ->and($lists->first()->name)->toBe('Owned List');
        });

        it('returns only global records when no owner provided and includeGlobal false', function (): void {
            config(['pricing.features.owner.enabled' => true]);

            $prefix = uniqid();

            // Global record
            OwnerContext::withOwner(null, static fn () => PriceList::query()->create([
                'name' => 'Global List',
                'slug' => "global-{$prefix}",
                'currency' => 'MYR',
                'is_active' => true,
            ]));

            // Owned record
            $otherOwner = new class extends Illuminate\Database\Eloquent\Model
            {
                public $incrementing = false;

                protected $keyType = 'string';
            };
            $otherOwner->id = 'store-' . uniqid();
            $otherOwner->setTable('stores');

            OwnerContext::withOwner($otherOwner, static fn () => PriceList::query()->create([
                'name' => 'Owned List',
                'slug' => "owned-{$prefix}",
                'currency' => 'MYR',
                'is_active' => true,
            ]));

            $lists = PriceList::where('slug', 'like', "%-{$prefix}")->forOwner(null, false)->get();

            expect($lists)->toHaveCount(1)
                ->and($lists->first()->name)->toBe('Global List');
        });
    });
});
