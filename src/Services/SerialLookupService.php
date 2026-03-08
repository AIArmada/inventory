<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Services;

use AIArmada\Inventory\Enums\SerialCondition;
use AIArmada\Inventory\Models\InventorySerial;
use AIArmada\Inventory\States\SerialStatus;
use AIArmada\Inventory\States\Sold;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

final class SerialLookupService
{
    /**
     * Find serial by exact serial number.
     */
    public function findBySerialNumber(string $serialNumber): ?InventorySerial
    {
        $query = InventorySerial::query()->where('serial_number', $serialNumber);
        InventoryOwnerScope::applyToQueryByLocationRelation($query, 'location');

        return $query->first();
    }

    /**
     * Find serial by serial number or fail.
     */
    public function findBySerialNumberOrFail(string $serialNumber): InventorySerial
    {
        $query = InventorySerial::query()->where('serial_number', $serialNumber);
        InventoryOwnerScope::applyToQueryByLocationRelation($query, 'location');

        return $query->firstOrFail();
    }

    /**
     * Search serials by partial serial number.
     *
     * @return Collection<int, InventorySerial>
     */
    public function searchBySerialNumber(string $partialSerialNumber, int $limit = 25): Collection
    {
        $query = InventorySerial::query()
            ->where('serial_number', 'like', "%{$partialSerialNumber}%")
            ->orderBy('serial_number')
            ->limit($limit);

        InventoryOwnerScope::applyToQueryByLocationRelation($query, 'location');

        return $query->get();
    }

    /**
     * Find serial by order ID.
     */
    public function findByOrderId(string $orderId): ?InventorySerial
    {
        $query = InventorySerial::query()->where('order_id', $orderId);
        InventoryOwnerScope::applyToQueryByLocationRelation($query, 'location');

        return $query->first();
    }

    /**
     * Get all serials by order ID.
     *
     * @return Collection<int, InventorySerial>
     */
    public function getAllByOrderId(string $orderId): Collection
    {
        $query = InventorySerial::query()->where('order_id', $orderId);
        InventoryOwnerScope::applyToQueryByLocationRelation($query, 'location');

        return $query->get();
    }

    /**
     * Find serial by customer ID.
     *
     * @return Collection<int, InventorySerial>
     */
    public function getByCustomerId(string $customerId): Collection
    {
        $query = InventorySerial::query()
            ->where('customer_id', $customerId)
            ->orderBy('sold_at', 'desc');

        InventoryOwnerScope::applyToQueryByLocationRelation($query, 'location');

        return $query->get();
    }

    /**
     * Get serials for an inventoryable model.
     *
     * @return Collection<int, InventorySerial>
     */
    public function getForModel(Model $model): Collection
    {
        $query = InventorySerial::query()
            ->where('inventoryable_type', $model->getMorphClass())
            ->where('inventoryable_id', $model->getKey())
            ->orderBy('created_at');

        InventoryOwnerScope::applyToQueryByLocationRelation($query, 'location');

        return $query->get();
    }

    /**
     * Get serials at a location.
     *
     * @return Collection<int, InventorySerial>
     */
    public function getAtLocation(string $locationId): Collection
    {
        $query = InventorySerial::query()
            ->atLocation($locationId)
            ->orderBy('serial_number');

        InventoryOwnerScope::applyToQueryByLocationRelation($query, 'location');

        return $query->get();
    }

    /**
     * Get serials by batch.
     *
     * @return Collection<int, InventorySerial>
     */
    public function getByBatch(string $batchId): Collection
    {
        $query = InventorySerial::query()
            ->where('batch_id', $batchId)
            ->orderBy('serial_number');

        InventoryOwnerScope::applyToQueryByLocationRelation($query, 'location');

        return $query->get();
    }

    /**
     * Get serials by status.
     *
     * @return Collection<int, InventorySerial>
     */
    public function getByStatus(SerialStatus | string $status): Collection
    {
        $query = InventorySerial::query()
            ->where('status', SerialStatus::normalize($status))
            ->orderBy('created_at', 'desc');

        InventoryOwnerScope::applyToQueryByLocationRelation($query, 'location');

        return $query->get();
    }

    /**
     * Get serials by condition.
     *
     * @return Collection<int, InventorySerial>
     */
    public function getByCondition(SerialCondition $condition): Collection
    {
        $query = InventorySerial::query()
            ->where('condition', $condition->value)
            ->orderBy('created_at', 'desc');

        InventoryOwnerScope::applyToQueryByLocationRelation($query, 'location');

        return $query->get();
    }

