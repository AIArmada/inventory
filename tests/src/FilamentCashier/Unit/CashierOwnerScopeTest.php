<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentCashier\Fixtures\TenantBillableUser;
use AIArmada\Commerce\Tests\FilamentCashier\Fixtures\TenantRecord;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\FilamentCashier\Resources\UnifiedSubscriptionResource\Pages\CreateSubscription;
use AIArmada\FilamentCashier\Support\CashierOwnerScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

if (! function_exists('filamentCashier_invokeProtectedMethod')) {
    function filamentCashier_invokeProtectedMethod(object $object, string $method, array $arguments = []): mixed
    {
        $reflection = new ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $arguments);
    }
}

it('scopes user_id queries to current owner billables', function (): void {
    if (! Schema::hasTable('filament_cashier_tenant_billables')) {
        Schema::create('filament_cashier_tenant_billables', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->nullableMorphs('owner');
            $table->timestamps();
        });
    }

    if (! Schema::hasTable('filament_cashier_tenant_records')) {
        Schema::create('filament_cashier_tenant_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id');
            $table->string('name');
            $table->timestamps();
        });
    }

    /** @var class-string<\Illuminate\Database\Eloquent\Model> $ownerModel */
    $ownerModel = config('auth.providers.users.model');

    $ownerA = $ownerModel::query()->create([
        'name' => 'Owner A',
        'email' => 'owner-a@example.com',
        'password' => bcrypt('secret'),
    ]);

    $ownerB = $ownerModel::query()->create([
        'name' => 'Owner B',
        'email' => 'owner-b@example.com',
        'password' => bcrypt('secret'),
    ]);

    config()->set('cashier.models.billable', TenantBillableUser::class);

    $billableA = TenantBillableUser::query()->create([
        'name' => 'Billable A',
        'email' => 'billable-a@example.com',
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]);

    $billableB = TenantBillableUser::query()->create([
        'name' => 'Billable B',
        'email' => 'billable-b@example.com',
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
    ]);

    TenantRecord::query()->create([
        'user_id' => $billableA->getKey(),
        'name' => 'Record A',
    ]);

    TenantRecord::query()->create([
        'user_id' => $billableB->getKey(),
        'name' => 'Record B',
    ]);

    app()->bind(OwnerResolverInterface::class, fn () => new class($ownerA) implements OwnerResolverInterface
    {
        public function __construct(private readonly \Illuminate\Database\Eloquent\Model $owner) {}

        public function resolve(): ?\Illuminate\Database\Eloquent\Model
        {
            return $this->owner;
        }
    });

    $records = CashierOwnerScope::apply(TenantRecord::query())
        ->orderBy('name')
        ->pluck('name')
        ->all();

    expect($records)->toBe(['Record A']);
});

it('fails closed when billable supports owner scoping but no owner context exists', function (): void {
    if (! Schema::hasTable('filament_cashier_tenant_billables')) {
        Schema::create('filament_cashier_tenant_billables', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->nullableMorphs('owner');
            $table->timestamps();
        });
    }

    if (! Schema::hasTable('filament_cashier_tenant_records')) {
        Schema::create('filament_cashier_tenant_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id');
            $table->string('name');
            $table->timestamps();
        });
    }

    /** @var class-string<\Illuminate\Database\Eloquent\Model> $ownerModel */
    $ownerModel = config('auth.providers.users.model');

    $ownerA = $ownerModel::query()->create([
        'name' => 'Owner A3',
        'email' => 'owner-a3@example.com',
        'password' => bcrypt('secret'),
    ]);

    config()->set('cashier.models.billable', TenantBillableUser::class);

    $billableA = TenantBillableUser::query()->create([
        'name' => 'Billable A3',
        'email' => 'billable-a3@example.com',
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]);

    TenantRecord::query()->create([
        'user_id' => $billableA->getKey(),
        'name' => 'Record A3',
    ]);

    // No OwnerResolver binding here: OwnerContext::resolve() returns null.
    // Since the billable model supports owner scoping, this must fail closed.
    $records = CashierOwnerScope::apply(TenantRecord::query())
        ->pluck('name')
        ->all();

    expect($records)->toBe([]);
});

it('blocks selecting a cross-tenant customer when an owner context exists', function (): void {
    if (! Schema::hasTable('filament_cashier_tenant_billables')) {
        Schema::create('filament_cashier_tenant_billables', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->nullableMorphs('owner');
            $table->timestamps();
        });
    }

    /** @var class-string<\Illuminate\Database\Eloquent\Model> $ownerModel */
    $ownerModel = config('auth.providers.users.model');

    $ownerA = $ownerModel::query()->create([
        'name' => 'Owner A2',
        'email' => 'owner-a2@example.com',
        'password' => bcrypt('secret'),
    ]);

    $ownerB = $ownerModel::query()->create([
        'name' => 'Owner B2',
        'email' => 'owner-b2@example.com',
        'password' => bcrypt('secret'),
    ]);

    config()->set('cashier.models.billable', TenantBillableUser::class);

    $billableA = TenantBillableUser::query()->create([
        'name' => 'Billable A2',
        'email' => 'billable-a2@example.com',
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]);

    $billableB = TenantBillableUser::query()->create([
        'name' => 'Billable B2',
        'email' => 'billable-b2@example.com',
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
    ]);

    app()->bind(OwnerResolverInterface::class, fn () => new class($ownerA) implements OwnerResolverInterface
    {
        public function __construct(private readonly \Illuminate\Database\Eloquent\Model $owner) {}

        public function resolve(): ?\Illuminate\Database\Eloquent\Model
        {
            return $this->owner;
        }
    });

    $page = app(CreateSubscription::class);

    expect(fn () => filamentCashier_invokeProtectedMethod($page, 'handleRecordCreation', [[
        'user_id' => $billableB->getKey(),
        'gateway' => 'chip',
        'type' => 'default',
        'plan_id' => 'plan_a',
        'quantity' => 1,
        'has_trial' => false,
        'trial_days' => 14,
        'payment_method' => null,
    ]]))->toThrow(AuthorizationException::class);

    expect(filamentCashier_invokeProtectedMethod($page, 'getCustomerOptions'))
        ->toBeArray()
        ->toHaveKey((string) $billableA->getKey())
        ->not->toHaveKey((string) $billableB->getKey());
});
