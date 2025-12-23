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
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
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

    it('builds table definition', function (): void {
        $widget = new PendingShipmentsWidget;

        $table = $widget->table(Table::make($widget));

        expect($table->getColumns())->not()->toBeEmpty();
        expect($table->getRecordActions())->not()->toBeEmpty();
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

    it('builds chart data and options', function (): void {
        Schema::dropIfExists('test_owners');
        Schema::create('test_owners', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->timestamps();
        });

        $owner = WidgetTestOwner::query()->create(['name' => 'Owner A']);

        Shipment::query()->create([
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->getKey(),
            'reference' => 'C-REF-1',
            'carrier_code' => 'jnt',
            'status' => ShipmentStatus::Delivered,
            'created_at' => Carbon::now()->subDays(2),
            'origin_address' => ['country' => 'MY', 'city' => 'Kuala Lumpur'],
            'destination_address' => ['country' => 'MY', 'city' => 'Kuala Lumpur'],
        ]);

        Shipment::query()->create([
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->getKey(),
            'reference' => 'C-REF-2',
            'carrier_code' => 'jnt',
            'status' => ShipmentStatus::Exception,
            'created_at' => Carbon::now()->subDays(1),
            'origin_address' => ['country' => 'MY', 'city' => 'Kuala Lumpur'],
            'destination_address' => ['country' => 'MY', 'city' => 'Kuala Lumpur'],
        ]);

        app()->instance(OwnerResolverInterface::class, new class($owner) implements OwnerResolverInterface
        {
            public function __construct(
                private readonly ?Model $owner,
            ) {}

            public function resolve(): ?Model
            {
                return $this->owner;
            }
        });

        $widget = new CarrierPerformanceWidget;

        $getData = new ReflectionMethod($widget, 'getData');
        $getData->setAccessible(true);
        $getOptions = new ReflectionMethod($widget, 'getOptions');
        $getOptions->setAccessible(true);

        /** @var array $data */
        $data = $getData->invoke($widget);
        /** @var array $options */
        $options = $getOptions->invoke($widget);

        expect($data)->toHaveKeys(['datasets', 'labels']);
        expect($data['datasets'])->toHaveCount(3);
        expect($options)->toHaveKey('plugins');
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
            config()->set('shipping.features.owner.enabled', true);
            config()->set('shipping.features.owner.include_global', true);
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

    it('builds stats without requiring Filament panel routes', function (): void {
        if (! Route::has('filament.admin.resources.shipments.index')) {
            Route::get('/__tests__/filament/shipments', fn () => 'ok')
                ->name('filament.admin.resources.shipments.index');
        }

        if (! Route::has('filament.admin.resources.return-authorizations.index')) {
            Route::get('/__tests__/filament/return-authorizations', fn () => 'ok')
                ->name('filament.admin.resources.return-authorizations.index');
        }

        $widget = new PendingActionsWidget;

        $method = new ReflectionMethod($widget, 'getStats');
        $method->setAccessible(true);

        /** @var array $stats */
        $stats = $method->invoke($widget);

        expect($stats)->toHaveCount(4);
    });
});

describe('ShippingDashboardWidget', function (): void {
    it('builds stats overview', function (): void {
        $widget = new ShippingDashboardWidget;

        $method = new ReflectionMethod($widget, 'getStats');
        $method->setAccessible(true);

        /** @var array $stats */
        $stats = $method->invoke($widget);

        expect($stats)->toHaveCount(5);
    });
});

class WidgetTestOwner extends Model
{
    use HasUuids;

    protected $table = 'test_owners';

    protected $fillable = ['name'];
}
