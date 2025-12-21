<?php

declare(strict_types=1);

use AIArmada\Chip\Models\BankAccount;
use AIArmada\Chip\Models\Purchase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::dropIfExists('tenants');
    Schema::dropIfExists('chip_purchases');
    Schema::dropIfExists('chip_bank_accounts');

    Schema::create('tenants', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('chip_purchases', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('owner_type')->nullable();
        $table->string('owner_id')->nullable();
        $table->string('status')->nullable();
        $table->boolean('is_test')->default(false);
        $table->json('purchase')->nullable();
    });

    Schema::create('chip_bank_accounts', function (Blueprint $table): void {
        $table->integer('id')->primary();
        $table->string('owner_type')->nullable();
        $table->string('owner_id')->nullable();
        $table->string('status')->nullable();
        $table->string('account_number')->nullable();
        $table->string('bank_code')->nullable();
        $table->string('name')->nullable();
        $table->timestamp('created_at')->nullable();
        $table->timestamp('updated_at')->nullable();
    });

    config()->set('chip.owner.enabled', true);
    config()->set('chip.owner.include_global', false);
    config()->set('chip.owner.auto_assign_on_create', false);
});

it('scopes reads to the current owner by default', function (): void {
    $ownerA = new class extends Model
    {
        protected $table = 'tenants';

        public $incrementing = false;

        protected $keyType = 'string';

        protected $guarded = [];
    };

    $ownerB = new class extends Model
    {
        protected $table = 'tenants';

        public $incrementing = false;

        protected $keyType = 'string';

        protected $guarded = [];
    };

    $ownerA->id = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
    $ownerA->name = 'A';
    $ownerA->save();

    $ownerB->id = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';
    $ownerB->name = 'B';
    $ownerB->save();

    app()->bind(OwnerResolverInterface::class, fn () => new class($ownerA) implements OwnerResolverInterface
    {
        public function __construct(private Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    Purchase::withoutEvents(function () use ($ownerA, $ownerB): void {
        Purchase::create([
            'id' => '11111111-1111-1111-1111-111111111111',
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => (string) $ownerA->getKey(),
            'status' => 'paid',
            'purchase' => ['amount' => 1000],
        ]);

        Purchase::create([
            'id' => '22222222-2222-2222-2222-222222222222',
            'owner_type' => $ownerB->getMorphClass(),
            'owner_id' => (string) $ownerB->getKey(),
            'status' => 'paid',
            'purchase' => ['amount' => 2000],
        ]);

        Purchase::create([
            'id' => '33333333-3333-3333-3333-333333333333',
            'owner_type' => null,
            'owner_id' => null,
            'status' => 'paid',
            'purchase' => ['amount' => 3000],
        ]);
    });

    expect(Purchase::query()->forOwner()->count())->toBe(1);
});

it('can optionally include global rows when explicitly requested', function (): void {
    config()->set('chip.owner.include_global', true);

    $ownerA = new class extends Model
    {
        protected $table = 'tenants';

        public $incrementing = false;

        protected $keyType = 'string';

        protected $guarded = [];
    };

    $ownerA->id = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
    $ownerA->name = 'A';
    $ownerA->save();

    app()->bind(OwnerResolverInterface::class, fn () => new class($ownerA) implements OwnerResolverInterface
    {
        public function __construct(private Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    Purchase::withoutEvents(function () use ($ownerA): void {
        Purchase::create([
            'id' => '11111111-1111-1111-1111-111111111111',
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => (string) $ownerA->getKey(),
            'status' => 'paid',
            'purchase' => ['amount' => 1000],
        ]);

        Purchase::create([
            'id' => '33333333-3333-3333-3333-333333333333',
            'owner_type' => null,
            'owner_id' => null,
            'status' => 'paid',
            'purchase' => ['amount' => 3000],
        ]);
    });

    expect(Purchase::query()->forOwner()->count())->toBe(2);
});

it('treats forOwner(null) as global-only (never current owner)', function (): void {
    config()->set('chip.owner.include_global', true);

    $ownerA = new class extends Model
    {
        protected $table = 'tenants';

        public $incrementing = false;

        protected $keyType = 'string';

        protected $guarded = [];
    };

    $ownerA->id = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
    $ownerA->name = 'A';
    $ownerA->save();

    app()->bind(OwnerResolverInterface::class, fn () => new class($ownerA) implements OwnerResolverInterface
    {
        public function __construct(private Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    Purchase::withoutEvents(function () use ($ownerA): void {
        Purchase::create([
            'id' => '11111111-1111-1111-1111-111111111111',
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => (string) $ownerA->getKey(),
            'status' => 'paid',
            'purchase' => ['amount' => 1000],
        ]);

        Purchase::create([
            'id' => '33333333-3333-3333-3333-333333333333',
            'owner_type' => null,
            'owner_id' => null,
            'status' => 'paid',
            'purchase' => ['amount' => 3000],
        ]);
    });

    expect(Purchase::query()->forOwner(null)->pluck('id')->all())
        ->toBe(['33333333-3333-3333-3333-333333333333']);
});

it('scopes integer-ID models too (option lists must be owner-safe)', function (): void {
    $ownerA = new class extends Model
    {
        protected $table = 'tenants';

        public $incrementing = false;

        protected $keyType = 'string';

        protected $guarded = [];
    };

    $ownerB = new class extends Model
    {
        protected $table = 'tenants';

        public $incrementing = false;

        protected $keyType = 'string';

        protected $guarded = [];
    };

    $ownerA->id = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
    $ownerA->name = 'A';
    $ownerA->save();

    $ownerB->id = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';
    $ownerB->name = 'B';
    $ownerB->save();

    app()->bind(OwnerResolverInterface::class, fn () => new class($ownerA) implements OwnerResolverInterface
    {
        public function __construct(private Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    BankAccount::withoutEvents(function () use ($ownerA, $ownerB): void {
        BankAccount::create([
            'id' => 1,
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => (string) $ownerA->getKey(),
            'status' => 'active',
            'name' => 'A',
            'account_number' => '123',
            'bank_code' => 'MBBEMYKL',
        ]);

        BankAccount::create([
            'id' => 2,
            'owner_type' => $ownerB->getMorphClass(),
            'owner_id' => (string) $ownerB->getKey(),
            'status' => 'active',
            'name' => 'B',
            'account_number' => '456',
            'bank_code' => 'HLBBMYKL',
        ]);
    });

    expect(BankAccount::query()->forOwner()->count())->toBe(1);
});
