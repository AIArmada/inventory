<?php

declare(strict_types=1);

use AIArmada\CashierChip\Exceptions\CustomerAlreadyCreated;
use AIArmada\CashierChip\Exceptions\IncompletePayment;
use AIArmada\CashierChip\Exceptions\InvalidCustomer;
use AIArmada\CashierChip\Exceptions\InvalidPaymentMethod;
use AIArmada\CashierChip\Exceptions\SubscriptionUpdateFailure;
use AIArmada\CashierChip\Payment;
use AIArmada\CashierChip\Subscription;
use AIArmada\Chip\DataObjects\Purchase;

it('can create customer already created exception', function (): void {
    $owner = new class
    {
        public string $chip_id = 'test-chip-id';
    };

    $exception = CustomerAlreadyCreated::exists($owner);

    expect($exception)->toBeInstanceOf(CustomerAlreadyCreated::class);
    expect($exception->getMessage())->toContain('test-chip-id');
});

it('can create invalid customer exception', function (): void {
    $owner = new class {};

    $exception = InvalidCustomer::notYetCreated($owner);

    expect($exception)->toBeInstanceOf(InvalidCustomer::class);
    expect($exception->getMessage())->toContain('not a CHIP customer');
});

it('can create invalid payment method exception for invalid owner', function (): void {
    $owner = new class {};

    $exception = InvalidPaymentMethod::invalidOwner('pm_123', $owner);

    expect($exception)->toBeInstanceOf(InvalidPaymentMethod::class);
    expect($exception->getMessage())->toContain('does not belong');
});

it('can create invalid payment method exception for not found', function (): void {
    $exception = InvalidPaymentMethod::notFound();

    expect($exception)->toBeInstanceOf(InvalidPaymentMethod::class);
    expect($exception->getMessage())->toContain('No payment method');
});

it('can create incomplete payment exception for redirect', function (): void {
    $payment = new Payment(Purchase::fromArray([
        'id' => 'test-id',
        'status' => 'pending',
        'checkout_url' => 'https://chip.com/checkout',
    ]));

    $exception = IncompletePayment::requiresRedirect($payment);

    expect($exception)->toBeInstanceOf(IncompletePayment::class);
    expect($exception->getMessage())->toContain('redirect');
    expect($exception->payment())->toBe($payment);
});

it('can create incomplete payment exception for failed', function (): void {
    $payment = new Payment(Purchase::fromArray([
        'id' => 'test-id',
        'status' => 'failed',
    ]));

    $exception = IncompletePayment::failed($payment);

    expect($exception)->toBeInstanceOf(IncompletePayment::class);
    expect($exception->getMessage())->toContain('failed');
});

it('can create incomplete payment exception for expired', function (): void {
    $payment = new Payment(Purchase::fromArray([
        'id' => 'test-id',
        'status' => 'expired',
    ]));

    $exception = IncompletePayment::expired($payment);

    expect($exception)->toBeInstanceOf(IncompletePayment::class);
    expect($exception->getMessage())->toContain('expired');
});

it('can create subscription update failure for incomplete subscription', function (): void {
    $subscription = new Subscription([
        'type' => 'standard',
        'chip_status' => Subscription::STATUS_INCOMPLETE,
    ]);

    $exception = SubscriptionUpdateFailure::incompleteSubscription($subscription);

    expect($exception)->toBeInstanceOf(SubscriptionUpdateFailure::class);
    expect($exception->getMessage())->toContain('incomplete payment');
    expect($exception->subscription())->toBe($subscription);
});

it('can create subscription update failure for duplicate price', function (): void {
    $subscription = new Subscription([
        'type' => 'standard',
    ]);

    $exception = SubscriptionUpdateFailure::duplicatePrice($subscription, 'price_monthly');

    expect($exception)->toBeInstanceOf(SubscriptionUpdateFailure::class);
    expect($exception->getMessage())->toContain('price_monthly');
    expect($exception->getMessage())->toContain('already attached');
});

it('can create subscription update failure for deleting last price', function (): void {
    $subscription = new Subscription([
        'type' => 'standard',
    ]);

    $exception = SubscriptionUpdateFailure::cannotDeleteLastPrice($subscription);

    expect($exception)->toBeInstanceOf(SubscriptionUpdateFailure::class);
    expect($exception->getMessage())->toContain('last price');
});
