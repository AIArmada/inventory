<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Storage\StorageInterface;
use AIArmada\FilamentCart\Jobs\SyncNormalizedCartJob;
use AIArmada\FilamentCart\Services\CartInstanceManager;
use AIArmada\FilamentCart\Services\CartSyncManager;
use AIArmada\FilamentCart\Services\NormalizedCartSynchronizer;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;

describe('CartSyncManager', function (): void {
    beforeEach(function (): void {
        $this->synchronizer = Mockery::mock(NormalizedCartSynchronizer::class);
        $this->cartInstances = Mockery::mock(CartInstanceManager::class);
        $this->manager = new CartSyncManager($this->synchronizer, $this->cartInstances);
        Queue::fake();
    });

    it('syncs cart synchronously by default', function (): void {
        Config::set('filament-cart.synchronization.queue_sync', false);

        $cart = new Cart(
            storage: Mockery::mock(StorageInterface::class),
            identifier: 'user-123',
            events: null,
            instanceName: 'default',
            eventsEnabled: false,
        );
        $this->cartInstances->shouldReceive('prepare')->with($cart)->andReturn($cart);
        $this->synchronizer->shouldReceive('syncFromCart')->with($cart)->once();

        $this->manager->sync($cart);

        Queue::assertNothingPushed();
    });

    it('queues sync if configured', function (): void {
        Config::set('filament-cart.synchronization.queue_sync', true);

        $cart = new Cart(
            storage: Mockery::mock(StorageInterface::class),
            identifier: 'user-123',
            events: null,
            instanceName: 'default',
            eventsEnabled: false,
        );

        $this->cartInstances->shouldReceive('prepare')->with($cart)->andReturn($cart);

        $this->manager->sync($cart);

        Queue::assertPushed(SyncNormalizedCartJob::class, function ($job) {
            return $job->identifier === 'user-123' && $job->instance === 'default';
        });

        $this->synchronizer->shouldNotReceive('syncFromCart');
    });

    it('forces synchronous sync even if queued configured', function (): void {
        Config::set('filament-cart.synchronization.queue_sync', true);

        $cart = new Cart(
            storage: Mockery::mock(StorageInterface::class),
            identifier: 'user-123',
            events: null,
            instanceName: 'default',
            eventsEnabled: false,
        );
        $this->cartInstances->shouldReceive('prepare')->with($cart)->andReturn($cart);
        $this->synchronizer->shouldReceive('syncFromCart')->with($cart)->once();

        $this->manager->sync($cart, force: true);

        Queue::assertNothingPushed();
    });

    it('syncs by identity', function (): void {
        $cart = new Cart(
            storage: Mockery::mock(StorageInterface::class),
            identifier: 'user-123',
            events: null,
            instanceName: 'default',
            eventsEnabled: false,
        );
        $this->cartInstances->shouldReceive('resolve')->with('default', 'user-123')->andReturn($cart);
        $this->cartInstances->shouldReceive('prepare')->with($cart)->andReturn($cart);
        $this->synchronizer->shouldReceive('syncFromCart')->with($cart)->once();

        $this->manager->syncByIdentity('default', 'user-123');
    });

    it('deletes by identity', function (): void {
        $this->synchronizer->shouldReceive('deleteNormalizedCart')->with('user-123', 'default')->once();

        $this->manager->deleteByIdentity('default', 'user-123');
    });
});
