<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\FilamentShipping\Widgets\CarrierPerformanceWidget;
use AIArmada\FilamentShipping\Widgets\PendingActionsWidget;
use AIArmada\FilamentShipping\Widgets\PendingShipmentsWidget;
use AIArmada\FilamentShipping\Widgets\ShippingDashboardWidget;
use AIArmada\Shipping\Enums\ShipmentStatus;
use AIArmada\Shipping\Models\Shipment;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

uses(TestCase::class);

// ============================================
// Filament Shipping Widgets Tests
// ============================================

describe('ShippingDashboardWidget', function (): void {
    it('can be instantiated', function (): void {
        $widget = new ShippingDashboardWidget;

        expect($widget)->toBeInstanceOf(ShippingDashboardWidget::class);
    });

    it('has polling enabled', function (): void {
        $widget = new ShippingDashboardWidget;

        $reflection = new ReflectionProperty($widget, 'pollingInterval');
        $reflection->setAccessible(true);

        expect($reflection->getValue($widget))->toBe('30s');
    });
});

describe('PendingShipmentsWidget', function (): void {
    it('can be instantiated', function (): void {
        $widget = new PendingShipmentsWidget;

        expect($widget)->toBeInstanceOf(PendingShipmentsWidget::class);
    });

    it('spans full width', function (): void {
        $widget = new PendingShipmentsWidget;

        $reflection = new ReflectionProperty($widget, 'columnSpan');
        $reflection->setAccessible(true);

        expect($reflection->getValue($widget))->toBe('full');
    });
});

describe('CarrierPerformanceWidget', function (): void {
    it('can be instantiated', function (): void {
        $widget = new CarrierPerformanceWidget;

        expect($widget)->toBeInstanceOf(CarrierPerformanceWidget::class);
    });

    it('has longer polling interval', function (): void {
        $widget = new CarrierPerformanceWidget;

        $reflection = new ReflectionProperty($widget, 'pollingInterval');
        $reflection->setAccessible(true);

        expect($reflection->getValue($widget))->toBe('60s');
    });

    it('spans full width', function (): void {
        $widget = new CarrierPerformanceWidget;

        $reflection = new ReflectionProperty($widget, 'columnSpan');
        $reflection->setAccessible(true);

        expect($reflection->getValue($widget))->toBe('full');
    });
});

describe('PendingActionsWidget', function (): void {
    it('can be instantiated', function (): void {
        $widget = new PendingActionsWidget;

        expect($widget)->toBeInstanceOf(PendingActionsWidget::class);
    });

    it('has polling enabled', function (): void {
        $widget = new PendingActionsWidget;

        $reflection = new ReflectionProperty($widget, 'pollingInterval');
        $reflection->setAccessible(true);

        expect($reflection->getValue($widget))->toBe('30s');
    });

    it('scopes pending shipment count to current owner plus global', function (): void {
        Schema::dropIfExists('test_owners');
        Schema::create('test_owners', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->timestamps();
        });

        $ownerA = WidgetTestOwner::query()->create(['name' => 'Owner A']);
        $ownerB = WidgetTestOwner::query()->create(['name' => 'Owner B']);

        Shipment::query()->create([
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => $ownerA->getKey(),
            'reference' => 'W-REF-A',
            'carrier_code' => 'test',
            'status' => ShipmentStatus::Pending,
            'origin_address' => ['country' => 'MY', 'city' => 'Kuala Lumpur'],
            'destination_address' => ['country' => 'MY', 'city' => 'Kuala Lumpur'],
        ]);

        Shipment::query()->create([
            'owner_type' => $ownerB->getMorphClass(),
            'owner_id' => $ownerB->getKey(),
            'reference' => 'W-REF-B',
            'carrier_code' => 'test',
            'status' => ShipmentStatus::Pending,
            'origin_address' => ['country' => 'MY', 'city' => 'Kuala Lumpur'],
            'destination_address' => ['country' => 'MY', 'city' => 'Kuala Lumpur'],
        ]);

        Shipment::query()->create([
            'owner_type' => null,
            'owner_id' => null,
            'reference' => 'W-REF-G',
            'carrier_code' => 'test',
            'status' => ShipmentStatus::Pending,
            'origin_address' => ['country' => 'MY', 'city' => 'Kuala Lumpur'],
            'destination_address' => ['country' => 'MY', 'city' => 'Kuala Lumpur'],
        ]);

        app()->instance(OwnerResolverInterface::class, new class($ownerA) implements OwnerResolverInterface
        {
            public function __construct(
                private readonly ?Model $owner,
            ) {}

            public function resolve(): ?Model
            {
                return $this->owner;
            }
        });

        $widget = new PendingActionsWidget;

        $method = new ReflectionMethod($widget, 'getPendingShipmentsCount');
        $method->setAccessible(true);

        expect($method->invoke($widget))->toBe(2);
    });
});

class WidgetTestOwner extends Model
{
    use HasUuids;

    protected $table = 'test_owners';

    protected $fillable = ['name'];
}
