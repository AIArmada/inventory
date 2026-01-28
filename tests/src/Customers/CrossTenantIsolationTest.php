<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Customers\Enums\CustomerStatus;
use AIArmada\Customers\Models\Address;
use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Models\Segment;
use AIArmada\Customers\Services\SegmentationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

require_once __DIR__ . '/Fixtures/CustomersTestOwner.php';

beforeEach(function (): void {
    Schema::dropIfExists('test_owners');

    Schema::create('test_owners', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });
});

it('does not match customers across tenant boundary', function (): void {
    $ownerA = CustomersTestOwner::query()->create(['name' => 'Owner A']);
    $ownerB = CustomersTestOwner::query()->create(['name' => 'Owner B']);

    $customerA = Customer::query()->create([
        'first_name' => 'A',
        'last_name' => 'Customer',
        'email' => 'a-' . uniqid() . '@example.com',
        'status' => CustomerStatus::Active,
        'accepts_marketing' => true,
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]);

    $customerB = Customer::query()->create([
        'first_name' => 'B',
        'last_name' => 'Customer',
        'email' => 'b-' . uniqid() . '@example.com',
        'status' => CustomerStatus::Active,
        'accepts_marketing' => true,
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
    ]);

    $segmentA = Segment::query()->create([
        'name' => 'Marketing Segment A',
        'slug' => 'marketing-segment-a-' . uniqid(),
        'is_active' => true,
        'is_automatic' => true,
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
        'conditions' => [
            ['field' => 'accepts_marketing', 'value' => true],
        ],
    ]);

    $matchedIds = $segmentA->getMatchingCustomers()->pluck('id')->all();

    expect($matchedIds)
        ->toContain($customerA->id)
        ->not->toContain($customerB->id);
});

it('prevents cross-tenant segment membership changes', function (): void {
    $ownerA = CustomersTestOwner::query()->create(['name' => 'Owner A']);
    $ownerB = CustomersTestOwner::query()->create(['name' => 'Owner B']);

    $customerB = Customer::query()->create([
        'first_name' => 'B',
        'last_name' => 'Customer',
        'email' => 'b2-' . uniqid() . '@example.com',
        'status' => CustomerStatus::Active,
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
    ]);

    $segmentA = Segment::query()->create([
        'name' => 'Segment A',
        'slug' => 'segment-a-' . uniqid(),
        'is_active' => true,
        'is_automatic' => false,
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]);

    $service = new SegmentationService;

    expect($service->addToSegment($customerB, $segmentA))->toBeFalse();

    $service->evaluateCustomer($customerB);

    expect($customerB->segments()->whereKey($segmentA->getKey())->exists())->toBeFalse();
});

it('enforces owner scoping for addresses', function (): void {
    $ownerA = CustomersTestOwner::query()->create(['name' => 'Owner A']);
    $ownerB = CustomersTestOwner::query()->create(['name' => 'Owner B']);

    $customerA = Customer::query()->create([
        'first_name' => 'A',
        'last_name' => 'Customer',
        'email' => 'addr-a-' . uniqid() . '@example.com',
        'status' => CustomerStatus::Active,
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]);

    $customerB = Customer::query()->create([
        'first_name' => 'B',
        'last_name' => 'Customer',
        'email' => 'addr-b-' . uniqid() . '@example.com',
        'status' => CustomerStatus::Active,
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
    ]);

    OwnerContext::withOwner($ownerA, function () use ($customerA): void {
        Address::query()->create([
            'customer_id' => $customerA->id,
            'line1' => '123 Owner A',
            'city' => 'KL',
            'postcode' => '50000',
            'country' => 'MY',
        ]);
    });

    OwnerContext::withOwner($ownerB, function () use ($customerB): void {
        Address::query()->create([
            'customer_id' => $customerB->id,
            'line1' => '456 Owner B',
            'city' => 'KL',
            'postcode' => '50000',
            'country' => 'MY',
        ]);
    });

    OwnerContext::withOwner($ownerA, function () use ($customerB): void {
        expect(Address::query()->count())->toBe(1);

        expect(fn () => Address::query()->create([
            'customer_id' => $customerB->id,
            'line1' => 'Cross-tenant',
            'city' => 'KL',
            'postcode' => '50000',
            'country' => 'MY',
        ]))->toThrow(InvalidArgumentException::class);
    });
});
