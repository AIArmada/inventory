<?php

declare(strict_types=1);

use AIArmada\CashierChip\Subscription;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;

uses(CashierChipTestCase::class);

beforeEach(function (): void {
    $this->user = $this->createUser();

    $this->subscription = $this->user->subscriptions()->create([
        'type' => 'standard',
        'chip_id' => 'test-sub-id',
        'chip_status' => Subscription::STATUS_ACTIVE,
        'chip_price' => 'price_monthly',
        'quantity' => 1,
    ]);

    $this->item = $this->subscription->items()->create([
        'chip_id' => 'item-1',
        'chip_product' => 'prod_123',
        'chip_price' => 'price_monthly',
        'quantity' => 1,
        'unit_amount' => 9900,
    ]);
});

it('belongs to subscription', function (): void {
    expect($this->item->subscription)->toBeInstanceOf(Subscription::class);
    expect($this->item->subscription->id)->toBe($this->subscription->id);
});

it('can increment quantity', function (): void {
    $this->item->incrementQuantity();

    expect($this->item->quantity)->toBe(2);
});

it('can increment quantity by specific amount', function (): void {
    $this->item->incrementQuantity(5);

    expect($this->item->quantity)->toBe(6);
});

it('can decrement quantity', function (): void {
    $this->item->update(['quantity' => 5]);

    $this->item->decrementQuantity();

    expect($this->item->quantity)->toBe(4);
});

it('can decrement quantity by specific amount', function (): void {
    $this->item->update(['quantity' => 10]);

    $this->item->decrementQuantity(3);

    expect($this->item->quantity)->toBe(7);
});

it('cannot decrement quantity below 1', function (): void {
    $this->item->decrementQuantity(10);

    expect($this->item->quantity)->toBe(1);
});

it('can update quantity', function (): void {
    $this->item->updateQuantity(10);

    expect($this->item->quantity)->toBe(10);
});

it('updates subscription quantity for single price', function (): void {
    $this->item->updateQuantity(5);

    $this->subscription->refresh();

    expect($this->subscription->quantity)->toBe(5);
});

it('can swap to new price', function (): void {
    $this->item->swap('price_yearly');

    expect($this->item->chip_price)->toBe('price_yearly');
});

it('can swap to new price with options', function (): void {
    $this->item->swap('price_yearly', [
        'product' => 'prod_456',
        'unit_amount' => 99900,
    ]);

    expect($this->item->chip_price)->toBe('price_yearly');
    expect($this->item->chip_product)->toBe('prod_456');
    expect($this->item->unit_amount)->toBe(99900);
});

it('updates subscription price for single price swap', function (): void {
    $this->item->swap('price_yearly');

    $this->subscription->refresh();

    expect($this->subscription->chip_price)->toBe('price_yearly');
});

it('can check if on trial', function (): void {
    expect($this->item->onTrial())->toBeFalse();

    $this->subscription->update([
        'chip_status' => Subscription::STATUS_TRIALING,
        'trial_ends_at' => now()->addDays(14),
    ]);

    // Refresh the item's subscription relationship
    $this->item->refresh();

    expect($this->item->onTrial())->toBeTrue();
});

it('can check if on grace period', function (): void {
    expect($this->item->onGracePeriod())->toBeFalse();

    $this->subscription->update([
        'ends_at' => now()->addDays(7),
    ]);

    // Refresh the item's subscription relationship
    $this->item->refresh();

    expect($this->item->onGracePeriod())->toBeTrue();
});

it('can calculate total amount', function (): void {
    $this->item->update([
        'unit_amount' => 5000,
        'quantity' => 3,
    ]);

    expect($this->item->totalAmount())->toBe(15000);
});

it('has correct casts', function (): void {
    expect($this->item->quantity)->toBeInt();
    expect($this->item->unit_amount)->toBeInt();
});

it('guards against incomplete subscription updates', function (): void {
    $this->subscription->update([
        'chip_status' => Subscription::STATUS_INCOMPLETE,
    ]);

    $this->item->updateQuantity(5);
})->throws(AIArmada\CashierChip\Exceptions\SubscriptionUpdateFailure::class);
