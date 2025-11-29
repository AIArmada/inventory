<?php

declare(strict_types=1);

use AIArmada\CashierChip\CashierChip;
use AIArmada\CashierChip\Subscription;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use Carbon\Carbon;

uses(CashierChipTestCase::class);

beforeEach(function (): void {
    $this->user = $this->createUser();
});

// Customer Management Tests

it('can check if user has chip id', function (): void {
    expect($this->user->hasChipId())->toBeFalse();

    $this->user->update(['chip_id' => 'test-chip-id']);

    expect($this->user->hasChipId())->toBeTrue();
});

it('can get chip id', function (): void {
    $this->user->update(['chip_id' => 'test-chip-id']);

    expect($this->user->chipId())->toBe('test-chip-id');
});

it('can check if has default payment method', function (): void {
    expect($this->user->hasDefaultPaymentMethod())->toBeFalse();

    // hasDefaultPaymentMethod() checks pm_type, not default_pm_id
    $this->user->update(['pm_type' => 'card', 'pm_last_four' => '4242']);

    expect($this->user->hasDefaultPaymentMethod())->toBeTrue();
});

it('can get default payment method', function (): void {
    // First, set up user with chip_id and a stored default payment method
    $this->user->update([
        'chip_id' => 'cli_test123',
        'pm_type' => 'card',
        'pm_last_four' => '4242',
    ]);

    // Add a recurring token to the fake client using CashierChip::getFake()
    $fake = CashierChip::getFake();
    $fake->addRecurringToken($this->user->chip_id, [
        'type' => 'card',
        'card_brand' => 'Visa',
        'last_4' => '4242',
        'exp_month' => 12,
        'exp_year' => 2030,
    ]);

    // Now get the default payment method
    $paymentMethod = $this->user->defaultPaymentMethod();

    expect($paymentMethod)->not->toBeNull();
    expect($paymentMethod->brand())->toBe('Visa');
    expect($paymentMethod->lastFour())->toBe('4242');
});

it('can update default payment method', function (): void {
    // Set up user with chip_id
    $this->user->update(['chip_id' => 'cli_test456']);

    // Add a recurring token to the fake client using CashierChip::getFake()
    $fake = CashierChip::getFake();
    $token = $fake->addRecurringToken($this->user->chip_id, [
        'type' => 'card',
        'card_brand' => 'Mastercard',
        'last_4' => '5555',
        'exp_month' => 6,
        'exp_year' => 2028,
    ]);

    // Update default payment method
    $this->user->updateDefaultPaymentMethod($token['id']);

    // Refresh the user to get the latest values
    $this->user->refresh();

    expect($this->user->pm_type)->toBe('card');
    expect($this->user->pm_last_four)->toBe('5555');
});

// Subscription Tests

it('can start a new subscription', function (): void {
    $builder = $this->user->newSubscription('standard', 'price_monthly');

    expect($builder)->toBeInstanceOf(AIArmada\CashierChip\SubscriptionBuilder::class);
});

it('can check if subscribed', function (): void {
    expect($this->user->subscribed('standard'))->toBeFalse();

    $this->user->subscriptions()->create([
        'type' => 'standard',
        'chip_id' => 'test-sub-id',
        'chip_status' => Subscription::STATUS_ACTIVE,
        'chip_price' => 'price_monthly',
    ]);

    // Refresh the model to reload relationships
    $this->user->refresh();

    expect($this->user->subscribed('standard'))->toBeTrue();
});

it('can check if subscribed to specific price', function (): void {
    $this->user->subscriptions()->create([
        'type' => 'standard',
        'chip_id' => 'test-sub-id',
        'chip_status' => Subscription::STATUS_ACTIVE,
        'chip_price' => 'price_monthly',
    ]);

    expect($this->user->subscribedToPrice('price_monthly', 'standard'))->toBeTrue();
    expect($this->user->subscribedToPrice('price_yearly', 'standard'))->toBeFalse();
});

it('can get specific subscription', function (): void {
    $this->user->subscriptions()->create([
        'type' => 'standard',
        'chip_id' => 'test-sub-id',
        'chip_status' => Subscription::STATUS_ACTIVE,
        'chip_price' => 'price_monthly',
    ]);

    $subscription = $this->user->subscription('standard');

    expect($subscription)->toBeInstanceOf(Subscription::class);
    expect($subscription->type)->toBe('standard');
});

it('returns null for non-existent subscription', function (): void {
    expect($this->user->subscription('standard'))->toBeNull();
});

it('can have multiple subscriptions', function (): void {
    $this->user->subscriptions()->create([
        'type' => 'standard',
        'chip_id' => 'test-sub-1',
        'chip_status' => Subscription::STATUS_ACTIVE,
        'chip_price' => 'price_monthly',
    ]);

    $this->user->subscriptions()->create([
        'type' => 'swimming',
        'chip_id' => 'test-sub-2',
        'chip_status' => Subscription::STATUS_ACTIVE,
        'chip_price' => 'price_swimming',
    ]);

    expect($this->user->subscriptions)->toHaveCount(2);
    expect($this->user->subscribed('standard'))->toBeTrue();
    expect($this->user->subscribed('swimming'))->toBeTrue();
});