    /**
     * Get available serials for sale.
     *
     * @return Collection<int, InventorySerial>
     */
    public function getAvailableForSale(Model $model, ?string $locationId = null): Collection
    {
        $query = InventorySerial::query()
            ->where('inventoryable_type', $model->getMorphClass())
            ->where('inventoryable_id', $model->getKey())
            ->sellable();

        if ($locationId !== null) {
            $query->atLocation($locationId);
        }

        InventoryOwnerScope::applyToQueryByLocationRelation($query, 'location');

        return $query->get();
    }

    /**
     * Get serials with expiring warranty.
     *
     * @return Collection<int, InventorySerial>
     */
    public function getExpiringWarranty(int $daysAhead = 30): Collection
    {
        $query = InventorySerial::query()
            ->whereNotNull('warranty_expires_at')
            ->where('warranty_expires_at', '>', now())
            ->where('warranty_expires_at', '<=', now()->addDays($daysAhead))
            ->orderBy('warranty_expires_at');

        InventoryOwnerScope::applyToQueryByLocationRelation($query, 'location');

        return $query->get();
    }

    /**
     * Get serials under warranty for a customer.
     *
     * @return Collection<int, InventorySerial>
     */
    public function getCustomerWarrantyItems(string $customerId): Collection
    {
        $query = InventorySerial::query()
            ->where('customer_id', $customerId)
            ->where('status', SerialStatus::normalize(Sold::class))
            ->whereNotNull('warranty_expires_at')
            ->where('warranty_expires_at', '>', now())
            ->orderBy('warranty_expires_at');

        InventoryOwnerScope::applyToQueryByLocationRelation($query, 'location');

        return $query->get();
    }

