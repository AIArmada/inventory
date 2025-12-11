<?php

declare(strict_types=1);

namespace AIArmada\Shipping\Services;

use AIArmada\Shipping\Contracts\RateSelectionStrategyInterface;
use AIArmada\Shipping\Data\AddressData;
use AIArmada\Shipping\Data\PackageData;
use AIArmada\Shipping\Data\RateQuoteData;
use AIArmada\Shipping\ShippingManager;
use AIArmada\Shipping\Strategies\CheapestRateStrategy;
use AIArmada\Shipping\Strategies\FastestRateStrategy;
use AIArmada\Shipping\Strategies\PreferredCarrierStrategy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Aggregates rates from multiple carriers and applies selection rules.
 */
class RateShoppingEngine
{
    protected RateSelectionStrategyInterface $strategy;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected readonly ShippingManager $shippingManager,
        protected readonly array $config = []
    ) {
        $this->strategy = $this->resolveStrategy();
    }

    /**
     * Get all available rates from all carriers.
     *
     * @param  array<PackageData>  $packages
     * @param  array<string, mixed>  $options
     * @return Collection<int, RateQuoteData>
     */
    public function getAllRates(
        AddressData $origin,
        AddressData $destination,
        array $packages,
        array $options = []
    ): Collection {
        $cacheKey = $this->buildCacheKey($origin, $destination, $packages);
        $cacheTtl = $this->config['cache_ttl'] ?? 300;

        if ($cacheTtl > 0) {
            return Cache::remember($cacheKey, $cacheTtl, function () use ($origin, $destination, $packages, $options) {
                return $this->fetchRatesFromAllCarriers($origin, $destination, $packages, $options);
            });
        }

        return $this->fetchRatesFromAllCarriers($origin, $destination, $packages, $options);
    }

    /**
     * Get the best rate based on selection strategy.
     *
     * @param  array<PackageData>  $packages
     * @param  array<string, mixed>  $options
     */
    public function getBestRate(
        AddressData $origin,
        AddressData $destination,
        array $packages,
        array $options = []
    ): ?RateQuoteData {
        $allRates = $this->getAllRates($origin, $destination, $packages, $options);

        if ($allRates->isEmpty()) {
            return $this->getFallbackRate($destination, $packages);
        }

        return $this->strategy->select($allRates, $options);
    }

    /**
     * Get rates for specific carriers only.
     *
     * @param  array<string>  $carriers
     * @param  array<PackageData>  $packages
     * @param  array<string, mixed>  $options
     * @return Collection<int, RateQuoteData>
     */
    public function getRatesFromCarriers(
        array $carriers,
        AddressData $origin,
        AddressData $destination,
        array $packages,
        array $options = []
    ): Collection {
        $rates = collect();

        foreach ($carriers as $carrierCode) {
            if ($this->shippingManager->hasDriver($carrierCode)) {
                $driver = $this->shippingManager->driver($carrierCode);

                if ($driver->servicesDestination($destination)) {
                    try {
                        $carrierRates = $driver->getRates($origin, $destination, $packages, $options);
                        $rates = $rates->merge($carrierRates);
                    } catch (Throwable $e) {
                        // Log error but continue with other carriers
                        report($e);
                    }
                }
            }
        }

        return $rates->sortBy('rate');
    }

    /**
     * Set the rate selection strategy.
     */
    public function setStrategy(RateSelectionStrategyInterface $strategy): self
    {
        $this->strategy = $strategy;

        return $this;
    }

    /**
     * Clear cached rates.
     */
    public function clearCache(): void
    {
        Cache::flush(); // In production, use tags: Cache::tags(['shipping', 'rates'])->flush()
    }

    /**
     * Fetch rates from all available carriers concurrently.
     *
     * Uses Laravel's Concurrency facade to fetch rates from multiple carriers
     * in parallel, dramatically improving performance when multiple carriers
     * are configured. Each carrier call is independent with no shared state.
     *
     * Performance improvement example:
     * - Sequential: 5 carriers × 500ms = 2.5 seconds
     * - Concurrent: ~500ms (slowest carrier)
     *
     * @param  array<PackageData>  $packages
     * @param  array<string, mixed>  $options
     * @return Collection<int, RateQuoteData>
     */
    protected function fetchRatesFromAllCarriers(
        AddressData $origin,
        AddressData $destination,
        array $packages,
        array $options = []
    ): Collection {
        $drivers = $this->shippingManager->getDriversForDestination($destination);

        if ($drivers->isEmpty()) {
            return collect();
        }

        // Extract carrier codes (primitives are safely serializable)
        $carrierCodes = $drivers->map(fn ($driver) => $driver->getCarrierCode())->all();

        // Build concurrent tasks - one per carrier
        // We pass primitives and re-resolve the driver in each child process
        // to avoid serialization issues with complex driver objects
        $tasks = collect($carrierCodes)->mapWithKeys(function (string $carrierCode) use ($origin, $destination, $packages, $options) {
            return [
                $carrierCode => function () use ($carrierCode, $origin, $destination, $packages, $options) {
                    try {
                        // Resolve driver fresh in child process
                        $driver = app(ShippingManager::class)->driver($carrierCode);

                        return $driver->getRates($origin, $destination, $packages, $options);
                    } catch (Throwable $e) {
                        // Log error but return empty - other carriers may succeed
                        report($e);

                        return collect();
                    }
                },
            ];
        })->all();

        // Execute all carrier calls concurrently
        $results = \Illuminate\Support\Facades\Concurrency::run($tasks);

        // Merge all successful results
        $rates = collect();
        foreach ($results as $carrierRates) {
            if ($carrierRates instanceof Collection) {
                $rates = $rates->merge($carrierRates);
            }
        }

        return $rates->sortBy('rate');
    }

    /**
     * Get fallback rate when no carrier rates available.
     *
     * @param  array<PackageData>  $packages
     */
    protected function getFallbackRate(AddressData $destination, array $packages): ?RateQuoteData
    {
        $fallbackEnabled = $this->config['fallback_to_manual'] ?? true;

        if (! $fallbackEnabled) {
            return null;
        }

        return $this->shippingManager->driver('manual')
            ->getRates(
                new AddressData(name: '', phone: '', address: '', postCode: ''),
                $destination,
                $packages
            )
            ->first();
    }

    /**
     * Build cache key for rate lookup.
     *
     * @param  array<PackageData>  $packages
     */
    protected function buildCacheKey(AddressData $origin, AddressData $destination, array $packages): string
    {
        $totalWeight = array_sum(array_map(fn (PackageData $p) => $p->weight, $packages));

        return 'shipping:rates:' . md5(serialize([
            'origin' => $origin->postCode,
            'destination' => $destination->postCode . $destination->countryCode,
            'weight' => $totalWeight,
            'packages' => count($packages),
        ]));
    }

    /**
     * Resolve the rate selection strategy based on config.
     *
     * Cached using once() because this method is parameterless and reads
     * only from immutable config. Safe for request-scoped caching.
     */
    protected function resolveStrategy(): RateSelectionStrategyInterface
    {
        return once(fn () => match ($this->config['strategy'] ?? 'cheapest') {
            'fastest' => new FastestRateStrategy,
            'preferred' => new PreferredCarrierStrategy($this->config['carrier_priority'] ?? []),
            default => new CheapestRateStrategy,
        });
    }
}
