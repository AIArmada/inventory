<?php

declare(strict_types=1);

use AIArmada\Cart\Testing\InMemoryStorage;

describe('InMemoryStorage', function (): void {
    it('can store and retrieve items', function (): void {
        $storage = new InMemoryStorage;

        $items = [['id' => 'item1', 'name' => 'Item 1']];
        $storage->putItems('identifier', 'instance', $items);

        expect($storage->getItems('identifier', 'instance'))->toBe($items);
    });

    it('can check if cart exists', function (): void {
        $storage = new InMemoryStorage;

        expect($storage->has('identifier', 'instance'))->toBeFalse();

        $storage->putItems('identifier', 'instance', [['id' => 'item1']]);

        expect($storage->has('identifier', 'instance'))->toBeTrue();
    });

    it('stores conditions and metadata and clears them', function (): void {
        $storage = new InMemoryStorage;

        $storage->putConditions('identifier', 'instance', [['name' => 'cond']]);
        $storage->putMetadata('identifier', 'instance', 'key', 'value');

        expect($storage->getConditions('identifier', 'instance'))->toBe([['name' => 'cond']]);
        expect($storage->getMetadata('identifier', 'instance', 'key'))->toBe('value');
        expect($storage->getAllMetadata('identifier', 'instance'))
            ->toEqual(['key' => 'value']);

        $storage->clearMetadata('identifier', 'instance');
        expect($storage->getAllMetadata('identifier', 'instance'))->toBe([]);
    });

    it('supports clearAll and forget operations', function (): void {
        $storage = new InMemoryStorage;

        $storage->putItems('identifier', 'instance', [['id' => 'item1']]);
        $storage->putConditions('identifier', 'instance', [['name' => 'cond']]);
        $storage->putMetadata('identifier', 'instance', 'key', 'value');

        $storage->clearAll('identifier', 'instance');

        expect($storage->getItems('identifier', 'instance'))->toBe([]);
        expect($storage->getConditions('identifier', 'instance'))->toBe([]);
        expect($storage->getAllMetadata('identifier', 'instance'))->toBe([]);

        $storage->putItems('identifier', 'instance', [['id' => 'item1']]);
        $storage->forget('identifier', 'instance');

        expect($storage->has('identifier', 'instance'))->toBeFalse();
    });

    it('supports instances, versions, ids and identifier swap', function (): void {
        $storage = new InMemoryStorage;

        $storage->putItems('id-1', 'inst-1', [['id' => 'item1']]);
        $storage->putItems('id-1', 'inst-2', [['id' => 'item2']]);

        // Instances are tracked
        expect($storage->getInstances('id-1'))->toHaveCount(2);

        // Version increments with writes
        expect($storage->getVersion('id-1', 'inst-3'))->toBeNull();
        $storage->putItems('id-1', 'inst-3', [['id' => 'item1']]);
        expect($storage->getVersion('id-1', 'inst-3'))->toBe(1);
        $storage->putConditions('id-1', 'inst-3', [['name' => 'cond']]);
        expect($storage->getVersion('id-1', 'inst-3'))->toBe(2);

        // ID is lazily generated and stable
        $id = $storage->getId('id-1', 'inst-1');
        expect($id)->toBeString();
        expect($storage->getId('id-1', 'inst-1'))->toBe($id);

        // Swap identifier moves all data
        $storage->putMetadata('id-1', 'inst-1', 'k', 'v');
        $result = $storage->swapIdentifier('id-1', 'id-2', 'inst-1');

        expect($result)->toBeTrue();
        expect($storage->getItems('id-2', 'inst-1'))->not->toBe([]);
        expect($storage->getAllMetadata('id-2', 'inst-1'))->toHaveKey('k');
    });

    it('supports flush to clear all storage', function (): void {
        $storage = new InMemoryStorage;

        $storage->putItems('user1', 'default', [['id' => 'x']]);
        $storage->putItems('user2', 'default', [['id' => 'y']]);
        $storage->putConditions('user1', 'default', [['name' => 'c']]);

        $storage->flush();

        expect($storage->has('user1', 'default'))->toBeFalse()
            ->and($storage->has('user2', 'default'))->toBeFalse();
    });

    it('supports forgetIdentifier to clear all instances', function (): void {
        $storage = new InMemoryStorage;

        $storage->putItems('user', 'cart1', [['id' => 'x']]);
        $storage->putItems('user', 'cart2', [['id' => 'y']]);

        $storage->forgetIdentifier('user');

        expect($storage->has('user', 'cart1'))->toBeFalse()
            ->and($storage->has('user', 'cart2'))->toBeFalse();
    });

    it('supports putMetadataBatch for bulk metadata', function (): void {
        $storage = new InMemoryStorage;

        $storage->putMetadataBatch('id', 'inst', [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
        ]);

        expect($storage->getMetadata('id', 'inst', 'key1'))->toBe('value1')
            ->and($storage->getMetadata('id', 'inst', 'key2'))->toBe('value2')
            ->and($storage->getMetadata('id', 'inst', 'key3'))->toBe('value3');
    });

    it('returns null for timestamps', function (): void {
        $storage = new InMemoryStorage;

        expect($storage->getCreatedAt('id', 'inst'))->toBeNull()
            ->and($storage->getUpdatedAt('id', 'inst'))->toBeNull()
            ->and($storage->getExpiresAt('id', 'inst'))->toBeNull();
    });

    it('supports owner type and id from constructor', function (): void {
        $storage = new InMemoryStorage('App\\Models\\User', 123);

        expect($storage->getOwnerType())->toBe('App\\Models\\User')
            ->and($storage->getOwnerId())->toBe(123);
    });

    it('returns null for owner when not set', function (): void {
        $storage = new InMemoryStorage;

        expect($storage->getOwnerType())->toBeNull()
            ->and($storage->getOwnerId())->toBeNull();
    });

    it('supports putBoth for atomic items and conditions', function (): void {
        $storage = new InMemoryStorage;

        $items = [['id' => 'item-1']];
        $conditions = [['name' => 'Tax']];

        $storage->putBoth('id', 'inst', $items, $conditions);

        expect($storage->getItems('id', 'inst'))->toHaveCount(1)
            ->and($storage->getConditions('id', 'inst'))->toHaveCount(1);
    });

    it('returns false when swapping nonexistent cart', function (): void {
        $storage = new InMemoryStorage;

        $result = $storage->swapIdentifier('nonexistent', 'new', 'default');

        expect($result)->toBeFalse();
    });

    it('returns empty instances for unknown identifier', function (): void {
        $storage = new InMemoryStorage;

        $instances = $storage->getInstances('unknown');

        expect($instances)->toBeArray()->toBeEmpty();
    });
});
