<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Commerce\Tests\Inventory\InventoryTestCase;
use AIArmada\Inventory\Enums\AlertStatus;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Services\AlertDispatchService;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

class AlertDispatchServiceTest extends InventoryTestCase
{
    protected AlertDispatchService $service;

    protected InventoryItem $item;

    protected InventoryLocation $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new AlertDispatchService;
        $this->item = InventoryItem::create(['name' => 'Test Item']);
        $this->location = InventoryLocation::factory()->create();
    }

    public function test_can_register_notification(): void
    {
        $result = $this->service->registerNotification(AlertStatus::LowStock, 'App\Notifications\LowStockNotification');

        expect($result)->toBeInstanceOf(AlertDispatchService::class);
    }

    public function test_can_register_notifiable(): void
    {
        $notifiable = new AnonymousNotifiable;
        $result = $this->service->registerNotifiable('admin', $notifiable);

        expect($result)->toBeInstanceOf(AlertDispatchService::class);
    }

    public function test_dispatch_alert_does_nothing_when_notifications_disabled(): void
    {
        config(['inventory.events.low_inventory' => false]);

        Notification::fake();

        $level = InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'alert_status' => AlertStatus::LowStock->value,
        ]);

        $this->service->dispatchAlert($level, AlertStatus::LowStock);

        Notification::assertNothingSent();
    }

    public function test_dispatch_alert_does_nothing_when_no_notification_class(): void
    {
        config(['inventory.events.low_inventory' => true]);

        Notification::fake();

        $notifiable = new AnonymousNotifiable;
        $this->service->registerNotifiable('admin', $notifiable);

        $level = InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'alert_status' => AlertStatus::LowStock->value,
        ]);

        // No notification class registered
        $this->service->dispatchAlert($level, AlertStatus::LowStock);

        Notification::assertNothingSent();
    }

    public function test_dispatch_bulk_alerts(): void
    {
        config(['inventory.events.low_inventory' => true]);

        $level1 = InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'alert_status' => AlertStatus::LowStock->value,
        ]);

        $location2 = InventoryLocation::factory()->create();
        $level2 = InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $location2->id,
            'alert_status' => AlertStatus::OutOfStock->value,
        ]);

        $location3 = InventoryLocation::factory()->create();
        $level3 = InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $location3->id,
            'alert_status' => AlertStatus::None->value,
        ]);

        $count = $this->service->dispatchBulkAlerts([$level1, $level2, $level3]);

        expect($count)->toBe(2);
    }

    public function test_get_alert_summary(): void
    {
        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'alert_status' => AlertStatus::LowStock->value,
        ]);

        $location2 = InventoryLocation::factory()->create();
        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $location2->id,
            'alert_status' => AlertStatus::LowStock->value,
        ]);

        $location3 = InventoryLocation::factory()->create();
        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $location3->id,
            'alert_status' => AlertStatus::OutOfStock->value,
        ]);

        $summary = $this->service->getAlertSummary();

        expect($summary)->toHaveKey('low_stock');
        expect($summary['low_stock'])->toBe(2);
        expect($summary)->toHaveKey('out_of_stock');
        expect($summary['out_of_stock'])->toBe(1);
    }

    public function test_get_critical_alerts(): void
    {
        // Create critical alert
        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'alert_status' => AlertStatus::OutOfStock->value,
            'last_alert_at' => now(),
        ]);

        // Create non-critical alert
        $location2 = InventoryLocation::factory()->create();
        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $location2->id,
            'alert_status' => AlertStatus::None->value,
        ]);

        $criticals = $this->service->getCriticalAlerts();

        expect($criticals)->toHaveCount(1);
    }

    public function test_acknowledge_alert(): void
    {
        $level = InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'alert_status' => AlertStatus::LowStock->value,
        ]);

        $this->service->acknowledgeAlert($level, 'Acknowledged by admin');

        $level->refresh();
        expect($level->metadata['acknowledged_note'])->toBe('Acknowledged by admin');
        expect($level->metadata)->toHaveKey('last_acknowledged_at');
    }

    public function test_clear_alert(): void
    {
        $level = InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'alert_status' => AlertStatus::LowStock->value,
            'last_alert_at' => now(),
        ]);

        $this->service->clearAlert($level);

        $level->refresh();
        expect($level->alert_status)->toBe(AlertStatus::None->value);
        expect($level->last_alert_at)->toBeNull();
    }
}