    /**
     * Advanced search with multiple criteria.
     *
     * @param  array<string, mixed>  $criteria
     * @return LengthAwarePaginator<InventorySerial>
     */
    public function search(array $criteria, int $perPage = 25): LengthAwarePaginator
    {
        $query = InventorySerial::query();

        $this->applyCriteria($query, $criteria);

        InventoryOwnerScope::applyToQueryByLocationRelation($query, 'location');

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Count serials by status for a model.
     *
     * @return array<string, int>
     */
    public function countByStatus(Model $model): array
    {
        $countsQuery = InventorySerial::query()
            ->where('inventoryable_type', $model->getMorphClass())
            ->where('inventoryable_id', $model->getKey())
            ->selectRaw('status, count(*) as count')
            ->groupBy('status');

        InventoryOwnerScope::applyToQueryByLocationRelation($countsQuery, 'location');

        $counts = $countsQuery->pluck('count', 'status')->toArray();

        $result = [];
        foreach (SerialStatus::classes() as $statusClass) {
            $value = $statusClass::getMorphClass();
            $result[$value] = $counts[$value] ?? 0;
        }

        return $result;
    }

    /**
     * Count serials by condition for a model.
     *
     * @return array<string, int>
     */
    public function countByCondition(Model $model): array
    {
        $countsQuery = InventorySerial::query()
            ->where('inventoryable_type', $model->getMorphClass())
            ->where('inventoryable_id', $model->getKey())
            ->selectRaw('`condition`, count(*) as count')
            ->groupBy('condition');

        InventoryOwnerScope::applyToQueryByLocationRelation($countsQuery, 'location');

        $counts = $countsQuery->pluck('count', 'condition')->toArray();

        $result = [];
        foreach (SerialCondition::cases() as $condition) {
            $result[$condition->value] = $counts[$condition->value] ?? 0;
        }

        return $result;
    }

    /**
     * Get total value of serials at a location.
     */
    public function getTotalValue(string $locationId): int
    {
        $query = InventorySerial::query()
            ->atLocation($locationId);

        InventoryOwnerScope::applyToQueryByLocationRelation($query, 'location');

        return (int) $query->sum('unit_cost_minor');
    }

    /**
     * Get total value of serials for a model.
     */
    public function getTotalValueForModel(Model $model, ?string $status = null): int
    {
        $query = InventorySerial::query()
            ->where('inventoryable_type', $model->getMorphClass())
            ->where('inventoryable_id', $model->getKey());

        if ($status !== null) {
            $query->where('status', $status);
        }

        InventoryOwnerScope::applyToQueryByLocationRelation($query, 'location');

        return (int) $query->sum('unit_cost_minor');
    }

    /**
     * Check if serial number exists.
     */
    public function serialNumberExists(string $serialNumber): bool
    {
        $query = InventorySerial::query()->where('serial_number', $serialNumber);
        InventoryOwnerScope::applyToQueryByLocationRelation($query, 'location');

        return $query->exists();
    }

    /**
     * Validate if serial numbers are available.
     *
     * @param  array<int, string>  $serialNumbers
     * @return array<string, bool>
     */
    public function validateSerialNumbers(array $serialNumbers): array
    {
        $existingQuery = InventorySerial::query()->whereIn('serial_number', $serialNumbers);
        InventoryOwnerScope::applyToQueryByLocationRelation($existingQuery, 'location');

        $existing = $existingQuery
            ->pluck('serial_number')
            ->toArray();

        $result = [];
        foreach ($serialNumbers as $serialNumber) {
            $result[$serialNumber] = ! in_array($serialNumber, $existing, true);
        }

        return $result;
    }

    /**
     * Apply search criteria to query.
     *
     * @param  Builder<InventorySerial>  $query
     * @param  array<string, mixed>  $criteria
     */
    private function applyCriteria(Builder $query, array $criteria): void
    {
        if (isset($criteria['serial_number'])) {
            $query->where('serial_number', 'like', "%{$criteria['serial_number']}%");
        }

        if (isset($criteria['status'])) {
            if (is_array($criteria['status'])) {
                $statuses = array_map(fn ($s) => SerialStatus::normalize($s), $criteria['status']);
                $query->whereIn('status', $statuses);
            } else {
                $status = SerialStatus::normalize($criteria['status']);
                $query->where('status', $status);
            }
        }

        if (isset($criteria['condition'])) {
            if (is_array($criteria['condition'])) {
                $conditions = array_map(fn ($c) => $c instanceof SerialCondition ? $c->value : $c, $criteria['condition']);
                $query->whereIn('condition', $conditions);
            } else {
                $condition = $criteria['condition'] instanceof SerialCondition ? $criteria['condition']->value : $criteria['condition'];
                $query->where('condition', $condition);
            }
        }

        if (isset($criteria['location_id'])) {
            $query->where('location_id', $criteria['location_id']);
        }

        if (isset($criteria['batch_id'])) {
            $query->where('batch_id', $criteria['batch_id']);
        }

        if (isset($criteria['order_id'])) {
            $query->where('order_id', $criteria['order_id']);
        }

        if (isset($criteria['customer_id'])) {
            $query->where('customer_id', $criteria['customer_id']);
        }

        if (isset($criteria['inventoryable_type']) && isset($criteria['inventoryable_id'])) {
            $query->where('inventoryable_type', $criteria['inventoryable_type'])
                ->where('inventoryable_id', $criteria['inventoryable_id']);
        }

        if (isset($criteria['received_from'])) {
            $query->where('received_at', '>=', $criteria['received_from']);
        }

        if (isset($criteria['received_to'])) {
            $query->where('received_at', '<=', $criteria['received_to']);
        }

        if (isset($criteria['sold_from'])) {
            $query->where('sold_at', '>=', $criteria['sold_from']);
        }

        if (isset($criteria['sold_to'])) {
            $query->where('sold_at', '<=', $criteria['sold_to']);
        }

        if (isset($criteria['warranty_expires_before'])) {
            $query->where('warranty_expires_at', '<=', $criteria['warranty_expires_before']);
        }

        if (isset($criteria['warranty_expires_after'])) {
            $query->where('warranty_expires_at', '>=', $criteria['warranty_expires_after']);
        }

        if (isset($criteria['has_warranty']) && $criteria['has_warranty']) {
            $query->whereNotNull('warranty_expires_at');
        }

        if (isset($criteria['under_warranty']) && $criteria['under_warranty']) {
            $query->whereNotNull('warranty_expires_at')
                ->where('warranty_expires_at', '>', now());
        }

        if (isset($criteria['min_cost'])) {
            $query->where('unit_cost_minor', '>=', $criteria['min_cost']);
        }

        if (isset($criteria['max_cost'])) {
            $query->where('unit_cost_minor', '<=', $criteria['max_cost']);
        }
    }
}
