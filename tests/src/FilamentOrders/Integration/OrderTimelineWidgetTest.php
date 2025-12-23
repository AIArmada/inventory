<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentOrders\Fixtures\TestOwner;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\FilamentOrders\Widgets\OrderTimelineWidget;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\Created;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

uses(TestCase::class);

beforeEach(function (): void {
    Schema::dropIfExists('test_owners');

    Schema::create('test_owners', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });

    config()->set('orders.owner.enabled', true);
    config()->set('orders.owner.include_global', true);
    config()->set('orders.owner.auto_assign_on_create', false);

    app()->instance(OwnerResolverInterface::class, new class implements OwnerResolverInterface
    {
        public function resolve(): ?Model
        {
            return null;
        }
    });
});

it('builds a timeline from payments, notes and shipment state', function (): void {
    Carbon::setTestNow(Carbon::parse('2025-01-10 10:00:00'));

    $ownerA = TestOwner::query()->create(['name' => 'Owner A']);

    app()->instance(OwnerResolverInterface::class, new class($ownerA) implements OwnerResolverInterface
    {
        public function __construct(private readonly ?Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    $order = Order::query()->create([
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
        'status' => Created::class,
        'currency' => 'MYR',
        'subtotal' => 10000,
        'grand_total' => 10000,
        'shipped_at' => now()->subHour(),
    ]);

    $order->forceFill(['created_at' => now()->subHours(6), 'updated_at' => now()->subHours(6)])->save();

    $payment = $order->payments()->create([
        'gateway' => 'manual',
        'transaction_id' => 'txn_123',
        'amount' => 10000,
        'currency' => 'MYR',
        'status' => 'completed',
        'paid_at' => now()->subHours(3),
    ]);

    $payment->forceFill(['created_at' => now()->subHours(3), 'updated_at' => now()->subHours(3)])->save();

    $note = $order->orderNotes()->create([
        'user_id' => null,
        'content' => 'Packed and ready.',
        'is_customer_visible' => false,
    ]);

    $note->forceFill(['created_at' => now()->subMinutes(30), 'updated_at' => now()->subMinutes(30)])->save();

    $widget = app(OrderTimelineWidget::class);
    $widget->mount($order);

    $events = $widget->getTimelineEvents();

    expect($events)->not->toBeEmpty();
    expect($events->pluck('type')->all())
        ->toContain('created', 'payment', 'shipped', 'note');
});
