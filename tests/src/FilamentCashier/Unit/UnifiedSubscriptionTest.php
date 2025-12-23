<?php

declare(strict_types=1);

use AIArmada\FilamentCashier\Support\SubscriptionStatus;
use AIArmada\FilamentCashier\Support\UnifiedSubscription;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

it('can format amount in USD correctly', function (): void {
    $model = Mockery::mock(Model::class);
    $model->shouldReceive('getKey')->andReturn('123');

    $subscription = new UnifiedSubscription(
        id: 'sub_123',
        gateway: 'stripe',
        userId: 'user_456',
        type: 'default',
        planId: 'price_789',
        amount: 2999,
        currency: 'USD',
        quantity: 1,
        status: SubscriptionStatus::Active,
        trialEndsAt: null,
        endsAt: null,
        nextBillingDate: null,
        createdAt: Carbon::now(),
        original: $model
    );

    $formatted = $subscription->formattedAmount();

    expect($formatted)->toBe('$29.99');
});

it('can format amount in MYR correctly', function (): void {
    $model = Mockery::mock(Model::class);
    $model->shouldReceive('getKey')->andReturn('456');

    $subscription = new UnifiedSubscription(
        id: 'sub_456',
        gateway: 'chip',
        userId: 'user_456',
        type: 'default',
        planId: 'plan_789',
        amount: 5000,
        currency: 'MYR',
        quantity: 1,
        status: SubscriptionStatus::Active,
        trialEndsAt: null,
        endsAt: null,
        nextBillingDate: null,
        createdAt: Carbon::now(),
        original: $model
    );

    $formatted = $subscription->formattedAmount();

    expect($formatted)->toBe('RM50.00');
});

it('returns gateway config', function (): void {
    $model = Mockery::mock(Model::class);

    $subscription = new UnifiedSubscription(
        id: 'sub_123',
        gateway: 'stripe',
        userId: 'user_456',
        type: 'default',
        planId: 'price_789',
        amount: 2999,
        currency: 'USD',
        quantity: 1,
        status: SubscriptionStatus::Active,
        trialEndsAt: null,
        endsAt: null,
        nextBillingDate: null,
        createdAt: Carbon::now(),
        original: $model
    );

    $config = $subscription->gatewayConfig();

    expect($config)->toBeArray()
        ->and($config)->toHaveKeys(['label', 'color', 'icon']);
});

it('returns billing cycle as monthly by default', function (): void {
    $model = Mockery::mock(Model::class);

    $subscription = new UnifiedSubscription(
        id: 'sub_123',
        gateway: 'stripe',
        userId: 'user_456',
        type: 'default',
        planId: 'price_monthly',
        amount: 2999,
        currency: 'USD',
        quantity: 1,
        status: SubscriptionStatus::Active,
        trialEndsAt: null,
        endsAt: null,
        nextBillingDate: null,
        createdAt: Carbon::now(),
        original: $model
    );

    $cycle = $subscription->billingCycle();

    expect($cycle)->toBeString();
});

it('identifies yearly billing cycle from plan name', function (): void {
    $model = Mockery::mock(Model::class);

    $subscription = new UnifiedSubscription(
        id: 'sub_123',
        gateway: 'stripe',
        userId: 'user_456',
        type: 'default',
        planId: 'price_yearly_plan',
        amount: 29900,
        currency: 'USD',
        quantity: 1,
        status: SubscriptionStatus::Active,
        trialEndsAt: null,
        endsAt: null,
        nextBillingDate: null,
        createdAt: Carbon::now(),
        original: $model
    );

    $cycle = $subscription->billingCycle();

    expect($cycle)->toBeString();
});

it('detects subscription needs attention when past due', function (): void {
    $model = Mockery::mock(Model::class);

    $subscription = new UnifiedSubscription(
        id: 'sub_123',
        gateway: 'stripe',
        userId: 'user_456',
        type: 'default',
        planId: 'price_789',
        amount: 2999,
        currency: 'USD',
        quantity: 1,
        status: SubscriptionStatus::PastDue,
        trialEndsAt: null,
        endsAt: null,
        nextBillingDate: null,
        createdAt: Carbon::now(),
        original: $model
    );

    expect($subscription->needsAttention())->toBeTrue();
});

it('detects subscription does not need attention when active', function (): void {
    $model = Mockery::mock(Model::class);

    $subscription = new UnifiedSubscription(
        id: 'sub_123',
        gateway: 'stripe',
        userId: 'user_456',
        type: 'default',
        planId: 'price_789',
        amount: 2999,
        currency: 'USD',
        quantity: 1,
        status: SubscriptionStatus::Active,
        trialEndsAt: null,
        endsAt: null,
        nextBillingDate: null,
        createdAt: Carbon::now(),
        original: $model
    );

    expect($subscription->needsAttention())->toBeFalse();
});

it('calculates stripe amount from loaded items without calling items()', function (): void {
    $stripeSubscription = new class extends Model
    {
        protected $guarded = [];

        public $timestamps = false;

        public function items(): never
        {
            throw new \RuntimeException('items() should not be called when items relation is preloaded.');
        }
    };

    $stripeSubscription->forceFill([
        'id' => 1,
        'user_id' => 1,
        'type' => 'default',
        'stripe_price' => 'price_monthly',
        'quantity' => 1,
        'trial_ends_at' => null,
        'ends_at' => null,
        'created_at' => Carbon::now(),
    ]);

    $stripeSubscription->setRelation('items', collect([
        (object) [
            'stripe_price' => 'price_monthly',
            'quantity' => 2,
            'unit_amount' => 1500,
        ],
    ]));

    $unified = UnifiedSubscription::fromStripe($stripeSubscription);

    expect($unified->amount)->toBe(3000);
});

it('calculates chip amount from loaded items without calling items()', function (): void {
    $chipSubscription = new class extends Model
    {
        protected $guarded = [];

        public $timestamps = false;

        public function items(): never
        {
            throw new \RuntimeException('items() should not be called when items relation is preloaded.');
        }
    };

    $chipSubscription->forceFill([
        'id' => 'sub_chip',
        'user_id' => 1,
        'type' => 'default',
        'plan_id' => 'plan_monthly',
        'quantity' => 1,
        'trial_ends_at' => null,
        'ends_at' => null,
        'next_billing_at' => null,
        'created_at' => Carbon::now(),
        'status' => 'active',
    ]);

    $chipSubscription->setRelation('items', collect([
        (object) [
            'quantity' => 3,
            'unit_amount' => 1200,
        ],
        (object) [
            'quantity' => 1,
            'unit_amount' => 500,
        ],
    ]));

    $unified = UnifiedSubscription::fromChip($chipSubscription);

    expect($unified->amount)->toBe(4100);
});
