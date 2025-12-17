<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Tax\Models\TaxClass;
use AIArmada\Tax\Models\TaxZone;

describe('Tax owner scoping', function (): void {
    beforeEach(function (): void {
        config()->set('tax.features.owner.enabled', true);
    });

    it('scopes TaxZone owner=null to global-only records', function (): void {
        $ownerA = User::query()->create([
            'name' => 'Owner A',
            'email' => 'tax-owner-a@example.com',
            'password' => 'secret',
        ]);

        $global = TaxZone::query()->create([
            'name' => 'Global Zone',
            'code' => 'GLOBAL_ZONE',
            'owner_type' => null,
            'owner_id' => null,
        ]);

        $owned = TaxZone::query()->create([
            'name' => 'Owned Zone',
            'code' => 'OWNED_ZONE',
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => $ownerA->getKey(),
        ]);

        $corrupt = TaxZone::query()->create([
            'name' => 'Corrupt Zone',
            'code' => 'CORRUPT_ZONE',
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => null,
        ]);

        $ids = TaxZone::query()->forOwner(null)->pluck('id')->all();

        expect($ids)
            ->toContain($global->id)
            ->not->toContain($owned->id)
            ->not->toContain($corrupt->id);
    });

    it('scopes TaxClass owner=null to global-only records', function (): void {
        $ownerA = User::query()->create([
            'name' => 'Owner A',
            'email' => 'tax-owner-a-2@example.com',
            'password' => 'secret',
        ]);

        $global = TaxClass::query()->create([
            'name' => 'Global Class',
            'slug' => 'global-class',
            'owner_type' => null,
            'owner_id' => null,
        ]);

        $owned = TaxClass::query()->create([
            'name' => 'Owned Class',
            'slug' => 'owned-class',
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => $ownerA->getKey(),
        ]);

        $corrupt = TaxClass::query()->create([
            'name' => 'Corrupt Class',
            'slug' => 'corrupt-class',
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => null,
        ]);

        $ids = TaxClass::query()->forOwner(null)->pluck('id')->all();

        expect($ids)
            ->toContain($global->id)
            ->not->toContain($owned->id)
            ->not->toContain($corrupt->id);
    });
});
