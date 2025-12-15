<?php

declare(strict_types=1);

use AIArmada\Cart\Facades\Cart;
use AIArmada\Cart\ReadModels\CartReadModel;
use AIArmada\Cart\Storage\StorageInterface;
use AIArmada\Commerce\Tests\Fixtures\Models\User;

describe('CartReadModel owner scoping', function (): void {
    it('does not leak cached cart summaries across owners', function (): void {
        Cart::clear();

        $ownerA = User::query()->create([
            'name' => 'Owner A',
            'email' => 'owner-a-readmodel@example.com',
            'password' => 'secret',
        ]);

        $ownerB = User::query()->create([
            'name' => 'Owner B',
            'email' => 'owner-b-readmodel@example.com',
            'password' => 'secret',
        ]);

        $cartB = Cart::forOwner($ownerB);
        $cartB->setIdentifier('shared-user');
        $cartB->add('item', 'Item', 10.00, 1);
        $cartIdB = $cartB->getId();

        expect($cartIdB)->not->toBeNull();

        /** @var Illuminate\Database\ConnectionInterface $connection */
        $connection = app('db')->connection();

        /** @var Illuminate\Contracts\Cache\Repository $cache */
        $cache = app('cache')->store();

        /** @var StorageInterface $baseStorage */
        $baseStorage = app(StorageInterface::class);

        $readModelForOwnerB = new CartReadModel(
            connection: $connection,
            cache: $cache,
            storage: $baseStorage->withOwner($ownerB),
        );

        $summaryB = $readModelForOwnerB->getCartSummary($cartIdB);
        expect($summaryB)->not->toBeNull();

        $readModelForOwnerA = new CartReadModel(
            connection: $connection,
            cache: $cache,
            storage: $baseStorage->withOwner($ownerA),
        );

        $summaryA = $readModelForOwnerA->getCartSummary($cartIdB);
        expect($summaryA)->toBeNull();
    });
});
