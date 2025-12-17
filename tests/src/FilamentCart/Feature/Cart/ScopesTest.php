<?php

declare(strict_types=1);

use AIArmada\FilamentCart\Models\Cart;

it('filters active carts with scopeNotEmpty', function (): void {
    $empty = Cart::factory()->empty()->create();
    $filled = Cart::factory()->create([
        'items_count' => 2,
        'quantity' => 3,
    ]);

    $results = Cart::query()->notEmpty()->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($filled->id);
    expect($results->contains($empty))->toBeFalse();
});

it('limits carts by recency', function (): void {
    $recent = Cart::factory()->create(['updated_at' => now()]);
    Cart::factory()->create(['updated_at' => now()->subDays(10)]);

    $results = Cart::query()->recent(7)->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($recent->id);
});

it('filters carts with savings', function (): void {
    $noSavings = Cart::factory()->create(['savings' => 0]);
    $withSavings = Cart::factory()->create(['savings' => 100]);

    $results = Cart::query()->withSavings()->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($withSavings->id);
    expect($results->contains($noSavings))->toBeFalse();
});

it('filters abandoned and recovered carts', function (): void {
    $abandoned = Cart::factory()->create([
        'checkout_abandoned_at' => now()->subHour(),
        'recovered_at' => null,
    ]);
    $recovered = Cart::factory()->create([
        'checkout_abandoned_at' => now()->subHour(),
        'recovered_at' => now(),
    ]);

    expect(Cart::query()->abandoned()->pluck('id')->all())->toContain($abandoned->id);
    expect(Cart::query()->abandoned()->pluck('id')->all())->not->toContain($recovered->id);

    expect(Cart::query()->recovered()->pluck('id')->all())->toContain($recovered->id);
    expect(Cart::query()->recovered()->pluck('id')->all())->not->toContain($abandoned->id);
});

it('filters carts in checkout', function (): void {
    $checkout = Cart::factory()->create([
        'checkout_started_at' => now()->subMinutes(5),
        'checkout_abandoned_at' => null,
    ]);
    $abandoned = Cart::factory()->create([
        'checkout_started_at' => now()->subHours(2),
        'checkout_abandoned_at' => now()->subHour(),
    ]);

    $results = Cart::query()->inCheckout()->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($checkout->id);
    expect($results->contains($abandoned))->toBeFalse();
});

it('filters collaborative carts and fraud risk carts', function (): void {
    $collaborative = Cart::factory()->create(['is_collaborative' => true]);
    Cart::factory()->create(['is_collaborative' => false]);

    expect(Cart::query()->collaborative()->pluck('id')->all())->toContain($collaborative->id);

    $highFraud = Cart::factory()->create(['fraud_risk_level' => 'high']);
    Cart::factory()->create(['fraud_risk_level' => 'low']);

    expect(Cart::query()->highFraudRisk()->pluck('id')->all())->toContain($highFraud->id);
});

it('filters carts needing recovery', function (): void {
    $needsRecovery = Cart::factory()->create([
        'checkout_abandoned_at' => now()->subHour(),
        'recovered_at' => null,
        'recovery_attempts' => 1,
    ]);

    Cart::factory()->create([
        'checkout_abandoned_at' => now()->subHour(),
        'recovered_at' => null,
        'recovery_attempts' => 3,
    ]);

    $results = Cart::query()->needsRecovery()->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($needsRecovery->id);
});
