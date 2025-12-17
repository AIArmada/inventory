<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Services;

use AIArmada\Inventory\Enums\SerialCondition;
use AIArmada\Inventory\Enums\SerialEventType;
use AIArmada\Inventory\Enums\SerialStatus;
use AIArmada\Inventory\Models\InventoryBatch;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventorySerial;
use AIArmada\Inventory\Models\InventorySerialHistory;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class SerialService
{
    /**
     * Register a new serial number.
     */
    public function register(
        Model $model,
        string $serialNumber,
        ?string $locationId = null,
        ?string $batchId = null,
        SerialCondition $condition = SerialCondition::New,
        ?int $unitCostMinor = null,
        ?Carbon $warrantyExpiresAt = null,
        ?string $userId = null
    ): InventorySerial {
        return DB::transaction(function () use (
            $model,
            $serialNumber,
            $locationId,
            $batchId,
            $condition,
            $unitCostMinor,
            $warrantyExpiresAt,
            $userId
        ): InventorySerial {
            $this->assertLocationIdAllowedForCurrentOwner($locationId);
            $this->assertBatchIdAllowedForCurrentOwner($batchId);

            $serial = InventorySerial::create([
                'inventoryable_type' => $model->getMorphClass(),
                'inventoryable_id' => $model->getKey(),
                'serial_number' => $serialNumber,
                'location_id' => $locationId,
                'batch_id' => $batchId,
                'status' => SerialStatus::Available->value,
                'condition' => $condition->value,
                'unit_cost_minor' => $unitCostMinor,
                'warranty_expires_at' => $warrantyExpiresAt,
                'received_at' => now(),
            ]);

            $this->logEvent($serial, SerialEventType::Registered, [
                'user_id' => $userId,
                'to_location_id' => $locationId,
            ]);

            return $serial;
        });
    }

    /**
     * Find serial by serial number.
     */
    public function findBySerialNumber(string $serialNumber): ?InventorySerial
    {
        $serial = InventorySerial::where('serial_number', $serialNumber)->first();

        if ($serial === null) {
            return null;
        }

        if (! $this->isSerialAllowedForCurrentOwner($serial)) {
            return null;
        }

        return $serial;
    }

    /**
     * Get serials for a model.
     *
     * @return Collection<int, InventorySerial>
     */
    public function getSerialsForModel(Model $model): Collection
    {
        $query = InventorySerial::query()
            ->where('inventoryable_type', $model->getMorphClass())
            ->where('inventoryable_id', $model->getKey())
            ->orderBy('created_at')
            ;

        $query = InventoryOwnerScope::applyToQueryByLocationRelation($query, 'location');

        return $query->get();
    }

    /**
     * Get available serials for a model.
     *
     * @return Collection<int, InventorySerial>
     */
    public function getAvailableSerials(Model $model, ?string $locationId = null): Collection
    {
        $this->assertLocationIdAllowedForCurrentOwner($locationId);

        $query = InventorySerial::query()
            ->where('inventoryable_type', $model->getMorphClass())
            ->where('inventoryable_id', $model->getKey())
            ->sellable();

        $query = InventoryOwnerScope::applyToQueryByLocationRelation($query, 'location');

        if ($locationId !== null) {
            $query->atLocation($locationId);
        }

        return $query->get();
    }

    /**
     * Transfer serial to a new location.
     */
    public function transfer(
        InventorySerial $serial,
        string $newLocationId,
        ?string $userId = null,
        ?string $notes = null
    ): InventorySerial {
        $this->assertSerialAllowedForCurrentOwner($serial);
        $this->assertLocationIdAllowedForCurrentOwner($newLocationId);

        $fromLocationId = $serial->location_id;

        $serial->update(['location_id' => $newLocationId]);

        $this->logEvent($serial, SerialEventType::Transferred, [
            'from_location_id' => $fromLocationId,
            'to_location_id' => $newLocationId,
            'user_id' => $userId,
            'notes' => $notes,
        ]);

        return $serial->refresh();
    }

    /**
     * Reserve a serial for an order.
     */
    public function reserve(
        InventorySerial $serial,
        ?string $orderId = null,
        ?string $userId = null
    ): InventorySerial {
        $this->assertSerialAllowedForCurrentOwner($serial);

        if (! $serial->canTransitionTo(SerialStatus::Reserved)) {
            throw new InvalidArgumentException('Serial cannot be reserved from current status');
        }

        $previousStatus = $serial->status;
        $serial->transitionTo(SerialStatus::Reserved);

        $this->logEvent($serial, SerialEventType::Reserved, [
            'previous_status' => $previousStatus,
            'new_status' => SerialStatus::Reserved->value,
            'reference' => $orderId,
            'user_id' => $userId,
        ]);

        return $serial;
    }

    /**
     * Release a reserved serial.
     */
    public function release(InventorySerial $serial, ?string $userId = null): InventorySerial
    {
        $this->assertSerialAllowedForCurrentOwner($serial);

        if (! $serial->canTransitionTo(SerialStatus::Available)) {
            throw new InvalidArgumentException('Serial cannot be released from current status');
        }

        $previousStatus = $serial->status;
        $serial->transitionTo(SerialStatus::Available);

        $this->logEvent($serial, SerialEventType::Released, [
            'previous_status' => $previousStatus,
            'new_status' => SerialStatus::Available->value,
            'user_id' => $userId,
        ]);

        return $serial;
    }

    /**
     * Mark serial as sold.
     */
    public function sell(
        InventorySerial $serial,
        string $orderId,
        ?string $customerId = null,
        ?string $userId = null
    ): InventorySerial {
        $this->assertSerialAllowedForCurrentOwner($serial);

        if (! $serial->canTransitionTo(SerialStatus::Sold)) {
            throw new InvalidArgumentException('Serial cannot be sold from current status');
        }

        $previousStatus = $serial->status;

        $serial->update([
            'status' => SerialStatus::Sold->value,
            'order_id' => $orderId,
            'customer_id' => $customerId,
            'sold_at' => now(),
        ]);

        $this->logEvent($serial, SerialEventType::Sold, [
            'previous_status' => $previousStatus,
            'new_status' => SerialStatus::Sold->value,
            'reference' => $orderId,
            'user_id' => $userId,
        ]);

        return $serial;
    }

    /**
     * Ship a sold serial.
     */
    public function ship(InventorySerial $serial, ?string $trackingNumber = null, ?string $userId = null): InventorySerial
    {
        $this->assertSerialAllowedForCurrentOwner($serial);

        if (! $serial->canTransitionTo(SerialStatus::Shipped)) {
            throw new InvalidArgumentException('Serial cannot be shipped from current status');
        }

        $previousStatus = $serial->status;
        $previousLocation = $serial->location_id;

        $serial->update([
            'status' => SerialStatus::Shipped->value,
            'location_id' => null,
        ]);

        $this->logEvent($serial, SerialEventType::Shipped, [
            'previous_status' => $previousStatus,
            'new_status' => SerialStatus::Shipped->value,
            'from_location_id' => $previousLocation,
            'reference' => $trackingNumber,
            'user_id' => $userId,
        ]);

        return $serial;
    }

    /**
     * Process a return.
     */
    public function processReturn(
        InventorySerial $serial,
        string $locationId,
        SerialCondition $condition,
        ?string $notes = null,
        ?string $userId = null
    ): InventorySerial {
        $this->assertSerialAllowedForCurrentOwner($serial);
        $this->assertLocationIdAllowedForCurrentOwner($locationId);

        if (! $serial->canTransitionTo(SerialStatus::Returned)) {
            throw new InvalidArgumentException('Serial cannot be returned from current status');
        }

        $previousStatus = $serial->status;
        $previousCondition = $serial->condition;

        $serial->update([
            'status' => SerialStatus::Returned->value,
            'location_id' => $locationId,
            'condition' => $condition->value,
        ]);

        $this->logEvent($serial, SerialEventType::Returned, [
            'previous_status' => $previousStatus,
            'new_status' => SerialStatus::Returned->value,
            'to_location_id' => $locationId,
            'user_id' => $userId,
            'notes' => $notes,
            'metadata' => [
                'previous_condition' => $previousCondition,
                'new_condition' => $condition->value,
            ],
        ]);

        return $serial;
    }

    /**
     * Start repair process.
     */
    public function startRepair(InventorySerial $serial, ?string $repairNotes = null, ?string $userId = null): InventorySerial
    {
        $this->assertSerialAllowedForCurrentOwner($serial);

        if (! $serial->canTransitionTo(SerialStatus::InRepair)) {
            throw new InvalidArgumentException('Serial cannot be put in repair from current status');
        }

        $previousStatus = $serial->status;
        $serial->transitionTo(SerialStatus::InRepair);

        $this->logEvent($serial, SerialEventType::RepairStarted, [
            'previous_status' => $previousStatus,
            'new_status' => SerialStatus::InRepair->value,
            'user_id' => $userId,
            'notes' => $repairNotes,
        ]);

        return $serial;
    }

    /**
     * Complete repair.
     */
    public function completeRepair(
        InventorySerial $serial,
        SerialCondition $newCondition,
        ?string $repairNotes = null,
        ?string $userId = null
    ): InventorySerial {
        $this->assertSerialAllowedForCurrentOwner($serial);

        $previousCondition = $serial->condition;

        $serial->update([
            'status' => SerialStatus::Available->value,
            'condition' => $newCondition->value,
        ]);

        $this->logEvent($serial, SerialEventType::RepairCompleted, [
            'previous_status' => SerialStatus::InRepair->value,
            'new_status' => SerialStatus::Available->value,
            'user_id' => $userId,
            'notes' => $repairNotes,
            'metadata' => [
                'previous_condition' => $previousCondition,
                'new_condition' => $newCondition->value,
            ],
        ]);

        return $serial;
    }

    /**
     * Dispose of a serial.
     */
    public function dispose(InventorySerial $serial, string $reason, ?string $userId = null): InventorySerial
    {
        $this->assertSerialAllowedForCurrentOwner($serial);

        $previousStatus = $serial->status;
        $previousLocation = $serial->location_id;

        $serial->update([
            'status' => SerialStatus::Disposed->value,
            'location_id' => null,
        ]);

        $this->logEvent($serial, SerialEventType::Disposed, [
            'previous_status' => $previousStatus,
            'new_status' => SerialStatus::Disposed->value,
            'from_location_id' => $previousLocation,
            'user_id' => $userId,
            'notes' => $reason,
        ]);

        return $serial;
    }

    /**
     * Update warranty expiration.
     */
    public function updateWarranty(
        InventorySerial $serial,
        Carbon $newExpiryDate,
        ?string $notes = null,
        ?string $userId = null
    ): InventorySerial {
        $this->assertSerialAllowedForCurrentOwner($serial);

        $previousExpiry = $serial->warranty_expires_at;

        $serial->update(['warranty_expires_at' => $newExpiryDate]);

        $this->logEvent($serial, SerialEventType::WarrantyUpdated, [
            'user_id' => $userId,
            'notes' => $notes,
            'metadata' => [
                'previous_warranty_expires_at' => $previousExpiry?->toDateString(),
                'new_warranty_expires_at' => $newExpiryDate->toDateString(),
            ],
        ]);

        return $serial;
    }

    /**
     * Get serial history.
     *
     * @return Collection<int, InventorySerialHistory>
     */
    public function getHistory(InventorySerial $serial, ?int $limit = null): Collection
    {
        $this->assertSerialAllowedForCurrentOwner($serial);

        $query = $serial->history();

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Log an event to serial history.
     *
     * @param  array<string, mixed>  $data
     */
    private function logEvent(InventorySerial $serial, SerialEventType $eventType, array $data = []): InventorySerialHistory
    {
        return InventorySerialHistory::create([
            'serial_id' => $serial->id,
            'event_type' => $eventType->value,
            'previous_status' => $data['previous_status'] ?? null,
            'new_status' => $data['new_status'] ?? null,
            'from_location_id' => $data['from_location_id'] ?? null,
            'to_location_id' => $data['to_location_id'] ?? null,
            'related_to_type' => $data['related_to_type'] ?? null,
            'related_to_id' => $data['related_to_id'] ?? null,
            'reference' => $data['reference'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'actor_name' => $data['actor_name'] ?? null,
            'notes' => $data['notes'] ?? null,
            'metadata' => $data['metadata'] ?? null,
            'occurred_at' => now(),
        ]);
    }

    private function assertSerialAllowedForCurrentOwner(InventorySerial $serial): void
    {
        if (! $this->isSerialAllowedForCurrentOwner($serial)) {
            throw new InvalidArgumentException('Serial is not accessible for current owner');
        }
    }

    private function isSerialAllowedForCurrentOwner(InventorySerial $serial): bool
    {
        if (! InventoryOwnerScope::isEnabled()) {
            return true;
        }

        $scopeLocationId = $this->resolveSerialScopeLocationId($serial);

        if ($scopeLocationId === null) {
            return false;
        }

        return InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())
            ->whereKey($scopeLocationId)
            ->exists();
    }

    private function resolveSerialScopeLocationId(InventorySerial $serial): ?string
    {
        if ($serial->location_id !== null) {
            return $serial->location_id;
        }

        /** @var InventorySerialHistory|null $history */
        $history = InventorySerialHistory::query()
            ->where('serial_id', $serial->id)
            ->where(function (Builder $query): void {
                $query->whereNotNull('to_location_id')
                    ->orWhereNotNull('from_location_id');
            })
            ->orderByDesc('occurred_at')
            ->first();

        return $history?->to_location_id ?? $history?->from_location_id;
    }

    private function assertLocationIdAllowedForCurrentOwner(?string $locationId): void
    {
        if (! InventoryOwnerScope::isEnabled()) {
            return;
        }

        if ($locationId === null && InventoryOwnerScope::resolveOwner() !== null) {
            throw new InvalidArgumentException('Location is required when owner scoping is enabled');
        }

        if ($locationId === null) {
            return;
        }

        $isAllowed = InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())
            ->whereKey($locationId)
            ->exists();

        if (! $isAllowed) {
            throw new InvalidArgumentException('Invalid location for current owner');
        }
    }

    private function assertBatchIdAllowedForCurrentOwner(?string $batchId): void
    {
        if (! InventoryOwnerScope::isEnabled()) {
            return;
        }

        if ($batchId === null) {
            return;
        }

        $isAllowed = InventoryOwnerScope::applyToQueryByLocationRelation(InventoryBatch::query(), 'location')
            ->whereKey($batchId)
            ->exists();

        if (! $isAllowed) {
            throw new InvalidArgumentException('Invalid batch for current owner');
        }
    }
}
