<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Support\Fixtures\TestOwner;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\FilamentShipping\Resources\ShipmentResource;
use AIArmada\Shipping\Enums\ShipmentStatus;
use AIArmada\Shipping\Models\Shipment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

uses(TestCase::class);

beforeEach(function (): void {
    Schema::dropIfExists('test_owners');

    Schema::create('test_owners', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });
});

it('scopes ShipmentResource to current owner plus global', function (): void {
    config()->set('shipping.features.owner.enabled', true);
    config()->set('shipping.features.owner.include_global', true);

    $ownerA = TestOwner::query()->create(['name' => 'Owner A']);
    $ownerB = TestOwner::query()->create(['name' => 'Owner B']);

    $shipmentA = Shipment::query()->create([
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
        'reference' => 'REF-A',
        'carrier_code' => 'test',
        'status' => ShipmentStatus::Pending,
        'origin_address' => ['country' => 'MY', 'city' => 'Kuala Lumpur'],
        'destination_address' => ['country' => 'MY', 'city' => 'Kuala Lumpur'],
    ]);

    $shipmentB = Shipment::query()->create([
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
        'reference' => 'REF-B',
        'carrier_code' => 'test',
        'status' => ShipmentStatus::Pending,
        'origin_address' => ['country' => 'MY', 'city' => 'Kuala Lumpur'],
        'destination_address' => ['country' => 'MY', 'city' => 'Kuala Lumpur'],
    ]);

    $shipmentGlobal = Shipment::query()->create([
        'owner_type' => null,
        'owner_id' => null,
        'reference' => 'REF-G',
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

    $ids = ShipmentResource::getEloquentQuery()->pluck('id')->all();

    expect($ids)
        ->toContain($shipmentA->id)
        ->toContain($shipmentGlobal->id)
        ->not->toContain($shipmentB->id);
});

it('returns empty when owner scoping is enabled but owner context is missing', function (): void {
    config()->set('shipping.features.owner.enabled', true);
    config()->set('shipping.features.owner.include_global', true);

    $ownerA = TestOwner::query()->create(['name' => 'Owner A']);

    $shipmentA = Shipment::query()->create([
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
        'reference' => 'REF-A',
        'carrier_code' => 'test',
        'status' => ShipmentStatus::Pending,
        'origin_address' => ['country' => 'MY', 'city' => 'Kuala Lumpur'],
        'destination_address' => ['country' => 'MY', 'city' => 'Kuala Lumpur'],
    ]);

    $shipmentGlobal = Shipment::query()->create([
        'owner_type' => null,
        'owner_id' => null,
        'reference' => 'REF-G',
        'carrier_code' => 'test',
        'status' => ShipmentStatus::Pending,
        'origin_address' => ['country' => 'MY', 'city' => 'Kuala Lumpur'],
        'destination_address' => ['country' => 'MY', 'city' => 'Kuala Lumpur'],
    ]);

    app()->instance(OwnerResolverInterface::class, new class implements OwnerResolverInterface
    {
        public function resolve(): ?Model
        {
            return null;
        }
    });

    $ids = ShipmentResource::getEloquentQuery()->pluck('id')->all();

    expect($ids)
        ->not->toContain($shipmentA->id)
        ->not->toContain($shipmentGlobal->id)
        ->toBe([]);
});
