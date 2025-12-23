<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\CashierChip\Unit;

use AIArmada\CashierChip\Events\SubscriptionCanceled;
use AIArmada\CashierChip\Listeners\HandleBillingCancelled;
use AIArmada\CashierChip\Subscription;
use AIArmada\Chip\Data\BillingTemplateClientData;
use AIArmada\Chip\Events\BillingCancelled;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Support\Facades\Event;

class HandleBillingCancelledTest extends CashierChipTestCase
{
    public function test_handle_billing_cancelled_marks_subscription_canceled(): void
    {
        Event::fake([SubscriptionCanceled::class]);

        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'user_id' => $user->id,
            'type' => 'default',
            'chip_status' => Subscription::STATUS_ACTIVE,
            'recurring_token' => 'tok_123',
        ]);

        $billingTemplateClientData = BillingTemplateClientData::from([
            'id' => 'btc_123',
            'client_id' => 'cli_123',
            'billing_template_id' => 'bt_123',
            'recurring_token' => 'tok_123',
            'status' => 'cancelled',
        ]);

        $event = new BillingCancelled($billingTemplateClientData, []);

        $listener = new HandleBillingCancelled;
        OwnerContext::withOwner($user, fn (): null => tap(null, fn () => $listener->handle($event)));

        $subscription->refresh();
        $this->assertEquals('canceled', $subscription->chip_status);
        $this->assertNotNull($subscription->ends_at);

        Event::assertDispatched(SubscriptionCanceled::class, function ($e) use ($subscription) {
            return $e->subscription->id === $subscription->id;
        });
    }

    public function test_handle_billing_cancelled_fails_closed_without_owner_context(): void
    {
        Event::fake([SubscriptionCanceled::class]);

        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'user_id' => $user->id,
            'type' => 'default',
            'chip_status' => Subscription::STATUS_ACTIVE,
            'recurring_token' => 'tok_123',
        ]);

        $billingTemplateClientData = BillingTemplateClientData::from([
            'id' => 'btc_123',
            'client_id' => 'cli_123',
            'billing_template_id' => 'bt_123',
            'recurring_token' => 'tok_123',
            'status' => 'cancelled',
        ]);

        $event = new BillingCancelled($billingTemplateClientData, []);

        $listener = new HandleBillingCancelled;
        OwnerContext::withOwner(null, fn (): null => tap(null, fn () => $listener->handle($event)));

        $subscription->refresh();
        $this->assertEquals(Subscription::STATUS_ACTIVE, $subscription->chip_status);
        $this->assertNull($subscription->ends_at);

        Event::assertNotDispatched(SubscriptionCanceled::class);
    }

    public function test_handle_billing_cancelled_returns_early_without_client_id(): void
    {
        Event::fake([SubscriptionCanceled::class]);

        $billingTemplateClientData = BillingTemplateClientData::from([
            'id' => 'btc_123',
            'client_id' => '',
            'billing_template_id' => 'bt_123',
            'status' => 'cancelled',
        ]);

        $event = new BillingCancelled($billingTemplateClientData, []);

        $listener = new HandleBillingCancelled;
        $listener->handle($event);

        Event::assertNotDispatched(SubscriptionCanceled::class);
    }

    public function test_handle_billing_cancelled_returns_early_without_billable(): void
    {
        Event::fake([SubscriptionCanceled::class]);

        $billingTemplateClientData = BillingTemplateClientData::from([
            'id' => 'btc_123',
            'client_id' => 'cli_nonexistent',
            'billing_template_id' => 'bt_123',
            'status' => 'cancelled',
        ]);

        $event = new BillingCancelled($billingTemplateClientData, []);

        $listener = new HandleBillingCancelled;
        $listener->handle($event);

        Event::assertNotDispatched(SubscriptionCanceled::class);
    }

    public function test_handle_billing_cancelled_does_nothing_without_subscription(): void
    {
        Event::fake([SubscriptionCanceled::class]);

        $user = $this->createUser(['chip_id' => 'cli_123']);

        $billingTemplateClientData = BillingTemplateClientData::from([
            'id' => 'btc_123',
            'client_id' => 'cli_123',
            'billing_template_id' => 'bt_nonexistent',
            'status' => 'cancelled',
        ]);

        $event = new BillingCancelled($billingTemplateClientData, []);

        $listener = new HandleBillingCancelled;
        $listener->handle($event);

        Event::assertNotDispatched(SubscriptionCanceled::class);
    }
}
