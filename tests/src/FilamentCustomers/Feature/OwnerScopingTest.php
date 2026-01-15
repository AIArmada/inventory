<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Models\Segment;
use AIArmada\FilamentCustomers\Resources\CustomerResource;
use AIArmada\FilamentCustomers\Resources\SegmentResource;
use Illuminate\Database\Eloquent\Model;

if (! function_exists('filamentCustomers_makeOwner')) {
    function filamentCustomers_makeOwner(string $id): Model
    {
        return new class($id) extends Model
        {
            public $incrementing = false;

            protected $keyType = 'string';

            public function __construct(private readonly string $uuid) {}

            public function getKey(): mixed
            {
                return $this->uuid;
            }

            public function getMorphClass(): string
            {
                return 'tests:owner';
            }
        };
    }
}

beforeEach(function (): void {
    Customer::query()->delete();
    Segment::query()->delete();
});

it('scopes CustomerResource query to the resolved owner', function (): void {
    $ownerA = filamentCustomers_makeOwner('00000000-0000-0000-0000-00000000000a');
    $ownerB = filamentCustomers_makeOwner('00000000-0000-0000-0000-00000000000b');

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new class($ownerA) implements OwnerResolverInterface
    {
        public function __construct(private readonly Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    Customer::query()->create([
        'first_name' => 'Alice',
        'last_name' => 'A',
        'email' => 'alice-a@example.com',
        'status' => 'active',
        'accepts_marketing' => true,
        'is_tax_exempt' => false,
        
        
        
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]);

    Customer::query()->create([
        'first_name' => 'Bob',
        'last_name' => 'B',
        'email' => 'bob-b@example.com',
        'status' => 'active',
        'accepts_marketing' => true,
        'is_tax_exempt' => false,
        
        
        
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
    ]);

    $emails = CustomerResource::getEloquentQuery()->pluck('email')->all();
    expect($emails)->toEqual(['alice-a@example.com']);
});

it('scopes SegmentResource query to the resolved owner', function (): void {
    $ownerA = filamentCustomers_makeOwner('00000000-0000-0000-0000-00000000000a');
    $ownerB = filamentCustomers_makeOwner('00000000-0000-0000-0000-00000000000b');

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new class($ownerB) implements OwnerResolverInterface
    {
        public function __construct(private readonly Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    Segment::query()->create([
        'name' => 'VIP',
        'slug' => 'vip-a',
        'type' => 'custom',
        'is_active' => true,
        'is_automatic' => false,
        'priority' => 0,
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]);

    Segment::query()->create([
        'name' => 'Newsletter',
        'slug' => 'newsletter-b',
        'type' => 'custom',
        'is_active' => true,
        'is_automatic' => false,
        'priority' => 0,
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
    ]);

    $names = SegmentResource::getEloquentQuery()->pluck('name')->all();
    expect($names)->toEqual(['Newsletter']);
});
