<?php

declare(strict_types=1);

use AIArmada\CashierChip\Events\PaymentFailed;
use AIArmada\CashierChip\Events\PaymentSucceeded;
use AIArmada\CashierChip\Events\SubscriptionCanceled;
use AIArmada\CashierChip\Events\SubscriptionCreated;
use AIArmada\CashierChip\Events\WebhookHandled;
use AIArmada\CashierChip\Events\WebhookReceived;
use AIArmada\CashierChip\Subscription;
use AIArmada\Commerce\Tests\CashierChip\Fixtures\User;

it('can create webhook received event', function (): void {
    $payload = ['event_type' => 'purchase.payment.success'];

    $event = new WebhookReceived($payload);

    expect($event->payload)->toBe($payload);
});

it('can create webhook handled event', function (): void {
    $payload = ['event_type' => 'purchase.payment.success'];

    $event = new WebhookHandled($payload);

    expect($event->payload)->toBe($payload);
});

it('can create payment succeeded event', function (): void {
    $billable = new User(['id' => 1, 'name' => 'Test']);
    $purchase = ['id' => 'test-purchase'];

    $event = new PaymentSucceeded($billable, $purchase);

    expect($event->billable)->toBe($billable);
    expect($event->purchase)->toBe($purchase);
});

it('can create payment failed event', function (): void {
    $billable = new User(['id' => 1, 'name' => 'Test']);
    $purchase = ['id' => 'test-purchase', 'error' => 'declined'];

    $event = new PaymentFailed($billable, $purchase);

    expect($event->billable)->toBe($billable);
    expect($event->purchase)->toBe($purchase);
});

it('can create subscription created event', function (): void {
    $subscription = new Subscription([
        'type' => 'standard',
        'chip_id' => 'test-sub-id',
    ]);

    $event = new SubscriptionCreated($subscription);

    expect($event->subscription)->toBe($subscription);
});

it('can create subscription canceled event', function (): void {
    $subscription = new Subscription([
        'type' => 'standard',
        'chip_id' => 'test-sub-id',
    ]);

    $event = new SubscriptionCanceled($subscription);

    expect($event->subscription)->toBe($subscription);
});
