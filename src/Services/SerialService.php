<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Services;

use AIArmada\Inventory\Enums\SerialCondition;
use AIArmada\Inventory\Enums\SerialEventType;
use AIArmada\Inventory\Models\InventoryBatch;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventorySerial;
use AIArmada\Inventory\Models\InventorySerialHistory;
use AIArmada\Inventory\States\Available;
use AIArmada\Inventory\States\Disposed;
use AIArmada\Inventory\States\InRepair;
use AIArmada\Inventory\States\Reserved;
use AIArmada\Inventory\States\Returned;
use AIArmada\Inventory\States\SerialStatus;
use AIArmada\Inventory\States\Shipped;
use AIArmada\Inventory\States\Sold;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Manages serial number tracking for high-value or regulated items.
 *
 * Serial tracking provides individual unit traceability through the
 * full lifecycle: registration → sale → service → warranty → disposal.
 *
 * @example Register a serial with warranty
 * ```php
 * $service = app(SerialService::class);
 * $serial = $service->register(
 *     model: $product,
 *     serialNumber: 'SN-2024-ABC123',
 *     locationId: $warehouse->id,
 *     condition: SerialCondition::New,
 *     warrantyExpiresAt: now()->addYear(),
 * );
 * ```
 * @example Sell a serial (assign to customer)
 * ```php
 * $service->sell($serial, orderId: $order->id, userId: $customer->id);
 * // Status: Available → Sold
 * ```
 * @example Return/RMA handling
 * ```php
 * $service->customerReturn($serial, returnReason: 'defective');
 * // Serial goes to InTransit, then:
 * $service->receive($serial, warehouseId: $returns->id, condition: SerialCondition::Defective);
 * ```
 * @example Full history audit
 * ```php
 * $history = $serial->history()->chronological()->get();
 * foreach ($history as $event) {
 *     echo "{$event->event_type}: {$event->notes}";
 * }
 * ```
 */
final class SerialService
{
    /**
     * Register a new serial number.
     *
     * @param  Model  $model  The inventoryable model (e.g., Product)
     * @param  string  $serialNumber  Unique serial number
     * @param  string|null  $locationId  Initial storage location
     * @param  string|null  $batchId  Associated batch (if applicable)
     * @param  SerialCondition  $condition  Physical condition
     * @param  int|null  $unitCostMinor  Cost in minor units (cents)
     * @param  Carbon|null  $warrantyExpiresAt  Warranty expiration date
     * @param  string|null  $userId  User performing registration
     * @return InventorySerial The registered serial record
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
                'status' => SerialStatus::normalize(Available::class),
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
            ->orderBy('created_at');

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

        if (! $serial->canTransitionTo(Reserved::class)) {
            throw new InvalidArgumentException('Serial cannot be reserved from current status');
        }

        $previousStatus = $serial->status->getValue();
        $serial->transitionTo(Reserved::class);

        $this->logEvent($serial, SerialEventType::Reserved, [
            'previous_status' => $previousStatus,
            'new_status' => SerialStatus::normalize(Reserved::class),
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

        if (! $serial->canTransitionTo(Available::class)) {
            throw new InvalidArgumentException('Serial cannot be released from current status');
        }

        $previousStatus = $serial->status->getValue();
        $serial->transitionTo(Available::class);

        $this->logEvent($serial, SerialEventType::Released, [
            'previous_status' => $previousStatus,
            'new_status' => SerialStatus::normalize(Available::class),
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

        if (! $serial->canTransitionTo(Sold::class)) {
            throw new InvalidArgumentException('Serial cannot be sold from current status');
        }

        $previousStatus = $serial->status->getValue();

        $serial->transitionTo(Sold::class);

        $serial->update([
            'order_id' => $orderId,
            'customer_id' => $customerId,
            'sold_at' => now(),
        ]);

        $this->logEvent($serial, SerialEventType::Sold, [
            'previous_status' => $previousStatus,
            'new_status' => SerialStatus::normalize(Sold::class),
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

        if (! $serial->canTransitionTo(Shipped::class)) {
            throw new InvalidArgumentException('Serial cannot be shipped from current status');
        }

        $previousStatus = $serial->status->getValue();
        $previousLocation = $serial->location_id;

        $serial->transitionTo(Shipped::class);

        $serial->update([
            'location_id' => null,
        ]);

        $this->logEvent($serial, SerialEventType::Shipped, [
            'previous_status' => $previousStatus,
            'new_status' => SerialStatus::normalize(Shipped::class),
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

        if (! $serial->canTransitionTo(Returned::class)) {
            throw new InvalidArgumentException('Serial cannot be returned from current status');
        }

        $previousStatus = $serial->status->getValue();
        $previousCondition = $serial->condition;

        $serial->transitionTo(Returned::class);

        $serial->update([
            'location_id' => $locationId,
            'condition' => $condition->value,
        ]);

        $this->logEvent($serial, SerialEventType::Returned, [
            'previous_status' => $previousStatus,
            'new_status' => SerialStatus::normalize(Returned::class),
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

        if (! $serial->canTransitionTo(InRepair::class)) {
            throw new InvalidArgumentException('Serial cannot be put in repair from current status');
        }

        $previousStatus = $serial->status->getValue();
        $serial->transitionTo(InRepair::class);

        $this->logEvent($serial, SerialEventType::RepairStarted, [
            'previous_status' => $previousStatus,
            'new_status' => SerialStatus::normalize(InRepair::class),
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
            'status' => SerialStatus::normalize(Available::class),
            'condition' => $newCondition->value,
        ]);

        $this->logEvent($serial, SerialEventType::RepairCompleted, [
            'previous_status' => SerialStatus::normalize(InRepair::class),
            'new_status' => SerialStatus::normalize(Available::class),
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

        $previousStatus = $serial->status->getValue();
        $previousLocation = $serial->location_id;

        $serial->update([
            'status' => SerialStatus::normalize(Disposed::class),
            'location_id' => null,
        ]);

        $this->logEvent($serial, SerialEventType::Disposed, [
            'previous_status' => $previousStatus,
            'new_status' => SerialStatus::normalize(Disposed::class),
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
