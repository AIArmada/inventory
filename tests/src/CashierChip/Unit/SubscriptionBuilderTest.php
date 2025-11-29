<?php

declare(strict_types=1);

use AIArmada\CashierChip\SubscriptionBuilder;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use Carbon\Carbon;

uses(CashierChipTestCase::class);

beforeEach(function (): void {
    $this->user = $this->createUser([
        'chip_id' => 'test-chip-client-id',
    ]);
});

it('can create subscription builder', function (): void {
    $builder = new SubscriptionBuilder($this->user, 'standard', 'price_monthly');

    expect($builder)->toBeInstanceOf(SubscriptionBuilder::class);
    expect($builder->getType())->toBe('standard');
});

it('can add price to subscription', function (): void {
    $builder = new SubscriptionBuilder($this->user, 'standard');

    $builder->price('price_monthly', 1);

    expect($builder->getItems())->toHaveCount(1);
    expect($builder->getItems()['price_monthly']['price'])->toBe('price_monthly');
});

it('can add multiple prices', function (): void {
    $builder = new SubscriptionBuilder($this->user, 'standard');

    $builder->price('price_basic', 1)
        ->price('price_addon', 2);

    expect($builder->getItems())->toHaveCount(2);
});

it('can set quantity', function (): void {
    $builder = new SubscriptionBuilder($this->user, 'standard', 'price_monthly');

    $builder->quantity(5);

    expect($builder->getItems()['price_monthly']['quantity'])->toBe(5);
});

it('can set trial days', function (): void {
    $builder = new SubscriptionBuilder($this->user, 'standard', 'price_monthly');

    $builder->trialDays(14);

    expect($builder->getTrialEnd())->not->toBeNull();
    // The diff can be 13 or 14 depending on timing, so we check the range
    $diffDays = (int) abs($builder->getTrialEnd()->diffInDays(Carbon::now()));
    expect($diffDays)->toBeGreaterThanOrEqual(13);
    expect($diffDays)->toBeLessThanOrEqual(14);
});

it('can set trial until date', function (): void {
    $trialEnd = Carbon::now()->addMonth();

    $builder = new SubscriptionBuilder($this->user, 'standard', 'price_monthly');
    $builder->trialUntil($trialEnd);

    expect($builder->getTrialEnd()->toDateString())->toBe($trialEnd->toDateString());
});

it('can skip trial', function (): void {
    $builder = new SubscriptionBuilder($this->user, 'standard', 'price_monthly');

    $builder->trialDays(14)->skipTrial();

    // Verify skipTrial is set in the builder
    expect($builder->getSkipTrial())->toBeTrue();
});

it('can set monthly billing', function (): void {
    $builder = new SubscriptionBuilder($this->user, 'standard', 'price_monthly');

    $result = $builder->monthly();

    // Verify the builder is returned for chaining
    expect($result)->toBeInstanceOf(SubscriptionBuilder::class);
    expect($result->getBillingInterval())->toBe('month');
    expect($result->getBillingIntervalCount())->toBe(1);
});

it('can set yearly billing', function (): void {
    $builder = new SubscriptionBuilder($this->user, 'standard', 'price_yearly');

    $result = $builder->yearly();

    expect($result)->toBeInstanceOf(SubscriptionBuilder::class);
    expect($result->getBillingInterval())->toBe('year');
    expect($result->getBillingIntervalCount())->toBe(1);
});

it('can set weekly billing', function (): void {
    $builder = new SubscriptionBuilder($this->user, 'standard', 'price_weekly');

    $result = $builder->weekly();

    expect($result)->toBeInstanceOf(SubscriptionBuilder::class);
    expect($result->getBillingInterval())->toBe('week');
    expect($result->getBillingIntervalCount())->toBe(1);
});

it('can set custom billing interval', function (): void {
    $builder = new SubscriptionBuilder($this->user, 'standard', 'price_biweekly');

    $result = $builder->billingInterval('week', 2);

    expect($result)->toBeInstanceOf(SubscriptionBuilder::class);
    expect($result->getBillingInterval())->toBe('week');
    expect($result->getBillingIntervalCount())->toBe(2);
});

it('can anchor billing cycle', function (): void {
    $anchor = Carbon::now()->addDays(15);

    $builder = new SubscriptionBuilder($this->user, 'standard', 'price_monthly');
    $result = $builder->anchorBillingCycleOn($anchor);

    expect($result)->toBeInstanceOf(SubscriptionBuilder::class);
    expect($result->getBillingCycleAnchor())->not->toBeNull();
    expect($result->getBillingCycleAnchor()->toDateString())->toBe($anchor->toDateString());
});

it('can add metadata', function (): void {
    $builder = new SubscriptionBuilder($this->user, 'standard', 'price_monthly');

    $result = $builder->withMetadata(['plan_name' => 'Premium']);

    expect($result)->toBeInstanceOf(SubscriptionBuilder::class);
});

it('can create subscription without payment using add', function (): void {
    $builder = new SubscriptionBuilder($this->user, 'standard', 'price_monthly');

    $subscription = $builder->add();

    expect($subscription)->toBeInstanceOf(AIArmada\CashierChip\Subscription::class);
    expect($subscription->type)->toBe('standard');
    expect($subscription->chip_price)->toBe('price_monthly');
});

it('can create subscription with recurring token', function (): void {
    $builder = new SubscriptionBuilder($this->user, 'standard', 'price_monthly');

    // Create subscription with a recurring token
    $subscription = $builder->create('test-recurring-token');

    expect($subscription)->toBeInstanceOf(AIArmada\CashierChip\Subscription::class);
    expect($subscription->recurring_token)->toBe('test-recurring-token');
});

it('creates subscription items for each price', function (): void {
    $builder = new SubscriptionBuilder($this->user, 'standard');

    $builder->price('price_basic', 1)
        ->price('price_addon', 2);

    $subscription = $builder->add();

    expect($subscription->items)->toHaveCount(2);

    // Get items as a keyed collection by chip_price
    $items = $subscription->items->keyBy('chip_price');

    expect($items->has('price_basic'))->toBeTrue();
    expect($items->has('price_addon'))->toBeTrue();
    expect($items->get('price_basic')->quantity)->toBe(1);
    expect($items->get('price_addon')->quantity)->toBe(2);
});

it('requires price for quantity when multiple prices', function (): void {
    $builder = new SubscriptionBuilder($this->user, 'standard');

    $builder->price('price_basic')
        ->price('price_addon');

    $builder->quantity(5);
})->throws(InvalidArgumentException::class);

it('can use conditionable trait', function (): void {
    $builder = new SubscriptionBuilder($this->user, 'standard', 'price_monthly');

    $result = $builder->when(true, function ($builder) {
        return $builder->trialDays(14);
    });

    expect($result->getTrialEnd())->not->toBeNull();
});
