<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Customers\Models\Customer;
use AIArmada\FilamentCustomers\Widgets\CustomerStatsWidget;
use AIArmada\FilamentCustomers\Widgets\RecentCustomersWidget;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

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
});

it('CustomerStatsWidget is owner-scoped for counts and aggregates', function (): void {
    Carbon::setTestNow(Carbon::parse('2025-01-15 12:00:00'));

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
        'first_name' => 'A1',
        'last_name' => 'User',
        'email' => 'a1@example.com',
        'status' => 'active',
        'accepts_marketing' => true,
        'is_tax_exempt' => false,
        'created_at' => Carbon::now()->subDays(2),
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]);

    Customer::query()->create([
        'first_name' => 'B1',
        'last_name' => 'User',
        'email' => 'b1@example.com',
        'status' => 'active',
        'accepts_marketing' => true,
        'is_tax_exempt' => false,
        'created_at' => Carbon::now()->subDays(2),
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
    ]);

    $widget = new CustomerStatsWidget;

    $stats = (function () use ($widget): array {
        $method = new ReflectionMethod($widget, 'getStats');
        $method->setAccessible(true);

        /** @var array<int, \Filament\Widgets\StatsOverviewWidget\Stat> $result */
        $result = $method->invoke($widget);

        return $result;
    })();

    $total = collect($stats)->first(fn ($stat) => $stat->getLabel() === 'Total Customers');

    expect($total?->getValue())->toBe('1');
});

it('RecentCustomersWidget query is owner-scoped', function (): void {
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
        'first_name' => 'A',
        'last_name' => 'High',
        'email' => 'a-high@example.com',
        'status' => 'active',
        'accepts_marketing' => true,
        'is_tax_exempt' => false,
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]);

    Customer::query()->create([
        'first_name' => 'B',
        'last_name' => 'High',
        'email' => 'b-high@example.com',
        'status' => 'active',
        'accepts_marketing' => true,
        'is_tax_exempt' => false,
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
    ]);

    $widget = new RecentCustomersWidget;
    $tableLivewire = Mockery::mock(HasTable::class);

    $table = $widget->table(Table::make($tableLivewire));

    $query = (function () use ($table) {
        $method = new ReflectionMethod($table, 'getQuery');
        $method->setAccessible(true);

        return $method->invoke($table);
    })();

    $emails = $query->pluck('email')->all();
    expect($emails)->toEqual(['a-high@example.com']);
});
