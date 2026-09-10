<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Reports;

use AIArmada\Inventory\Models\InventoryMovement;
use AIArmada\Inventory\Models\InventoryOperation;
use AIArmada\Inventory\Models\InventoryReservation;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use AIArmada\Orders\Models\Order;
use Illuminate\Support\Facades\Schema;

final class InventoryReconciliationReport
{
    /**
     * Return durable operation and reservation bookkeeping indicators.
     *
     * @return array{operations_total: int, operations_pending: int, operations_failed: int, completed_operations_without_movement: int, reservations_total: int, reservations_expired_pending_cleanup: int, reservations_without_allocations: int, terminal_reservations_with_allocations: int}
     */
    public function getSummary(): array
    {
        $operations = InventoryOwnerScope::applyToLocationQuery(InventoryOperation::query())->get();
        $completedWithoutMovement = $operations
            ->where('status', InventoryOperation::STATUS_COMPLETED)
            ->filter(function (InventoryOperation $operation): bool {
                $references = [(string) $operation->order_id];
                $order = class_exists(Order::class) && Schema::hasTable((new Order)->getTable())
                    ? Order::query()->find($operation->order_id)
                    : null;

                if ($order !== null) {
                    $references[] = (string) $order->order_number;
                }

                return ! InventoryOwnerScope::applyToLocationQuery(
                    InventoryMovement::query()->whereIn('reference', array_unique($references))
                )->exists();
            })
            ->count();

        $reservations = InventoryOwnerScope::applyToLocationQuery(InventoryReservation::query());

        return [
            'operations_total' => $operations->count(),
            'operations_pending' => $operations->where('status', InventoryOperation::STATUS_PENDING)->count(),
            'operations_failed' => $operations->where('status', InventoryOperation::STATUS_FAILED)->count(),
            'completed_operations_without_movement' => $completedWithoutMovement,
            'reservations_total' => (clone $reservations)->count(),
            'reservations_expired_pending_cleanup' => (clone $reservations)
                ->where('status', InventoryReservation::STATE_RESERVED)
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now())
                ->count(),
            'reservations_without_allocations' => (clone $reservations)
                ->where('status', InventoryReservation::STATE_RESERVED)
                ->whereDoesntHave('allocations')
                ->count(),
            'terminal_reservations_with_allocations' => (clone $reservations)
                ->whereIn('status', [
                    InventoryReservation::STATE_COMMITTED,
                    InventoryReservation::STATE_RELEASED,
                    InventoryReservation::STATE_EXPIRED,
                ])
                ->whereHas('allocations')
                ->count(),
        ];
    }
}
