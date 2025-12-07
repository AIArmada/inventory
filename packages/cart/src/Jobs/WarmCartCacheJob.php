<?php

declare(strict_types=1);

namespace AIArmada\Cart\Jobs;

use AIArmada\Cart\Infrastructure\Caching\CachedCartRepository;
use AIArmada\Cart\Storage\StorageInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Job to warm cart cache proactively.
 *
 * Can be dispatched:
 * - After cart creation
 * - After cache invalidation
 * - On a schedule for active carts
 */
final class WarmCartCacheJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string>  $instances
     */
    public function __construct(
        private readonly string $identifier,
        private readonly array $instances = ['default'],
        private readonly ?string $ownerType = null,
        private readonly string|int|null $ownerId = null
    ) {}

    public function handle(StorageInterface $storage, CacheRepository $cache): void
    {
        $ttl = config('cart.cache.ttl', 3600);

        $cachedRepository = new CachedCartRepository($storage, $cache, $ttl);

        foreach ($this->instances as $instance) {
            try {
                $cachedRepository->warmCache($this->identifier, $instance);

                Log::debug('Cart cache warmed', [
                    'identifier' => $this->identifier,
                    'instance' => $instance,
                    'owner_type' => $this->ownerType,
                    'owner_id' => $this->ownerId,
                ]);
            } catch (Throwable $e) {
                Log::warning('Failed to warm cart cache', [
                    'identifier' => $this->identifier,
                    'instance' => $instance,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Get the queue this job should run on.
     */
    public function queue(): string
    {
        return config('cart.cache.queue', 'default');
    }
}
