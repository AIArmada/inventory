<?php

declare(strict_types=1);

namespace AIArmada\Shipping\Services;

use AIArmada\Shipping\Data\AddressData;
use AIArmada\Shipping\Models\ShippingRate;
use AIArmada\Shipping\Models\ShippingZone;
use Illuminate\Support\Collection;

/**
 * Resolves shipping zones and rates for addresses.
 */
class ShippingZoneResolver
{
    /**
     * Parameter-keyed cache for zone resolution.
     *
     * @var array<string, ShippingZone|null>
     */
    private array $resolvedZones = [];

    /**
     * Resolve the matching zone for an address.
     *
     * Results are cached for the request lifetime, keyed by address + owner.
     * This ensures different addresses within the same request get correct zones.
     */
    public function resolve(AddressData $address, ?int $ownerId = null, ?string $ownerType = null): ?ShippingZone
    {
        $cacheKey = $this->buildCacheKey($address, $ownerId, $ownerType);

        if (array_key_exists($cacheKey, $this->resolvedZones)) {
            return $this->resolvedZones[$cacheKey];
        }

        return $this->resolvedZones[$cacheKey] = $this->performZoneResolution($address, $ownerId, $ownerType);
    }

    /**
     * Clear the zone resolution cache.
     *
     * Useful for testing or when zone configuration changes mid-request.
     */
    public function clearCache(): void
    {
        $this->resolvedZones = [];
    }

    /**
     * Get all matching zones for an address (not just the first).
     *
     * @return Collection<int, ShippingZone>
     */
    public function resolveAll(AddressData $address, ?int $ownerId = null, ?string $ownerType = null): Collection
    {
        $query = ShippingZone::query()
            ->active()
            ->ordered();

        if ($ownerId !== null && $ownerType !== null) {
            $query->where('owner_id', $ownerId)
                ->where('owner_type', $ownerType);
        }

        return $query->get()
            ->filter(fn (ShippingZone $zone) => $zone->matchesAddress($address) || $zone->is_default);
    }

    /**
     * Get applicable rates for an address.
     *
     * @return Collection<int, ShippingRate>
     */
    public function getApplicableRates(
        AddressData $address,
        ?string $carrierCode = null,
        ?int $ownerId = null,
        ?string $ownerType = null
    ): Collection {
        $zone = $this->resolve($address, $ownerId, $ownerType);

        if ($zone === null) {
            return collect();
        }

        return $zone->rates()
            ->active()
            ->forCarrier($carrierCode)
            ->get();
    }

    /**
     * Check if an address is serviceable (has matching zone).
     */
    public function isServiceable(AddressData $address, ?int $ownerId = null, ?string $ownerType = null): bool
    {
        return $this->resolve($address, $ownerId, $ownerType) !== null;
    }

    /**
     * Test which zone an address matches (useful for debugging).
     *
     * @return array{matched: bool, zone: ?ShippingZone, reason: string}
     */
    public function test(AddressData $address, ?int $ownerId = null, ?string $ownerType = null): array
    {
        $zone = $this->resolve($address, $ownerId, $ownerType);

        if ($zone === null) {
            return [
                'matched' => false,
                'zone' => null,
                'reason' => 'No matching zone found for this address.',
            ];
        }

        $reason = $zone->is_default
            ? 'Matched to default zone.'
            : "Matched to zone '{$zone->name}' via {$zone->type} rule.";

        return [
            'matched' => true,
            'zone' => $zone,
            'reason' => $reason,
        ];
    }

    /**
     * Perform the actual zone resolution (uncached).
     */
    private function performZoneResolution(AddressData $address, ?int $ownerId, ?string $ownerType): ?ShippingZone
    {
        $query = ShippingZone::query()
            ->active()
            ->ordered();

        if ($ownerId !== null && $ownerType !== null) {
            $query->where('owner_id', $ownerId)
                ->where('owner_type', $ownerType);
        }

        $zones = $query->get();

        foreach ($zones as $zone) {
            if ($zone->matchesAddress($address)) {
                return $zone;
            }
        }

        // Fall back to default zone
        return $zones->firstWhere('is_default', true);
    }

    /**
     * Build a cache key from address and owner parameters.
     */
    private function buildCacheKey(AddressData $address, ?int $ownerId, ?string $ownerType): string
    {
        return md5(serialize([
            'country' => $address->countryCode,
            'state' => $address->state,
            'city' => $address->city,
            'postal' => $address->postCode,
            'owner_id' => $ownerId,
            'owner_type' => $ownerType,
        ]));
    }
}
