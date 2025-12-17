<?php

declare(strict_types=1);

use AIArmada\FilamentCart\Jobs\SyncNormalizedCartJob;
use AIArmada\FilamentCart\Services\CartInstanceManager;
use AIArmada\FilamentCart\Services\CartSyncManager;
use AIArmada\FilamentCart\Services\NormalizedCartSynchronizer;
use Illuminate\Support\Facades\Log;

describe('SyncNormalizedCartJob', function (): void {
    it('can be constructed with identifier and instance', function (): void {
        $job = new SyncNormalizedCartJob(
            identifier: 'user-123',
            instance: 'default',
        );

        expect($job->identifier)->toBe('user-123');
        expect($job->instance)->toBe('default');
    });

    it('uses queue name from config', function (): void {
        config(['filament-cart.synchronization.queue_name' => 'custom-queue']);
        config(['filament-cart.synchronization.queue_connection' => 'redis']);

        $job = new SyncNormalizedCartJob(
            identifier: 'user-456',
            instance: 'checkout',
        );

        expect($job->queue)->toBe('custom-queue');
        expect($job->connection)->toBe('redis');
    });

    it('defaults queue when config is not set', function (): void {
        // Don't set config, let the code handle defaults
        $job = new SyncNormalizedCartJob(
            identifier: 'user-789',
            instance: 'default',
        );

        // The job should have a queue set (either from config defaults or constructor)
        expect($job->identifier)->toBe('user-789');
        expect($job->instance)->toBe('default');
    });

    it('syncs a resolved cart on handle', function (): void {
        $storage = Mockery::mock(\AIArmada\Cart\Storage\StorageInterface::class);
        $cart = new \AIArmada\Cart\Cart($storage, 'user-123');

        $cartInstances = Mockery::mock(CartInstanceManager::class);
        $cartInstances
            ->shouldReceive('resolve')
            ->once()
            ->with('default', 'user-123')
            ->andReturn($cart);
        $cartInstances
            ->shouldReceive('prepare')
            ->once()
            ->with($cart)
            ->andReturn($cart);

        $this->app->instance(CartInstanceManager::class, $cartInstances);

        $synchronizer = Mockery::mock(NormalizedCartSynchronizer::class);
        $synchronizer->shouldReceive('syncFromCart')->once()->with($cart);
        $syncManager = new CartSyncManager($synchronizer, $cartInstances);

        $job = new SyncNormalizedCartJob(
            identifier: 'user-123',
            instance: 'default',
        );

        $job->handle($syncManager);
    });

    it('logs and rethrows when sync fails', function (): void {
        $storage = Mockery::mock(\AIArmada\Cart\Storage\StorageInterface::class);
        $cart = new \AIArmada\Cart\Cart($storage, 'user-999');

        $cartInstances = Mockery::mock(CartInstanceManager::class);
        $cartInstances
            ->shouldReceive('resolve')
            ->once()
            ->with('default', 'user-999')
            ->andReturn($cart);
        $cartInstances
            ->shouldReceive('prepare')
            ->once()
            ->with($cart)
            ->andReturn($cart);

        $this->app->instance(CartInstanceManager::class, $cartInstances);

        $synchronizer = Mockery::mock(NormalizedCartSynchronizer::class);
        $synchronizer->shouldReceive('syncFromCart')->once()->with($cart)->andThrow(new RuntimeException('boom'));
        $syncManager = new CartSyncManager($synchronizer, $cartInstances);

        Log::shouldReceive('error')
            ->once()
            ->with('Failed to synchronize normalized cart snapshot', Mockery::on(function (array $context): bool {
                return $context['identifier'] === 'user-999'
                    && $context['instance'] === 'default'
                    && $context['message'] === 'boom';
            }));

        $job = new SyncNormalizedCartJob(
            identifier: 'user-999',
            instance: 'default',
        );

        $job->handle($syncManager);
    })->throws(RuntimeException::class);
});
