<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\CashierChip\Integration;

use AIArmada\CashierChip\Checkout;
use AIArmada\CashierChip\Subscription;
use AIArmada\CashierChip\SubscriptionBuilder;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use Carbon\Carbon;

class SubscriptionBuilderIntegrationTest extends CashierChipTestCase
{
    public function test_can_create_subscription(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_integration_123']);

        $subscription = $user->newSubscription('default', 'price_monthly_100')
            ->create();

        $this->assertInstanceOf(Subscription::class, $subscription);
        $this->assertEquals('default', $subscription->type);
        $this->assertEquals('price_monthly_100', $subscription->chip_price);
        $this->assertEquals(Subscription::STATUS_ACTIVE, $subscription->chip_status);
    }

    public function test_can_create_subscription_with_trial(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_trial_123']);

        $subscription = $user->newSubscription('default', 'price_monthly_100')
            ->trialDays(14)
            ->create();

        $this->assertInstanceOf(Subscription::class, $subscription);
        $this->assertEquals(Subscription::STATUS_TRIALING, $subscription->chip_status);
        $this->assertTrue($subscription->onTrial());
        $this->assertNotNull($subscription->trial_ends_at);
    }

    public function test_can_create_subscription_skip_trial(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_skip_trial_123']);

        $subscription = $user->newSubscription('default', 'price_monthly_100')
            ->trialDays(14)
            ->skipTrial()
            ->create();

        $this->assertEquals(Subscription::STATUS_ACTIVE, $subscription->chip_status);
        $this->assertNull($subscription->trial_ends_at);
    }

    public function test_can_create_subscription_with_trial_until(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_trial_until_123']);

        $trialEnd = Carbon::now()->addDays(30);
        $subscription = $user->newSubscription('default', 'price_monthly_100')
            ->trialUntil($trialEnd)
            ->create();

        $this->assertTrue($subscription->onTrial());
        $this->assertEquals($trialEnd->toDateTimeString(), $subscription->trial_ends_at->toDateTimeString());
    }

    public function test_can_create_subscription_with_multiple_prices(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_multi_price_123']);

        $subscription = $user->newSubscription('default', ['price_monthly_100', 'price_addon_50'])
            ->create();

        $this->assertNull($subscription->chip_price);
        $this->assertTrue($subscription->hasMultiplePrices());
        $this->assertEquals(2, $subscription->items->count());
    }

    public function test_can_create_subscription_with_quantity(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_qty_123']);

        $subscription = $user->newSubscription('default', 'price_per_seat')
            ->quantity(5)
            ->create();

        $this->assertEquals(5, $subscription->quantity);
    }

    public function test_subscription_builder_quantity_clamps_to_minimum_one(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_qty_clamp_123']);

        $subscription = $user->newSubscription('default', 'price_per_seat')
            ->quantity(0)
            ->create();

        $this->assertEquals(1, $subscription->quantity);
    }

    public function test_can_create_subscription_with_billing_interval(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_interval_123']);

        $subscription = $user->newSubscription('default', 'price_custom')
            ->billingInterval('year', 1)
            ->create();

        $this->assertEquals('year', $subscription->billing_interval);
        $this->assertEquals(1, $subscription->billing_interval_count);
    }

    public function test_can_create_monthly_subscription(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_monthly_123']);

        $subscription = $user->newSubscription('default', 'price_monthly_100')
            ->monthly()
            ->create();

        $this->assertEquals('month', $subscription->billing_interval);
    }

    public function test_can_create_yearly_subscription(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_yearly_123']);

        $subscription = $user->newSubscription('default', 'price_yearly_1000')
            ->yearly()
            ->create();

        $this->assertEquals('year', $subscription->billing_interval);
    }

    public function test_can_create_weekly_subscription(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_weekly_123']);

        $subscription = $user->newSubscription('default', 'price_weekly')
            ->weekly()
            ->create();

        $this->assertEquals('week', $subscription->billing_interval);
    }

    public function test_can_create_daily_subscription(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_daily_123']);

        $subscription = $user->newSubscription('default', 'price_daily')
            ->daily()
            ->create();

        $this->assertEquals('day', $subscription->billing_interval);
    }

    public function test_can_create_subscription_with_metadata(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_meta_123']);

        $subscription = $user->newSubscription('default', 'price_monthly_100')
            ->withMetadata(['campaign' => 'spring_sale'])
            ->create();

        $this->assertInstanceOf(Subscription::class, $subscription);
    }

    public function test_can_create_subscription_with_anchor(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_anchor_123']);

        $anchor = Carbon::now()->endOfMonth();
        $subscription = $user->newSubscription('default', 'price_monthly_100')
            ->anchorBillingCycleOn($anchor)
            ->create();

        $this->assertInstanceOf(Subscription::class, $subscription);
    }

    public function test_can_create_subscription_with_recurring_token(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_token_123']);

        $subscription = $user->newSubscription('default', 'price_monthly_100')
            ->create('tok_recurring_123');

        $this->assertEquals('tok_recurring_123', $subscription->recurring_token);
    }

    public function test_add_creates_subscription_without_charge(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_add_123']);

        $subscription = $user->newSubscription('default', 'price_monthly_100')
            ->add();

        $this->assertInstanceOf(Subscription::class, $subscription);
    }

    public function test_checkout_returns_checkout_instance(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_checkout_123']);

        $builder = new SubscriptionBuilder($user, 'default');
        $builder->price(['price' => 'price_monthly_100', 'quantity' => 1, 'unit_amount' => 10000]);

        $checkout = $builder->checkout();

        $this->assertInstanceOf(Checkout::class, $checkout);
    }

    public function test_checkout_with_trial(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_checkout_trial_123']);

        $builder = new SubscriptionBuilder($user, 'default');
        $builder->price(['price' => 'price_monthly_100', 'quantity' => 1, 'unit_amount' => 10000]);
        $builder->trialDays(7);

        $checkout = $builder->checkout();

        $this->assertInstanceOf(Checkout::class, $checkout);
    }

    public function test_checkout_with_recurring(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_checkout_recurring_123']);

        $builder = new SubscriptionBuilder($user, 'default');
        $builder->price(['price' => 'price_monthly_100', 'quantity' => 1, 'unit_amount' => 10000]);

        $checkout = $builder->checkout(['success_url' => 'https://example.com/success']);

        $this->assertInstanceOf(Checkout::class, $checkout);
    }

    public function test_fluent_builder(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_fluent_123']);

        $subscription = $user->newSubscription('premium', 'price_premium')
            ->monthly(1)
            ->trialDays(7)
            ->withMetadata(['source' => 'test'])
            ->create('tok_test_123');

        $this->assertInstanceOf(Subscription::class, $subscription);
        $this->assertEquals('premium', $subscription->type);
        $this->assertEquals('month', $subscription->billing_interval);
        $this->assertTrue($subscription->onTrial());
        $this->assertEquals('tok_test_123', $subscription->recurring_token);
    }

    public function test_creates_subscription_items(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_items_123']);

        $subscription = $user->newSubscription('default', 'price_monthly_100')
            ->create();

        $this->assertGreaterThan(0, $subscription->items->count());
        $this->assertEquals('price_monthly_100', $subscription->items->first()->chip_price);
    }

    public function test_subscription_has_next_billing_at(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_billing_123']);

        $subscription = $user->newSubscription('default', 'price_monthly_100')
            ->monthly()
            ->create();

        $this->assertNotNull($subscription->next_billing_at);
    }
}
