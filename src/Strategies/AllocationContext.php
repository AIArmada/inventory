<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Strategies;

final class AllocationContext
{
    public function __construct(
        public ?string $locationId = null,
        public ?string $orderId = null,
        public ?string $customerId = null,
        public bool $excludeExpiringSoon = false,
        public int $minDaysToExpiry = 7,
        public bool $allowPartialAllocation = true,
        public bool $createBackorderForShortfall = false,
        public ?float $originX = null,
        public ?float $originY = null,
        public ?float $originZ = null,
        public ?int $maxLocations = null,
        public bool $preferSameLocation = true,
        public bool $preferWholeBatches = false,
        public ?string $preferredZone = null,
        public ?string $excludeZone = null,
        /** @var array<string>|null */
        public ?array $excludeLocationIds = null,
        /** @var array<string>|null */
        public ?array $preferLocationIds = null,
    ) {}

    public static function forOrder(string $orderId, ?string $customerId = null): self
    {
        return new self(
            orderId: $orderId,
            customerId: $customerId,
            createBackorderForShortfall: true,
        );
    }

    public static function forLocation(string $locationId): self
    {
        return new self(locationId: $locationId);
    }

    public static function forPerishables(int $minDaysToExpiry = 7): self
    {
        return new self(
            excludeExpiringSoon: true,
            minDaysToExpiry: $minDaysToExpiry,
        );
    }

    public static function fromCoordinates(float $x, float $y, ?float $z = null): self
    {
        return new self(
            originX: $x,
            originY: $y,
            originZ: $z,
        );
    }

    public function withLocation(string $locationId): self
    {
        $clone = clone $this;
        $clone->locationId = $locationId;

        return $clone;
    }

    public function withOrder(string $orderId): self
    {
        $clone = clone $this;
        $clone->orderId = $orderId;

        return $clone;
    }

    public function withBackorderSupport(): self
    {
        $clone = clone $this;
        $clone->createBackorderForShortfall = true;

        return $clone;
    }

    public function withExpiryConstraint(int $minDays = 7): self
    {
        $clone = clone $this;
        $clone->excludeExpiringSoon = true;
        $clone->minDaysToExpiry = $minDays;

        return $clone;
    }

    public function withMaxLocations(int $max): self
    {
        $clone = clone $this;
        $clone->maxLocations = $max;

        return $clone;
    }

    public function withPreferredZone(string $zone): self
    {
        $clone = clone $this;
        $clone->preferredZone = $zone;

        return $clone;
    }

    public function excludingZone(string $zone): self
    {
        $clone = clone $this;
        $clone->excludeZone = $zone;

        return $clone;
    }

    /**
     * @param  array<string>  $locationIds
     */
    public function excludingLocations(array $locationIds): self
    {
        $clone = clone $this;
        $clone->excludeLocationIds = $locationIds;

        return $clone;
    }

    /**
     * @param  array<string>  $locationIds
     */
    public function preferringLocations(array $locationIds): self
    {
        $clone = clone $this;
        $clone->preferLocationIds = $locationIds;

        return $clone;
    }

    public function hasOriginCoordinates(): bool
    {
        return $this->originX !== null && $this->originY !== null;
    }
}
