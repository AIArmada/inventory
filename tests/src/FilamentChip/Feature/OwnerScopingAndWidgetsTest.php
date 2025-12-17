<?php

declare(strict_types=1);

use AIArmada\Chip\Models\Purchase;
use AIArmada\FilamentChip\Resources\PurchaseResource;
use AIArmada\FilamentChip\Widgets\PaymentMethodsWidget;
use AIArmada\FilamentChip\Widgets\RevenueChartWidget;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::dropIfExists('tenants');
    Schema::dropIfExists('chip_purchases');

    Schema::create('chip_purchases', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('owner_type')->nullable();
        $table->string('owner_id')->nullable();
        $table->string('status')->nullable();
        $table->boolean('is_test')->default(false);
        $table->integer('created_on')->nullable();
        $table->string('payment_method')->nullable();
        $table->json('purchase')->nullable();
        $table->json('payment')->nullable();
        $table->json('transaction_data')->nullable();
        $table->json('metadata')->nullable();
    });

    config()->set('chip.owner.enabled', true);
});

afterEach(function (): void {
    config()->set('chip.owner.enabled', true);
});

it('scopes filament resource queries to the current owner', function (): void {
    Schema::create('tenants', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });

    $ownerA = new class extends \Illuminate\Database\Eloquent\Model {
        protected $table = 'tenants';
        public $incrementing = false;
        protected $keyType = 'string';
        protected $guarded = [];
    };

    $ownerB = new class extends \Illuminate\Database\Eloquent\Model {
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

    app()->bind(\AIArmada\CommerceSupport\Contracts\OwnerResolverInterface::class, fn () => new class ($ownerA) implements \AIArmada\CommerceSupport\Contracts\OwnerResolverInterface {
        public function __construct(private \Illuminate\Database\Eloquent\Model $owner) {}

        public function resolve(): ?\Illuminate\Database\Eloquent\Model
        {
            return $this->owner;
        }
    });

    Purchase::withoutEvents(function () use ($ownerA, $ownerB): void {
        Purchase::create([
            'status' => 'paid',
            'is_test' => false,
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => (string) $ownerA->getKey(),
            'purchase' => ['amount' => 1000],
        ]);

        Purchase::create([
            'status' => 'paid',
            'is_test' => false,
            'owner_type' => $ownerB->getMorphClass(),
            'owner_id' => (string) $ownerB->getKey(),
            'purchase' => ['amount' => 2000],
        ]);

        Purchase::create([
            'status' => 'paid',
            'is_test' => false,
            'owner_type' => null,
            'owner_id' => null,
            'purchase' => ['amount' => 3000],
        ]);
    });

    expect(PurchaseResource::getEloquentQuery()->count())->toBe(1);
});

it('computes payment method breakdown without including test mode purchases', function (): void {
    Purchase::withoutEvents(function (): void {
        Purchase::create([
            'status' => 'paid',
            'is_test' => false,
            'purchase' => ['amount' => 1000],
            'payment' => ['payment_type' => 'fpx'],
        ]);

        Purchase::create([
            'status' => 'paid',
            'is_test' => true,
            'purchase' => ['amount' => 9999],
            'payment' => ['payment_type' => 'card'],
        ]);
    });

    $widget = app(PaymentMethodsWidget::class);

    $ref = new ReflectionClass($widget);
    $method = $ref->getMethod('getPaymentMethodBreakdown');
    $method->setAccessible(true);

    /** @var array<string, array{count: int, amount: int}> $breakdown */
    $breakdown = $method->invoke($widget);

    expect($breakdown)->toHaveKey('FPX');
    expect($breakdown['FPX']['count'])->toBe(1);
    expect($breakdown['FPX']['amount'])->toBe(1000);
});

it('generates revenue data for the last 30 days', function (): void {
    $today = now();

    Purchase::withoutEvents(function () use ($today): void {
        Purchase::create([
            'status' => 'paid',
            'is_test' => false,
            'created_on' => $today->copy()->startOfDay()->getTimestamp(),
            'purchase' => ['amount' => 1000],
        ]);

        Purchase::create([
            'status' => 'paid',
            'is_test' => false,
            'created_on' => $today->copy()->endOfDay()->getTimestamp(),
            'purchase' => ['amount' => 2000],
        ]);
    });

    $widget = app(RevenueChartWidget::class);

    $ref = new ReflectionClass($widget);
    $method = $ref->getMethod('getRevenueData');
    $method->setAccessible(true);

    /** @var array{labels: array<string>, amounts: array<int>} $data */
    $data = $method->invoke($widget);

    expect($data['labels'])->toHaveCount(30);
    expect($data['amounts'])->toHaveCount(30);
    expect(max($data['amounts']))->toBeGreaterThanOrEqual(30);
});
