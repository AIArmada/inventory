<?php

declare(strict_types=1);

use AIArmada\Cart\Storage\DatabaseStorage;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use Illuminate\Database\ConnectionInterface;

describe('DatabaseStorage Integration', function (): void {
    beforeEach(function (): void {
        /** @var ConnectionInterface $connection */
        $connection = app('db')->connection();
        $this->storage = new DatabaseStorage($connection, 'carts');
    });

    describe('basic operations', function (): void {
        it('stores and retrieves items', function (): void {
            $identifier = 'db-storage-items-' . uniqid();
            $instance = 'default';
            $items = [
                'item-1' => ['id' => 'item-1', 'name' => 'Product 1', 'price' => 1000, 'quantity' => 2],
                'item-2' => ['id' => 'item-2', 'name' => 'Product 2', 'price' => 2000, 'quantity' => 1],
            ];

            $this->storage->putItems($identifier, $instance, $items);
            $retrieved = $this->storage->getItems($identifier, $instance);

            expect($retrieved)->toBeArray();
        });

        it('stores and retrieves conditions', function (): void {
            $identifier = 'db-storage-conditions-' . uniqid();
            $instance = 'default';
            $conditions = [
                'discount' => ['name' => 'discount', 'type' => 'discount', 'value' => '-10%'],
            ];

            $this->storage->putConditions($identifier, $instance, $conditions);
            $retrieved = $this->storage->getConditions($identifier, $instance);

            expect($retrieved)->toBeArray();
        });

        it('stores both items and conditions', function (): void {
            $identifier = 'db-storage-both-' . uniqid();
            $instance = 'default';
            $items = ['item-1' => ['id' => 'item-1']];
            $conditions = ['cond-1' => ['name' => 'cond-1']];

            $this->storage->putBoth($identifier, $instance, $items, $conditions);

            expect($this->storage->has($identifier, $instance))->toBeTrue();
        });
    });

    describe('has', function (): void {
        it('returns false for non-existent cart', function (): void {
            $result = $this->storage->has('non-existent-' . uniqid(), 'default');

            expect($result)->toBeFalse();
        });

        it('returns true for existing cart', function (): void {
            $identifier = 'db-storage-has-' . uniqid();
            $this->storage->putItems($identifier, 'default', ['item' => []]);

            $result = $this->storage->has($identifier, 'default');

            expect($result)->toBeTrue();
        });
    });

    describe('forget', function (): void {
        it('removes cart from storage', function (): void {
            $identifier = 'db-storage-forget-' . uniqid();
            $this->storage->putItems($identifier, 'default', ['item' => []]);

            $this->storage->forget($identifier, 'default');

            expect($this->storage->has($identifier, 'default'))->toBeFalse();
        });
    });

    describe('getInstances', function (): void {
        it('returns all instances for identifier', function (): void {
            $identifier = 'db-storage-instances-' . uniqid();

            $this->storage->putItems($identifier, 'cart', ['item' => []]);
            $this->storage->putItems($identifier, 'wishlist', ['item' => []]);

            $instances = $this->storage->getInstances($identifier);

            expect($instances)->toBeArray();
            expect($instances)->toContain('cart');
            expect($instances)->toContain('wishlist');
        });
    });

    describe('forgetIdentifier', function (): void {
        it('removes all instances for identifier', function (): void {
            $identifier = 'db-storage-forget-all-' . uniqid();

            $this->storage->putItems($identifier, 'cart', ['item' => []]);
            $this->storage->putItems($identifier, 'wishlist', ['item' => []]);

            $this->storage->forgetIdentifier($identifier);

            expect($this->storage->getInstances($identifier))->toBeEmpty();
        });
    });

    describe('metadata operations', function (): void {
        it('stores and retrieves metadata', function (): void {
            $identifier = 'db-storage-meta-' . uniqid();
            $this->storage->putItems($identifier, 'default', []);

            $this->storage->putMetadata($identifier, 'default', 'customer_note', 'Please gift wrap');
            $value = $this->storage->getMetadata($identifier, 'default', 'customer_note');

            expect($value)->toBe('Please gift wrap');
        });

        it('stores metadata in batch', function (): void {
            $identifier = 'db-storage-meta-batch-' . uniqid();
            $this->storage->putItems($identifier, 'default', []);

            $this->storage->putMetadataBatch($identifier, 'default', [
                'note' => 'Test note',
                'priority' => 'high',
            ]);

            $all = $this->storage->getAllMetadata($identifier, 'default');

            expect($all)->toHaveKey('note');
            expect($all)->toHaveKey('priority');
        });

        it('clears metadata', function (): void {
            $identifier = 'db-storage-meta-clear-' . uniqid();
            $this->storage->putItems($identifier, 'default', []);
            $this->storage->putMetadata($identifier, 'default', 'key', 'value');

            $this->storage->clearMetadata($identifier, 'default');

            $all = $this->storage->getAllMetadata($identifier, 'default');
            expect($all)->toBeEmpty();
        });
    });

    describe('clearAll', function (): void {
        it('clears all cart data', function (): void {
            $identifier = 'db-storage-clear-all-' . uniqid();

            $this->storage->putItems($identifier, 'default', ['item' => []]);
            $this->storage->putConditions($identifier, 'default', ['cond' => []]);
            $this->storage->putMetadata($identifier, 'default', 'key', 'value');

            $this->storage->clearAll($identifier, 'default');

            expect($this->storage->getItems($identifier, 'default'))->toBeEmpty();
            expect($this->storage->getConditions($identifier, 'default'))->toBeEmpty();
        });
    });

    describe('version', function (): void {
        it('returns version for cart', function (): void {
            $identifier = 'db-storage-version-' . uniqid();
            $this->storage->putItems($identifier, 'default', ['item' => []]);

            $version = $this->storage->getVersion($identifier, 'default');

            expect($version)->toBeInt();
            expect($version)->toBeGreaterThanOrEqual(1);
        });

        it('returns 0 or null for non-existent cart', function (): void {
            $version = $this->storage->getVersion('non-existent-' . uniqid(), 'default');

            expect($version)->toBeIn([0, null]);
        });
    });

    describe('withOwner', function (): void {
        it('creates new instance with owner scope', function (): void {
            $user = User::query()->create([
                'name' => 'Storage Owner',
                'email' => 'storage-owner-' . uniqid() . '@example.com',
                'password' => 'secret',
            ]);

            $scoped = $this->storage->withOwner($user);

            expect($scoped)->toBeInstanceOf(DatabaseStorage::class);
            expect($scoped->getOwnerType())->toBe(get_class($user));
            expect($scoped->getOwnerId())->toBe($user->id);
        });

        it('creates instance with null owner', function (): void {
            $scoped = $this->storage->withOwner(null);

            expect($scoped)->toBeInstanceOf(DatabaseStorage::class);
            expect($scoped->getOwnerType())->toBeNull();
            expect($scoped->getOwnerId())->toBeNull();
        });
    });

    describe('owner accessors', function (): void {
        it('returns null owner type for unscoped storage', function (): void {
            expect($this->storage->getOwnerType())->toBeNull();
        });

        it('returns null owner id for unscoped storage', function (): void {
            expect($this->storage->getOwnerId())->toBeNull();
        });
    });
});
