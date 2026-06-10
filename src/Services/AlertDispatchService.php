<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Services;

use AIArmada\Inventory\Enums\AlertStatus;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

final class AlertDispatchService
{
    /**
     * @var array<string, class-string>
     */
    private array $notificationClasses = [];

    /**
     * @var array<string, object>
     */
    private array $notifiables = [];

    /**
     * Register a notification class for a specific alert status.
     *
     * @param  class-string  $notificationClass
     */
    public function registerNotification(AlertStatus $status, string $notificationClass): self
    {
        $this->notificationClasses[$status->value] = $notificationClass;

        return $this;
    }

    /**
     * Register notifiables (users/channels) for alerts.
     */
    public function registerNotifiable(string $key, object $notifiable): self
    {
        $this->notifiables[$key] = $notifiable;

        return $this;
    }

    /**
     * Dispatch alert for an inventory level.
     */
    public function dispatchAlert(InventoryLevel $level, AlertStatus $status): void
    {
        if (! config('inventory.events.low_inventory', true)) {
            return;
        }

        $notificationClass = $this->notificationClasses[$status->value] ?? null;

        if ($notificationClass === null || empty($this->notifiables)) {
            return;
        }

        $notification = new $notificationClass($level, $status);

        foreach ($this->notifiables as $notifiable) {
            Notification::send($notifiable, $notification);
        }
    }

    /**
     * Dispatch alerts for multiple levels.
     *
     * @param  iterable<InventoryLevel>  $levels
     */
    public function dispatchBulkAlerts(iterable $levels): int
    {
        $count = 0;

        foreach ($levels as $level) {
            $status = $level->alert_status !== null
                ? AlertStatus::from($level->alert_status)
                : AlertStatus::None;

            if ($status !== AlertStatus::None) {
                $this->dispatchAlert($level, $status);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get summary of alerts by status.
     *
     * @return array<string, int>
     */
    public function getAlertSummary(): array
    {
        $summary = [];

        foreach (AlertStatus::cases() as $status) {
            $query = InventoryLevel::query()->where('alert_status', $status->value);

            if (InventoryOwnerScope::isEnabled()) {
                InventoryOwnerScope::applyToQueryByLocationRelation($query, 'location');
            }

            $count = $query->count();

            if ($count > 0) {
                $summary[$status->value] = $count;
            }
        }

        return $summary;
    }

    /**
     * Get all critical alerts.
     *
     * @return Collection<int, InventoryLevel>
     */
    public function getCriticalAlerts(): Collection
    {
        $query = InventoryLevel::query()
            ->whereIn('alert_status', array_map(
                fn (AlertStatus $s): string => $s->value,
                AlertStatus::criticalStatuses()
            ))
            ->with(['location', 'inventoryable'])
            ->orderByDesc('last_alert_at');

        if (InventoryOwnerScope::isEnabled()) {
            InventoryOwnerScope::applyToQueryByLocationRelation($query, 'location');
        }

        return $query->get();
    }

    /**
     * Acknowledge an alert (mark as reviewed without clearing).
     */
    public function acknowledgeAlert(InventoryLevel $level, ?string $note = null): void
    {
        $metadata = $level->metadata ?? [];
        $metadata['last_acknowledged_at'] = now()->toIso8601String();
        $metadata['acknowledged_note'] = $note;

        $level->update(['metadata' => $metadata]);
    }

    /**
     * Clear all alerts for a level.
     */
    public function clearAlert(InventoryLevel $level): void
    {
        $level->update([
            'alert_status' => AlertStatus::None->value,
            'last_alert_at' => null,
        ]);
    }
}
