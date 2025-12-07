<?php

declare(strict_types=1);

use AIArmada\Cart\Infrastructure\Caching\CachedCartRepository;
use AIArmada\Cart\Storage\StorageInterface;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    Cache::flush();

    $this->storage = Mockery::mock(StorageInterface::class);
    $this->storage->shouldReceive('getOwnerType')->andReturnNull()->byDefault();
    $this->storage->shouldReceive('getOwnerId')->andReturnNull()->byDefault();
    $this->cache = Cache::store();
    $this->cachedRepository = new CachedCartRepository($this->storage, $this->cache, 3600);
});

describe('CachedCartRepository', function (): void {
    describe('read-through caching', function (): void {
        it('caches items on first read and returns cached on second', function (): void {
            $items = ['item-1' => ['name' => 'Test Item', 'price' => 1000]];

            $this->storage->shouldReceive('getItems')
                ->once()
                ->with('cart-123', 'default')
                ->andReturn($items);

            // First read - should hit storage
            $result1 = $this->cachedRepository->getItems('cart-123', 'default');
            expect($result1)->toBe($items);

            // Second read - should hit cache, not storage
            $result2 = $this->cachedRepository->getItems('cart-123', 'default');
            expect($result2)->toBe($items);
        });

        it('caches conditions on first read', function (): void {
            $conditions = ['tax' => ['type' => 'percentage', 'value' => '10']];

            $this->storage->shouldReceive('getConditions')
                ->once()
                ->with('cart-123', 'default')
                ->andReturn($conditions);

            $result1 = $this->cachedRepository->getConditions('cart-123', 'default');
            $result2 = $this->cachedRepository->getConditions('cart-123', 'default');

            expect($result1)->toBe($conditions);
            expect($result2)->toBe($conditions);
        });

        it('caches has check result', function (): void {
            $this->storage->shouldReceive('has')
                ->once()
                ->with('cart-123', 'default')
                ->andReturn(true);

            $result1 = $this->cachedRepository->has('cart-123', 'default');
            $result2 = $this->cachedRepository->has('cart-123', 'default');

            expect($result1)->toBeTrue();
            expect($result2)->toBeTrue();
        });

        it('caches metadata on first read', function (): void {
            $this->storage->shouldReceive('getMetadata')
                ->once()
                ->with('cart-123', 'default', 'notes')
                ->andReturn('Customer notes');

            $result1 = $this->cachedRepository->getMetadata('cart-123', 'default', 'notes');
            $result2 = $this->cachedRepository->getMetadata('cart-123', 'default', 'notes');

            expect($result1)->toBe('Customer notes');
            expect($result2)->toBe('Customer notes');
        });

        it('caches getAllMetadata result', function (): void {
            $metadata = ['notes' => 'Test', 'user_id' => 123];

            $this->storage->shouldReceive('getAllMetadata')
                ->once()
                ->with('cart-123', 'default')
                ->andReturn($metadata);

            $result1 = $this->cachedRepository->getAllMetadata('cart-123', 'default');
            $result2 = $this->cachedRepository->getAllMetadata('cart-123', 'default');

            expect($result1)->toBe($metadata);
            expect($result2)->toBe($metadata);
        });

        it('caches version', function (): void {
            $this->storage->shouldReceive('getVersion')
                ->once()
                ->with('cart-123', 'default')
                ->andReturn(5);

            $result1 = $this->cachedRepository->getVersion('cart-123', 'default');
            $result2 = $this->cachedRepository->getVersion('cart-123', 'default');

            expect($result1)->toBe(5);
            expect($result2)->toBe(5);
        });

        it('caches cart ID', function (): void {
            $this->storage->shouldReceive('getId')
                ->once()
                ->with('cart-123', 'default')
                ->andReturn('uuid-123');

            $result1 = $this->cachedRepository->getId('cart-123', 'default');
            $result2 = $this->cachedRepository->getId('cart-123', 'default');

            expect($result1)->toBe('uuid-123');
            expect($result2)->toBe('uuid-123');
        });

        it('caches instances list', function (): void {
            $instances = ['default', 'wishlist'];

            $this->storage->shouldReceive('getInstances')
                ->once()
                ->with('cart-123')
                ->andReturn($instances);

            $result1 = $this->cachedRepository->getInstances('cart-123');
            $result2 = $this->cachedRepository->getInstances('cart-123');

            expect($result1)->toBe($instances);
            expect($result2)->toBe($instances);
        });

        it('caches created_at timestamp', function (): void {
            $timestamp = '2024-01-15 10:30:00';

            $this->storage->shouldReceive('getCreatedAt')
                ->once()
                ->with('cart-123', 'default')
                ->andReturn($timestamp);

            $result1 = $this->cachedRepository->getCreatedAt('cart-123', 'default');
            $result2 = $this->cachedRepository->getCreatedAt('cart-123', 'default');

            expect($result1)->toBe($timestamp);
            expect($result2)->toBe($timestamp);
        });

        it('does not cache updated_at timestamp', function (): void {
            $this->storage->shouldReceive('getUpdatedAt')
                ->twice()
                ->with('cart-123', 'default')
                ->andReturn('2024-01-15 10:30:00', '2024-01-15 10:31:00');

            $result1 = $this->cachedRepository->getUpdatedAt('cart-123', 'default');
            $result2 = $this->cachedRepository->getUpdatedAt('cart-123', 'default');

            expect($result1)->toBe('2024-01-15 10:30:00');
            expect($result2)->toBe('2024-01-15 10:31:00');
        });
    });

    describe('cache invalidation', function (): void {
        it('invalidates items cache when putting items', function (): void {
            $items = ['item-1' => ['name' => 'Test']];

            // First, cache the items
            $this->storage->shouldReceive('getItems')
                ->once()
                ->with('cart-123', 'default')
                ->andReturn($items);

            $this->cachedRepository->getItems('cart-123', 'default');

            // Now put new items - should invalidate cache
            $newItems = ['item-2' => ['name' => 'New Item']];
            $this->storage->shouldReceive('putItems')
                ->once()
                ->with('cart-123', 'default', $newItems);
            $this->storage->shouldReceive('getOwnerType')->andReturnNull();
            $this->storage->shouldReceive('getOwnerId')->andReturnNull();

            $this->cachedRepository->putItems('cart-123', 'default', $newItems);

            // Next read should hit storage again
            $this->storage->shouldReceive('getItems')
                ->once()
                ->with('cart-123', 'default')
                ->andReturn($newItems);

            $result = $this->cachedRepository->getItems('cart-123', 'default');
            expect($result)->toBe($newItems);
        });

        it('invalidates conditions cache when putting conditions', function (): void {
            $conditions = ['tax' => ['value' => '10']];

            $this->storage->shouldReceive('getConditions')
                ->once()
                ->with('cart-123', 'default')
                ->andReturn($conditions);

            $this->cachedRepository->getConditions('cart-123', 'default');

            $newConditions = ['discount' => ['value' => '5']];
            $this->storage->shouldReceive('putConditions')
                ->once()
                ->with('cart-123', 'default', $newConditions);
            $this->storage->shouldReceive('getOwnerType')->andReturnNull();
            $this->storage->shouldReceive('getOwnerId')->andReturnNull();

            $this->cachedRepository->putConditions('cart-123', 'default', $newConditions);

            $this->storage->shouldReceive('getConditions')
                ->once()
                ->with('cart-123', 'default')
                ->andReturn($newConditions);

            $result = $this->cachedRepository->getConditions('cart-123', 'default');
            expect($result)->toBe($newConditions);
        });

        it('invalidates all caches when forgetting cart', function (): void {
            $this->storage->shouldReceive('getItems')
                ->once()
                ->with('cart-123', 'default')
                ->andReturn(['item' => []]);

            $this->cachedRepository->getItems('cart-123', 'default');

            $this->storage->shouldReceive('forget')
                ->once()
                ->with('cart-123', 'default');
            $this->storage->shouldReceive('getOwnerType')->andReturnNull();
            $this->storage->shouldReceive('getOwnerId')->andReturnNull();

            $this->cachedRepository->forget('cart-123', 'default');

            $this->storage->shouldReceive('getItems')
                ->once()
                ->with('cart-123', 'default')
                ->andReturn([]);

            $result = $this->cachedRepository->getItems('cart-123', 'default');
            expect($result)->toBe([]);
        });

        it('invalidates caches when clearing all', function (): void {
            $this->storage->shouldReceive('getItems')
                ->once()
                ->with('cart-123', 'default')
                ->andReturn(['item' => []]);

            $this->cachedRepository->getItems('cart-123', 'default');

            $this->storage->shouldReceive('clearAll')
                ->once()
                ->with('cart-123', 'default');
            $this->storage->shouldReceive('getOwnerType')->andReturnNull();
            $this->storage->shouldReceive('getOwnerId')->andReturnNull();

            $this->cachedRepository->clearAll('cart-123', 'default');

            $this->storage->shouldReceive('getItems')
                ->once()
                ->with('cart-123', 'default')
                ->andReturn([]);

            $result = $this->cachedRepository->getItems('cart-123', 'default');
            expect($result)->toBe([]);
        });

        it('invalidates metadata cache when putting metadata', function (): void {
            $this->storage->shouldReceive('getAllMetadata')
                ->once()
                ->with('cart-123', 'default')
                ->andReturn(['notes' => 'old']);

            $this->cachedRepository->getAllMetadata('cart-123', 'default');

            $this->storage->shouldReceive('putMetadata')
                ->once()
                ->with('cart-123', 'default', 'notes', 'new');
            $this->storage->shouldReceive('getOwnerType')->andReturnNull();
            $this->storage->shouldReceive('getOwnerId')->andReturnNull();

            $this->cachedRepository->putMetadata('cart-123', 'default', 'notes', 'new');

            $this->storage->shouldReceive('getAllMetadata')
                ->once()
                ->with('cart-123', 'default')
                ->andReturn(['notes' => 'new']);

            $result = $this->cachedRepository->getAllMetadata('cart-123', 'default');
            expect($result)->toBe(['notes' => 'new']);
        });

        it('invalidates both caches on swap identifier', function (): void {
            // Cache old identifier
            $this->storage->shouldReceive('getItems')
                ->once()
                ->with('old-cart', 'default')
                ->andReturn(['item' => []]);

            $this->cachedRepository->getItems('old-cart', 'default');

            // Swap
            $this->storage->shouldReceive('swapIdentifier')
                ->once()
                ->with('old-cart', 'new-cart', 'default')
                ->andReturn(true);
            $this->storage->shouldReceive('getOwnerType')->andReturnNull();
            $this->storage->shouldReceive('getOwnerId')->andReturnNull();

            $result = $this->cachedRepository->swapIdentifier('old-cart', 'new-cart', 'default');

            expect($result)->toBeTrue();

            // Both should be invalidated - verify old cart cache is cleared
            $this->storage->shouldReceive('getItems')
                ->once()
                ->with('old-cart', 'default')
                ->andReturn([]);

            $oldResult = $this->cachedRepository->getItems('old-cart', 'default');
            expect($oldResult)->toBe([]);
        });
    });

    describe('write-through operations', function (): void {
        it('writes to storage when putting items', function (): void {
            $items = ['item-1' => ['name' => 'Test']];

            $this->storage->shouldReceive('putItems')
                ->once()
                ->with('cart-123', 'default', $items);
            $this->storage->shouldReceive('getOwnerType')->andReturnNull();
            $this->storage->shouldReceive('getOwnerId')->andReturnNull();

            $this->cachedRepository->putItems('cart-123', 'default', $items);
        });

        it('writes to storage when putting conditions', function (): void {
            $conditions = ['tax' => ['value' => '10']];

            $this->storage->shouldReceive('putConditions')
                ->once()
                ->with('cart-123', 'default', $conditions);
            $this->storage->shouldReceive('getOwnerType')->andReturnNull();
            $this->storage->shouldReceive('getOwnerId')->andReturnNull();

            $this->cachedRepository->putConditions('cart-123', 'default', $conditions);
        });

        it('writes both to storage with putBoth', function (): void {
            $items = ['item-1' => ['name' => 'Test']];
            $conditions = ['tax' => ['value' => '10']];

            $this->storage->shouldReceive('putBoth')
                ->once()
                ->with('cart-123', 'default', $items, $conditions);
            $this->storage->shouldReceive('getOwnerType')->andReturnNull();
            $this->storage->shouldReceive('getOwnerId')->andReturnNull();

            $this->cachedRepository->putBoth('cart-123', 'default', $items, $conditions);
        });
    });

    describe('owner delegation', function (): void {
        it('delegates withOwner to storage', function (): void {
            $owner = Mockery::mock(Illuminate\Database\Eloquent\Model::class);
            $newStorage = Mockery::mock(StorageInterface::class);

            $this->storage->shouldReceive('withOwner')
                ->once()
                ->with($owner)
                ->andReturn($newStorage);

            $result = $this->cachedRepository->withOwner($owner);

            expect($result)->toBeInstanceOf(CachedCartRepository::class);
        });

        it('delegates getOwnerType to storage', function (): void {
            $this->storage->shouldReceive('getOwnerType')
                ->once()
                ->andReturn('App\\Models\\User');

            $result = $this->cachedRepository->getOwnerType();

            expect($result)->toBe('App\\Models\\User');
        });

        it('delegates getOwnerId to storage', function (): void {
            $this->storage->shouldReceive('getOwnerId')
                ->once()
                ->andReturn(123);

            $result = $this->cachedRepository->getOwnerId();

            expect($result)->toBe(123);
        });
    });

    describe('AI & analytics methods pass-through', function (): void {
        it('passes through getLastActivityAt without caching', function (): void {
            $this->storage->shouldReceive('getLastActivityAt')
                ->twice()
                ->with('cart-123', 'default')
                ->andReturn('2024-01-15 10:30:00');

            $result1 = $this->cachedRepository->getLastActivityAt('cart-123', 'default');
            $result2 = $this->cachedRepository->getLastActivityAt('cart-123', 'default');

            expect($result1)->toBe('2024-01-15 10:30:00');
            expect($result2)->toBe('2024-01-15 10:30:00');
        });

        it('passes through touchLastActivity', function (): void {
            $this->storage->shouldReceive('touchLastActivity')
                ->once()
                ->with('cart-123', 'default');

            $this->cachedRepository->touchLastActivity('cart-123', 'default');
        });

        it('passes through checkout tracking methods', function (): void {
            $this->storage->shouldReceive('markCheckoutStarted')
                ->once()
                ->with('cart-123', 'default');

            $this->cachedRepository->markCheckoutStarted('cart-123', 'default');

            $this->storage->shouldReceive('getCheckoutStartedAt')
                ->once()
                ->with('cart-123', 'default')
                ->andReturn('2024-01-15 10:30:00');

            $result = $this->cachedRepository->getCheckoutStartedAt('cart-123', 'default');
            expect($result)->toBe('2024-01-15 10:30:00');
        });

        it('passes through abandonment tracking methods', function (): void {
            $this->storage->shouldReceive('markCheckoutAbandoned')
                ->once()
                ->with('cart-123', 'default');

            $this->cachedRepository->markCheckoutAbandoned('cart-123', 'default');

            $this->storage->shouldReceive('getCheckoutAbandonedAt')
                ->once()
                ->with('cart-123', 'default')
                ->andReturn('2024-01-15 11:00:00');

            $result = $this->cachedRepository->getCheckoutAbandonedAt('cart-123', 'default');
            expect($result)->toBe('2024-01-15 11:00:00');
        });

        it('passes through recovery tracking methods', function (): void {
            $this->storage->shouldReceive('incrementRecoveryAttempts')
                ->once()
                ->with('cart-123', 'default');

            $this->cachedRepository->incrementRecoveryAttempts('cart-123', 'default');

            $this->storage->shouldReceive('getRecoveryAttempts')
                ->once()
                ->with('cart-123', 'default')
                ->andReturn(3);

            $result = $this->cachedRepository->getRecoveryAttempts('cart-123', 'default');
            expect($result)->toBe(3);
        });
    });

    describe('event sourcing methods pass-through', function (): void {
        it('passes through event stream position methods', function (): void {
            $this->storage->shouldReceive('setEventStreamPosition')
                ->once()
                ->with('cart-123', 'default', 42);

            $this->cachedRepository->setEventStreamPosition('cart-123', 'default', 42);

            $this->storage->shouldReceive('getEventStreamPosition')
                ->once()
                ->with('cart-123', 'default')
                ->andReturn(42);

            $result = $this->cachedRepository->getEventStreamPosition('cart-123', 'default');
            expect($result)->toBe(42);
        });

        it('passes through aggregate version methods', function (): void {
            $this->storage->shouldReceive('setAggregateVersion')
                ->once()
                ->with('cart-123', 'default', 'v1.2.3');

            $this->cachedRepository->setAggregateVersion('cart-123', 'default', 'v1.2.3');

            $this->storage->shouldReceive('getAggregateVersion')
                ->once()
                ->with('cart-123', 'default')
                ->andReturn('v1.2.3');

            $result = $this->cachedRepository->getAggregateVersion('cart-123', 'default');
            expect($result)->toBe('v1.2.3');
        });

        it('passes through snapshot methods', function (): void {
            $this->storage->shouldReceive('markSnapshotTaken')
                ->once()
                ->with('cart-123', 'default');

            $this->cachedRepository->markSnapshotTaken('cart-123', 'default');

            $this->storage->shouldReceive('getSnapshotAt')
                ->once()
                ->with('cart-123', 'default')
                ->andReturn('2024-01-15 10:30:00');

            $result = $this->cachedRepository->getSnapshotAt('cart-123', 'default');
            expect($result)->toBe('2024-01-15 10:30:00');
        });
    });

    describe('cache warming', function (): void {
        it('warms cache by pre-fetching common data', function (): void {
            $items = ['item-1' => ['name' => 'Test']];
            $conditions = ['tax' => ['value' => '10']];
            $metadata = ['notes' => 'Customer notes'];

            $this->storage->shouldReceive('getItems')
                ->once()
                ->with('cart-123', 'default')
                ->andReturn($items);

            $this->storage->shouldReceive('getConditions')
                ->once()
                ->with('cart-123', 'default')
                ->andReturn($conditions);

            $this->storage->shouldReceive('getAllMetadata')
                ->once()
                ->with('cart-123', 'default')
                ->andReturn($metadata);

            $this->storage->shouldReceive('getVersion')
                ->once()
                ->with('cart-123', 'default')
                ->andReturn(1);

            $this->storage->shouldReceive('getId')
                ->once()
                ->with('cart-123', 'default')
                ->andReturn('uuid-123');

            $this->cachedRepository->warmCache('cart-123', 'default');

            // Subsequent reads should not hit storage
            expect($this->cachedRepository->getItems('cart-123', 'default'))->toBe($items);
            expect($this->cachedRepository->getConditions('cart-123', 'default'))->toBe($conditions);
            expect($this->cachedRepository->getAllMetadata('cart-123', 'default'))->toBe($metadata);
            expect($this->cachedRepository->getVersion('cart-123', 'default'))->toBe(1);
            expect($this->cachedRepository->getId('cart-123', 'default'))->toBe('uuid-123');
        });
    });

    describe('expiry methods', function (): void {
        it('delegates getExpiresAt to storage', function (): void {
            $this->storage->shouldReceive('getExpiresAt')
                ->once()
                ->with('cart-123', 'default')
                ->andReturn('2024-02-15 10:30:00');

            $result = $this->cachedRepository->getExpiresAt('cart-123', 'default');
            expect($result)->toBe('2024-02-15 10:30:00');
        });

        it('delegates isExpired to storage', function (): void {
            $this->storage->shouldReceive('isExpired')
                ->once()
                ->with('cart-123', 'default')
                ->andReturn(false);

            $result = $this->cachedRepository->isExpired('cart-123', 'default');
            expect($result)->toBeFalse();
        });
    });
});
