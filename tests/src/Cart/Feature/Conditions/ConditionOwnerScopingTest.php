<?php

declare(strict_types=1);

use AIArmada\Cart\Models\Condition;
use AIArmada\Commerce\Tests\Fixtures\Models\User;

describe('Condition owner scoping', function (): void {
    beforeEach(function (): void {
        config()->set('cart.owner.enabled', true);
    });

    it('scopes owner=null to global-only records (never owner_type-only corrupt rows)', function (): void {
        $ownerA = User::query()->create([
            'name' => 'Owner A',
            'email' => 'owner-a@example.com',
            'password' => 'secret',
        ]);

        $global = Condition::factory()->create([
            'owner_type' => null,
            'owner_id' => null,
        ]);

        $owned = Condition::factory()->create([
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => $ownerA->getKey(),
        ]);

        $corrupt = Condition::factory()->create([
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => null,
        ]);

        $ids = Condition::query()->forOwner(null)->pluck('id')->all();

        expect($ids)
            ->toContain($global->id)
            ->not->toContain($owned->id)
            ->not->toContain($corrupt->id);
    });

    it('respects cart.owner.include_global for owner-scoped queries', function (): void {
        $ownerA = User::query()->create([
            'name' => 'Owner A',
            'email' => 'owner-a-2@example.com',
            'password' => 'secret',
        ]);

        $global = Condition::factory()->create([
            'owner_type' => null,
            'owner_id' => null,
        ]);

        $owned = Condition::factory()->create([
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => $ownerA->getKey(),
        ]);

        config()->set('cart.owner.include_global', false);

        $ids = Condition::query()->forOwner($ownerA)->pluck('id')->all();

        expect($ids)
            ->toContain($owned->id)
            ->not->toContain($global->id);
    });
});
