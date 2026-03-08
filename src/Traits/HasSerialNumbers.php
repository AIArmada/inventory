<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Traits;

use AIArmada\Inventory\Enums\SerialCondition;
use AIArmada\Inventory\Models\InventorySerial;
use AIArmada\Inventory\States\Available;
use AIArmada\Inventory\States\SerialStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property-read Collection<int, InventorySerial> $serials
 */
trait HasSerialNumbers
{
    /**
     * @return MorphMany<InventorySerial, $this>
     */
    public function serials(): MorphMany
    {
        return $this->morphMany(InventorySerial::class, 'inventoryable');
    }

    /**
     * Get available serials for this item.
     *
     * @return Collection<int, InventorySerial>
     */
    public function availableSerials(): Collection
    {
        return $this->serials()
            ->where('status', SerialStatus::normalize(Available::class))
            ->get();
    }

    /**
     * Get sellable serials (available or refurbished in new condition).
     *
     * @return Collection<int, InventorySerial>
     */
    public function sellableSerials(): Collection
    {
        return $this->serials()
            ->sellable()
            ->get();
    }

    /**
     * Get serials at a specific location.
     *
     * @return Collection<int, InventorySerial>
     */
    public function serialsAtLocation(string $locationId): Collection
    {
        return $this->serials()
            ->atLocation($locationId)
            ->get();
    }

    /**
     * Get serials by status.
     *
     * @return Collection<int, InventorySerial>
     */
    public function serialsByStatus(SerialStatus | string $status): Collection
    {
        return $this->serials()
            ->where('status', SerialStatus::normalize($status))
            ->get();
    }

    /**
     * Get serials by condition.
     *
     * @return Collection<int, InventorySerial>
     */
    public function serialsByCondition(SerialCondition $condition): Collection
    {
        return $this->serials()
            ->where('condition', $condition->value)
            ->get();
    }

    /**
     * Register a new serial number.
     */
    public function registerSerial(
        string $serialNumber,
        ?string $locationId = null,
        ?string $batchId = null,
        SerialCondition $condition = SerialCondition::New,
        ?int $unitCostMinor = null,
        ?Carbon $warrantyExpiresAt = null
    ): InventorySerial {
        return $this->serials()->create([
            'serial_number' => $serialNumber,
            'location_id' => $locationId,
            'batch_id' => $batchId,
            'status' => SerialStatus::normalize(Available::class),
            'condition' => $condition->value,
            'unit_cost_minor' => $unitCostMinor,
            'warranty_expires_at' => $warrantyExpiresAt,
            'received_at' => now(),
        ]);
    }

    /**
     * Register multiple serial numbers.
     *
     * @param  array<int, string>  $serialNumbers
     * @return Collection<int, InventorySerial>
     */
    public function registerSerials(
        array $serialNumbers,
        ?string $locationId = null,
        ?string $batchId = null,
        SerialCondition $condition = SerialCondition::New,
        ?int $unitCostMinor = null,
        ?Carbon $warrantyExpiresAt = null
    ): Collection {
        $serials = new Collection;

        foreach ($serialNumbers as $serialNumber) {
            $serials->push($this->registerSerial(
                $serialNumber,
                $locationId,
                $batchId,
                $condition,
                $unitCostMinor,
                $warrantyExpiresAt
            ));
        }

        return $serials;
    }

    /**
     * Get count of serials by status.
     *
     * @return array<string, int>
     */
    public function serialCountsByStatus(): array
    {
        $counts = $this->serials()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $result = [];
        foreach (SerialStatus::classes() as $statusClass) {
            $value = $statusClass::getMorphClass();
            $result[$value] = $counts[$value] ?? 0;
        }

        return $result;
    }

    /**
     * Get count of serials by condition.
     *
     * @return array<string, int>
     */
    public function serialCountsByCondition(): array
    {
        $counts = $this->serials()
            ->selectRaw('`condition`, count(*) as count')
            ->groupBy('condition')
            ->pluck('count', 'condition')
            ->toArray();

        $result = [];
        foreach (SerialCondition::cases() as $condition) {
            $result[$condition->value] = $counts[$condition->value] ?? 0;
        }

        return $result;
    }

    /**
     * Get total serial count.
     */
    public function totalSerialCount(): int
    {
        return $this->serials()->count();
    }

    /**
     * Get available serial count.
     */
    public function availableSerialCount(): int
    {
        return $this->serials()
            ->where('status', SerialStatus::normalize(Available::class))
            ->count();
    }

    /**
     * Get sellable serial count.
     */
    public function sellableSerialCount(): int
    {
        return $this->serials()
            ->sellable()
            ->count();
    }

    /**
     * Check if any serial is available.
     */
    public function hasAvailableSerial(): bool
    {
        return $this->serials()
            ->where('status', SerialStatus::normalize(Available::class))
            ->exists();
    }

    /**
     * Get the next available serial (FIFO).
     */
    public function getNextAvailableSerial(?string $locationId = null): ?InventorySerial
    {
        $query = $this->serials()
            ->where('status', SerialStatus::normalize(Available::class))
            ->orderBy('received_at');

        if ($locationId !== null) {
            $query->where('location_id', $locationId);
        }

        return $query->first();
    }

    /**
     * Find serial by serial number.
     */
    public function findSerial(string $serialNumber): ?InventorySerial
    {
        return $this->serials()
            ->where('serial_number', $serialNumber)
            ->first();
    }

    /**
     * Check if serial number exists for this model.
     */
    public function hasSerial(string $serialNumber): bool
    {
        return $this->serials()
            ->where('serial_number', $serialNumber)
            ->exists();
    }

    /**
     * Get total value of all serials.
     */
    public function totalSerialValue(): int
    {
        return (int) $this->serials()->sum('unit_cost_minor');
    }

    /**
     * Get total value of available serials.
     */
    public function availableSerialValue(): int
    {
        return (int) $this->serials()
            ->where('status', SerialStatus::normalize(Available::class))
            ->sum('unit_cost_minor');
    }

    /**
     * Get serials with expiring warranty.
     *
     * @return Collection<int, InventorySerial>
     */
    public function serialsWithExpiringWarranty(int $daysAhead = 30): Collection
    {
        return $this->serials()
            ->whereNotNull('warranty_expires_at')
            ->where('warranty_expires_at', '>', now())
            ->where('warranty_expires_at', '<=', now()->addDays($daysAhead))
            ->orderBy('warranty_expires_at')
            ->get();
    }

    /**
     * Get serials under warranty.
     *
     * @return Collection<int, InventorySerial>
     */
    public function serialsUnderWarranty(): Collection
    {
        return $this->serials()
            ->whereNotNull('warranty_expires_at')
            ->where('warranty_expires_at', '>', now())
            ->get();
    }
}