it('can check if on trial', function (): void {
    expect($this->user->onTrial('standard'))->toBeFalse();

    $this->user->subscriptions()->create([
        'type' => 'standard',
        'chip_id' => 'test-sub-id',
        'chip_status' => Subscription::STATUS_TRIALING,
        'chip_price' => 'price_monthly',
        'trial_ends_at' => Carbon::now()->addDays(14),
    ]);

    // Refresh the model to reload relationships
    $this->user->refresh();

    expect($this->user->onTrial('standard'))->toBeTrue();
});

it('can check generic trial on model', function (): void {
    expect($this->user->onGenericTrial())->toBeFalse();

    $this->user->update(['trial_ends_at' => Carbon::now()->addDays(14)]);

    expect($this->user->onGenericTrial())->toBeTrue();
});

// Subscription Scopes

it('has subscriptions relationship', function (): void {
    expect($this->user->subscriptions())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\HasMany::class);
});

it('can get only active subscriptions', function (): void {
    $this->user->subscriptions()->create([
        'type' => 'active',
        'chip_id' => 'test-sub-1',
        'chip_status' => Subscription::STATUS_ACTIVE,
        'chip_price' => 'price_monthly',
    ]);

    $this->user->subscriptions()->create([
        'type' => 'canceled',
        'chip_id' => 'test-sub-2',
        'chip_status' => Subscription::STATUS_CANCELED,
        'chip_price' => 'price_monthly',
        'ends_at' => Carbon::now()->subDay(),
    ]);

    $activeSubscriptions = $this->user->subscriptions()->active()->get();

    expect($activeSubscriptions)->toHaveCount(1);
    expect($activeSubscriptions->first()->type)->toBe('active');
});

it('can get only canceled subscriptions', function (): void {
    $this->user->subscriptions()->create([
        'type' => 'active',
        'chip_id' => 'test-sub-1',
        'chip_status' => Subscription::STATUS_ACTIVE,
        'chip_price' => 'price_monthly',
    ]);

    $this->user->subscriptions()->create([
        'type' => 'canceled',
        'chip_id' => 'test-sub-2',
        'chip_status' => Subscription::STATUS_ACTIVE,
        'chip_price' => 'price_monthly',
        'ends_at' => Carbon::now()->addDays(7),
    ]);

    $canceledSubscriptions = $this->user->subscriptions()->canceled()->get();

    expect($canceledSubscriptions)->toHaveCount(1);
    expect($canceledSubscriptions->first()->type)->toBe('canceled');
});

it('can get only recurring subscriptions', function (): void {
    $this->user->subscriptions()->create([
        'type' => 'recurring',
        'chip_id' => 'test-sub-1',
        'chip_status' => Subscription::STATUS_ACTIVE,
        'chip_price' => 'price_monthly',
    ]);

    $this->user->subscriptions()->create([
        'type' => 'trial',
        'chip_id' => 'test-sub-2',
        'chip_status' => Subscription::STATUS_TRIALING,
        'chip_price' => 'price_monthly',
        'trial_ends_at' => Carbon::now()->addDays(14),
    ]);

    $recurringSubscriptions = $this->user->subscriptions()->recurring()->get();

    expect($recurringSubscriptions)->toHaveCount(1);
    expect($recurringSubscriptions->first()->type)->toBe('recurring');
});

it('can get subscriptions on trial', function (): void {
    $this->user->subscriptions()->create([
        'type' => 'active',
        'chip_id' => 'test-sub-1',
        'chip_status' => Subscription::STATUS_ACTIVE,
        'chip_price' => 'price_monthly',
    ]);

    $this->user->subscriptions()->create([
        'type' => 'trial',
        'chip_id' => 'test-sub-2',
        'chip_status' => Subscription::STATUS_TRIALING,
        'chip_price' => 'price_monthly',
        'trial_ends_at' => Carbon::now()->addDays(14),
    ]);

    $trialSubscriptions = $this->user->subscriptions()->onTrial()->get();

    expect($trialSubscriptions)->toHaveCount(1);
    expect($trialSubscriptions->first()->type)->toBe('trial');
});

it('can get subscriptions on grace period', function (): void {
    $this->user->subscriptions()->create([
        'type' => 'active',
        'chip_id' => 'test-sub-1',
        'chip_status' => Subscription::STATUS_ACTIVE,
        'chip_price' => 'price_monthly',
    ]);

    $this->user->subscriptions()->create([
        'type' => 'grace',
        'chip_id' => 'test-sub-2',
        'chip_status' => Subscription::STATUS_ACTIVE,
        'chip_price' => 'price_monthly',
        'ends_at' => Carbon::now()->addDays(7),
    ]);

    $graceSubscriptions = $this->user->subscriptions()->onGracePeriod()->get();

    expect($graceSubscriptions)->toHaveCount(1);
    expect($graceSubscriptions->first()->type)->toBe('grace');
});

it('can get ended subscriptions', function (): void {
    $this->user->subscriptions()->create([
        'type' => 'active',
        'chip_id' => 'test-sub-1',
        'chip_status' => Subscription::STATUS_ACTIVE,
        'chip_price' => 'price_monthly',
    ]);

    $this->user->subscriptions()->create([
        'type' => 'ended',
        'chip_id' => 'test-sub-2',
        'chip_status' => Subscription::STATUS_CANCELED,
        'chip_price' => 'price_monthly',
        'ends_at' => Carbon::now()->subDay(),
    ]);

    $endedSubscriptions = $this->user->subscriptions()->ended()->get();

    expect($endedSubscriptions)->toHaveCount(1);
    expect($endedSubscriptions->first()->type)->toBe('ended');
});
