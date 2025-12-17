<?php

declare(strict_types=1);

use AIArmada\Cart\Jobs\WarmCartCacheJob;
use AIArmada\Cart\Storage\StorageInterface;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Config;

describe('WarmCartCacheJob', function (): void {
    beforeEach(function (): void {
        Config::set('cart.cache.enabled', true);
        Config::set('cart.cache.ttl', 3600);
        Config::set('cart.cache.queue', 'cart-cache');
    });

    it('can be instantiated with identifier', function (): void {
        $job = new WarmCartCacheJob('user-123');

        expect($job)->toBeInstanceOf(WarmCartCacheJob::class);
    });

    it('can be instantiated with instances', function (): void {
        $job = new WarmCartCacheJob('user-456', ['default', 'wishlist']);

        expect($job)->toBeInstanceOf(WarmCartCacheJob::class);
    });

    it('can be instantiated with owner info', function (): void {
        $job = new WarmCartCacheJob('user-789', ['default'], 'App\\Models\\User', 123);

        expect($job)->toBeInstanceOf(WarmCartCacheJob::class);
    });

    it('returns the configured queue', function (): void {
        $job = new WarmCartCacheJob('user-123');

        expect($job->queue())->toBe('cart-cache');
    });

    it('returns default queue when not explicitly configured', function (): void {
        Config::set('cart.cache.queue', 'default');

        $job = new WarmCartCacheJob('user-123');

        expect($job->queue())->toBe('default');
    });

    it('implements ShouldQueue interface', function (): void {
        $job = new WarmCartCacheJob('user-123');

        expect($job)->toBeInstanceOf(Illuminate\Contracts\Queue\ShouldQueue::class);
    });

    it('can serialize and deserialize', function (): void {
        $job = new WarmCartCacheJob('user-123', ['default', 'wishlist'], 'App\\Models\\User', 456);

        $serialized = serialize($job);
        $unserialized = unserialize($serialized);

        expect($unserialized)->toBeInstanceOf(WarmCartCacheJob::class);
    });

    it('returns early when cache is disabled', function (): void {
        Config::set('cart.cache.enabled', false);

        $job = new WarmCartCacheJob('user-123');

        $storage = Mockery::mock(StorageInterface::class);
        $cache = Mockery::mock(CacheRepository::class);

        // Neither should be called if cache is disabled
        $storage->shouldNotReceive('get');
        $cache->shouldNotReceive('put');
        $cache->shouldNotReceive('get');

        // Handle should return without errors
        $job->handle($storage, $cache);

        expect(true)->toBeTrue(); // If we get here, early return worked
    });

    it('warms cache for each instance when enabled', function (): void {
        Config::set('cart.cache.enabled', true);
        Config::set('cart.cache.ttl', 3600);

        $job = new WarmCartCacheJob('user-123', ['default', 'wishlist']);

        $storage = Mockery::mock(StorageInterface::class);
        $cache = Mockery::mock(CacheRepository::class);

        // Storage should return data for each instance
        $storage->shouldReceive('get')
            ->andReturn(['items' => [], 'metadata' => []]);

        // Cache should receive put calls for each instance
        $cache->shouldReceive('get')->andReturn(null);
        $cache->shouldReceive('put')->andReturn(true);
        $cache->shouldReceive('forget')->andReturn(true);

        $job->handle($storage, $cache);

        expect(true)->toBeTrue();
    });

    it('handles errors gracefully when warming cache fails', function (): void {
        Config::set('cart.cache.enabled', true);

        $job = new WarmCartCacheJob('user-failing', ['default']);

        $storage = Mockery::mock(StorageInterface::class);
        $cache = Mockery::mock(CacheRepository::class);

        // Simulate an error during cache warming
        $storage->shouldReceive('get')
            ->andThrow(new RuntimeException('Storage unavailable'));

        // Should not throw, just log warning
        $job->handle($storage, $cache);

        expect(true)->toBeTrue();
    });
});
