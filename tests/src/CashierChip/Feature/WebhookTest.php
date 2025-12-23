<?php

declare(strict_types=1);

use AIArmada\CashierChip\Events\PaymentFailed;
use AIArmada\CashierChip\Events\PaymentSucceeded;
use AIArmada\CashierChip\Events\WebhookHandled;
use AIArmada\CashierChip\Events\WebhookReceived;
use AIArmada\CashierChip\Http\Controllers\WebhookController;
use AIArmada\CashierChip\Subscription;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;

uses(CashierChipTestCase::class);

beforeEach(function (): void {
    Event::fake([
        WebhookReceived::class,
        WebhookHandled::class,
        PaymentSucceeded::class,
        PaymentFailed::class,
    ]);

    $this->user = $this->createUser([
        'chip_id' => 'test-client-id',
    ]);

    // Manually register the webhook route for testing
    Route::post('/chip/webhook', WebhookController::class)
        ->name('cashier-chip.webhook');
});

it('handles webhook received event', function (): void {
    $payload = [
        'event_type' => 'purchase.paid',
        'purchase' => [
            'id' => 'test-purchase-id',
            'status' => 'paid',
            'client' => ['id' => 'test-client-id'],
        ],
    ];

    $response = $this->postJson('/chip/webhook', $payload);

    $response->assertStatus(200);

    Event::assertDispatched(WebhookReceived::class, function ($event) use ($payload) {
        return $event->payload['event_type'] === $payload['event_type'];
    });
});

it('handles payment success webhook', function (): void {
    $payload = [
        'event_type' => 'purchase.paid',
        'purchase' => [
            'id' => 'test-purchase-id',
            'status' => 'paid',
            'client' => ['id' => 'test-client-id'],
            'total' => 100.00,
            'currency' => 'MYR',
        ],
    ];

    $response = $this->postJson('/chip/webhook', $payload);

    $response->assertStatus(200);

    Event::assertDispatched(PaymentSucceeded::class, function ($event) {
        return $event->billable->id === $this->user->id;
    });

    Event::assertDispatched(WebhookHandled::class);
});

it('handles payment failed webhook', function (): void {
    $payload = [
        'event_type' => 'purchase.payment_failure',
        'purchase' => [
            'id' => 'test-purchase-id',
            'status' => 'error',
            'client' => ['id' => 'test-client-id'],
        ],
    ];

    $response = $this->postJson('/chip/webhook', $payload);

    $response->assertStatus(200);

    Event::assertDispatched(PaymentFailed::class, function ($event) {
        return $event->billable->id === $this->user->id;
    });
});

it('handles purchase completed webhook', function (): void {
    $payload = [
        'event_type' => 'purchase.paid',
        'purchase' => [
            'id' => 'test-purchase-id',
            'status' => 'paid',
            'client' => ['id' => 'test-client-id'],
        ],
    ];

    $response = $this->postJson('/chip/webhook', $payload);

    $response->assertStatus(200);

    Event::assertDispatched(PaymentSucceeded::class);
});

it('stores recurring token from webhook when no default payment method', function (): void {
    Event::fake([WebhookReceived::class, WebhookHandled::class, PaymentSucceeded::class]);

    // User starts without a default payment method
    expect($this->user->default_pm_id)->toBeNull();

    $payload = [
        'event_type' => 'purchase.paid',
        'purchase' => [
            'id' => 'test-purchase-id',
            'status' => 'paid',
            'client' => ['id' => 'test-client-id'],
            'recurring_token' => 'new-recurring-token',
            'card' => [
                'brand' => 'Visa',
                'last_4' => '4242',
            ],
        ],
    ];

    $response = $this->postJson('/chip/webhook', $payload);

    $response->assertStatus(200);

    $this->user->refresh();

    expect($this->user->default_pm_id)->toBe('new-recurring-token');
    expect($this->user->pm_type)->toBe('Visa');
    expect($this->user->pm_last_four)->toBe('4242');
});

it('updates subscription on payment success', function (): void {
    Event::fake([WebhookReceived::class, WebhookHandled::class, PaymentSucceeded::class]);

    // Create a subscription
    $this->user->subscriptions()->create([
        'type' => 'standard',
        'chip_id' => 'test-sub-id',
        'chip_status' => Subscription::STATUS_PAST_DUE,
        'chip_price' => 'price_monthly',
        'billing_interval' => 'month',
        'billing_interval_count' => 1,
    ]);

    $payload = [
        'event_type' => 'purchase.paid',
        'purchase' => [
            'id' => 'test-purchase-id',
            'status' => 'paid',
            'client' => ['id' => 'test-client-id'],
            'metadata' => [
                'subscription_type' => 'standard',
            ],
        ],
    ];

    $response = $this->postJson('/chip/webhook', $payload);

    $response->assertStatus(200);

    $subscription = $this->user->subscription('standard');

    expect($subscription->chip_status)->toBe(Subscription::STATUS_ACTIVE);
    expect($subscription->next_billing_at)->not->toBeNull();
});

