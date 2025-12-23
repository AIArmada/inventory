<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentJnt\FilamentJntTestCase;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentJnt\Actions\CancelOrderAction;
use AIArmada\Jnt\Enums\CancellationReason;
use AIArmada\Jnt\Models\JntOrder;
use AIArmada\Jnt\Services\JntExpressService;

uses(FilamentJntTestCase::class);

it('is not visible for non-cancellable statuses', function (): void {
    $order = JntOrder::query()->create([
        'order_id' => 'ORD-1',
        'customer_code' => 'CUST',
        'status' => 'delivered',
    ]);

    $action = CancelOrderAction::make()->record($order);

    expect($action->isVisible())->toBeFalse();
});

it('shows an authentication required notification when unauthenticated', function (): void {
    $order = JntOrder::query()->create([
        'order_id' => 'ORD-2',
        'customer_code' => 'CUST',
        'status' => 'in_transit',
    ]);

    $action = CancelOrderAction::make()->record($order);
    $handler = $action->getActionFunction();

    expect($handler)->not()->toBeNull();

    $handler($order, [
        'reason' => CancellationReason::SYSTEM_ERROR->value,
        'custom_reason' => null,
    ]);

    $order->refresh();
    expect($order->status)->not()->toBe('cancelled');

    $notifications = session()->get('filament.notifications', []);
    expect(collect($notifications)->last()['title'])->toBe('Authentication Required');
});

it('cancels an order and updates the record when authenticated', function (): void {
    /** @var User $user */
    $user = User::query()->create([
        'name' => 'User',
        'email' => 'user@example.test',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    $captured = [];

    app()->bind(JntExpressService::class, function () use (&$captured) {
        return new class($captured)
        {
            /**
             * @param  array<string, mixed>  $captured
             */
            public function __construct(private array &$captured) {}

            public function cancelOrder(string $orderId, string $reason, ?string $trackingNumber = null): void
            {
                $this->captured = compact('orderId', 'reason', 'trackingNumber');
            }
        };
    });

    $order = JntOrder::query()->create([
        'order_id' => 'ORD-3',
        'tracking_number' => 'TRK-3',
        'customer_code' => 'CUST',
        'status' => 'in_transit',
    ]);

    $action = CancelOrderAction::make()->record($order);
    $handler = $action->getActionFunction();

    $handler($order, [
        'reason' => CancellationReason::OTHER->value,
        'custom_reason' => 'Customer requested cancellation',
    ]);

    $order->refresh();

    expect($order->status)->toBe('cancelled');
    expect($captured['orderId'])->toBe('ORD-3');
    expect($captured['reason'])->toBe('Customer requested cancellation');
    expect($captured['trackingNumber'])->toBe('TRK-3');

    $notifications = session()->get('filament.notifications', []);
    expect(collect($notifications)->last()['title'])->toBe('Order Cancelled');
});

it('does not cancel when a cancellation reason is missing', function (): void {
    /** @var User $user */
    $user = User::query()->create([
        'name' => 'User',
        'email' => 'user-missing-reason@example.test',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    $order = JntOrder::query()->create([
        'order_id' => 'ORD-5',
        'customer_code' => 'CUST',
        'status' => 'in_transit',
    ]);

    $action = CancelOrderAction::make()->record($order);
    $handler = $action->getActionFunction();

    $handler($order, ['reason' => '', 'custom_reason' => null]);

    $order->refresh();
    expect($order->status)->not()->toBe('cancelled');

    $notifications = session()->get('filament.notifications', []);
    expect(collect($notifications)->last()['title'])->toBe('Invalid Request');
});

it('does not cancel when Other is selected without details', function (): void {
    /** @var User $user */
    $user = User::query()->create([
        'name' => 'User',
        'email' => 'user-missing-details@example.test',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    $order = JntOrder::query()->create([
        'order_id' => 'ORD-6',
        'customer_code' => 'CUST',
        'status' => 'in_transit',
    ]);

    $action = CancelOrderAction::make()->record($order);
    $handler = $action->getActionFunction();

    $handler($order, ['reason' => CancellationReason::OTHER->value, 'custom_reason' => '']);

    $order->refresh();
    expect($order->status)->not()->toBe('cancelled');

    $notifications = session()->get('filament.notifications', []);
    expect(collect($notifications)->last()['title'])->toBe('Additional Details Required');
});

it('handles service exceptions and shows a failure notification', function (): void {
    /** @var User $user */
    $user = User::query()->create([
        'name' => 'User',
        'email' => 'user2@example.test',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    app()->bind(JntExpressService::class, fn () => new class
    {
        public function cancelOrder(string $orderId, string $reason, ?string $trackingNumber = null): void
        {
            throw new RuntimeException('fail');
        }
    });

    $order = JntOrder::query()->create([
        'order_id' => 'ORD-4',
        'customer_code' => 'CUST',
        'status' => 'in_transit',
    ]);

    $action = CancelOrderAction::make()->record($order);
    $handler = $action->getActionFunction();

    $handler($order, [
        'reason' => CancellationReason::SYSTEM_ERROR->value,
        'custom_reason' => null,
    ]);

    $order->refresh();
    expect($order->status)->not()->toBe('cancelled');

    $notifications = session()->get('filament.notifications', []);
    expect(collect($notifications)->last()['title'])->toBe('Cancellation Failed');
});