it('updates subscription to past due on payment failure', function (): void {
    Event::fake([WebhookReceived::class, WebhookHandled::class, PaymentFailed::class]);

    // Create a subscription
    $this->user->subscriptions()->create([
        'type' => 'standard',
        'chip_id' => 'test-sub-id',
        'chip_status' => Subscription::STATUS_ACTIVE,
        'chip_price' => 'price_monthly',
    ]);

    $payload = [
        'event_type' => 'purchase.payment_failure',
        'purchase' => [
            'id' => 'test-purchase-id',
            'status' => 'error',
            'client' => ['id' => 'test-client-id'],
            'metadata' => [
                'subscription_type' => 'standard',
            ],
        ],
    ];

    $response = $this->postJson('/chip/webhook', $payload);

    $response->assertStatus(200);

    $subscription = $this->user->subscription('standard');

    expect($subscription->chip_status)->toBe(Subscription::STATUS_PAST_DUE);
});

it('handles unknown webhook events gracefully', function (): void {
    $payload = [
        'event_type' => 'unknown.event',
        'data' => ['some' => 'data'],
    ];

    $response = $this->postJson('/chip/webhook', $payload);

    $response->assertStatus(200);
    $response->assertSee('Webhook received');
});

it('handles missing client id gracefully', function (): void {
    $payload = [
        'event_type' => 'purchase.paid',
        'purchase' => [
            'id' => 'test-purchase-id',
            'status' => 'paid',
            // No client id
        ],
    ];

    $response = $this->postJson('/chip/webhook', $payload);

    $response->assertStatus(200);

    Event::assertNotDispatched(PaymentSucceeded::class);
});

it('handles non-existent billable gracefully', function (): void {
    $payload = [
        'event_type' => 'purchase.paid',
        'purchase' => [
            'id' => 'test-purchase-id',
            'status' => 'paid',
            'client' => ['id' => 'non-existent-client-id'],
        ],
    ];

    $response = $this->postJson('/chip/webhook', $payload);

    $response->assertStatus(200);

    Event::assertNotDispatched(PaymentSucceeded::class);
});

it('requires owner resolution via brand_id mapping when owner scoping is enabled', function (): void {
    config()->set('cashier-chip.features.owner.enabled', true);
    config()->set('cashier-chip.features.owner.include_global', false);
    config()->set('cashier-chip.features.owner.auto_assign_on_create', true);

    config()->set('chip.owner.webhook_brand_id_map', [
        'test_brand_id' => [
            'owner_type' => $this->user->getMorphClass(),
            'owner_id' => (string) $this->user->getKey(),
        ],
    ]);

    OwnerContext::withOwner($this->user, function (): void {
        $this->user->subscriptions()->create([
            'type' => 'standard',
            'chip_id' => 'test-sub-id',
            'chip_status' => Subscription::STATUS_PAST_DUE,
            'chip_price' => 'price_monthly',
            'billing_interval' => 'month',
            'billing_interval_count' => 1,
        ]);
    });

    // Simulate a webhook request arriving without ambient owner context.
    OwnerContext::override(null);

    $payload = [
        'brand_id' => 'test_brand_id',
        'event_type' => 'purchase.paid',
        'purchase' => [
            'id' => 'test-purchase-id',
            'status' => 'paid',
            'client' => ['id' => 'test-client-id'],
            'metadata' => [
                'subscription_type' => 'standard',
            ],
        ],
    ];

    $this->postJson('/chip/webhook', $payload)
        ->assertStatus(200);

    $subscription = Subscription::query()
        ->withoutOwnerScope()
        ->where('user_id', $this->user->getKey())
        ->where('type', 'standard')
        ->first();

    expect($subscription)->not->toBeNull();
    expect($subscription?->chip_status)->toBe(Subscription::STATUS_ACTIVE);
});

it('fails closed when owner scoping is enabled but brand_id has no mapping', function (): void {
    config()->set('cashier-chip.features.owner.enabled', true);
    config()->set('chip.owner.webhook_brand_id_map', []);

    OwnerContext::override(null);

    $payload = [
        'brand_id' => 'missing-brand',
        'event_type' => 'purchase.paid',
        'purchase' => [
            'id' => 'test-purchase-id',
            'status' => 'paid',
            'client' => ['id' => 'test-client-id'],
        ],
    ];

    $this->postJson('/chip/webhook', $payload)
        ->assertStatus(500);
});
