<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\CashierChip\Unit;

use AIArmada\CashierChip\Events\PaymentFailed;
use AIArmada\CashierChip\Events\SubscriptionRenewalFailed;
use AIArmada\CashierChip\Listeners\HandlePurchasePaymentFailure;
use AIArmada\CashierChip\Listeners\HandlePurchasePreauthorized;
use AIArmada\CashierChip\Listeners\HandleSubscriptionChargeFailure;
use AIArmada\CashierChip\Subscription;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Events\PurchasePaymentFailure;
use AIArmada\Chip\Events\PurchasePreauthorized;
use AIArmada\Chip\Events\PurchaseSubscriptionChargeFailure;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Support\Facades\Event;

class MoreListenersTest extends CashierChipTestCase
{
    public function test_handle_purchase_payment_failure_dispatches_event(): void
    {
        Event::fake([PaymentFailed::class]);

        $user = $this->createUser(['chip_id' => 'cli_123']);

        $purchaseData = [
            'id' => 'pur_123',
            'client_id' => 'cli_123',
            'status' => 'failed',
            'purchase' => ['total' => 1000, 'currency' => 'MYR'],
        ];

        $purchase = PurchaseData::from($purchaseData);
        $event = new PurchasePaymentFailure($purchase, $purchaseData);

        $listener = new HandlePurchasePaymentFailure;
        OwnerContext::withOwner($user, fn (): null => tap(null, fn () => $listener->handle($event)));

        Event::assertDispatched(PaymentFailed::class, function ($e) use ($user) {
            return $e->billable->is($user);
        });
    }

    public function test_handle_purchase_payment_failure_returns_early_without_client_id(): void
    {
        Event::fake([PaymentFailed::class]);

        $purchaseData = [
            'id' => 'pur_123',
            'status' => 'failed',
        ];

        $purchase = PurchaseData::from($purchaseData);
        $event = new PurchasePaymentFailure($purchase, $purchaseData);

        $listener = new HandlePurchasePaymentFailure;
        $listener->handle($event);

        Event::assertNotDispatched(PaymentFailed::class);
    }

    public function test_handle_purchase_payment_failure_marks_subscription_past_due(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->for($user, 'customer')->create([
            'type' => 'default',
            'chip_status' => Subscription::STATUS_ACTIVE,
        ]);

        $purchaseData = [
            'id' => 'pur_123',
            'client_id' => 'cli_123',
            'status' => 'failed',
            'metadata' => ['subscription_type' => 'default'],
        ];

        $purchase = PurchaseData::from($purchaseData);
        $event = new PurchasePaymentFailure($purchase, $purchaseData);

        $listener = new HandlePurchasePaymentFailure;
        OwnerContext::withOwner($user, fn (): null => tap(null, fn () => $listener->handle($event)));

        $this->assertEquals(Subscription::STATUS_PAST_DUE, $subscription->fresh()->chip_status);
    }

    public function test_handle_purchase_preauthorized_saves_recurring_token(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $purchaseData = [
            'id' => 'pur_123',
            'client_id' => 'cli_123',
            'status' => 'preauthorized',
            'recurring_token' => 'tok_preauth_123',
            'transaction_data' => ['payment_method' => 'card'],
            'card' => ['brand' => 'Visa', 'last_4' => '4242'],
        ];

        $purchase = PurchaseData::from($purchaseData);
        $event = new PurchasePreauthorized($purchase, $purchaseData);

        $listener = new HandlePurchasePreauthorized;
        OwnerContext::withOwner($user, fn (): null => tap(null, fn () => $listener->handle($event)));

        $user->refresh();
        // Recurring token should be saved
        $this->assertEquals('tok_preauth_123', $user->default_pm_id);
    }

    public function test_handle_purchase_preauthorized_returns_early_without_client_id(): void
    {
        $purchaseData = [
            'id' => 'pur_123',
            'status' => 'preauthorized',
            'recurring_token' => 'tok_preauth_123',
        ];

        $purchase = PurchaseData::from($purchaseData);
        $event = new PurchasePreauthorized($purchase, $purchaseData);

        $listener = new HandlePurchasePreauthorized;
        $listener->handle($event);

        // No exception means it returned early successfully
        $this->assertTrue(true);
    }

    public function test_handle_purchase_preauthorized_returns_early_without_recurring_token(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $purchaseData = [
            'id' => 'pur_123',
            'client_id' => 'cli_123',
            'status' => 'preauthorized',
        ];

        $purchase = PurchaseData::from($purchaseData);
        $event = new PurchasePreauthorized($purchase, $purchaseData);

        $listener = new HandlePurchasePreauthorized;
        OwnerContext::withOwner($user, fn (): null => tap(null, fn () => $listener->handle($event)));

        $user->refresh();
        $this->assertNull($user->default_pm_id);
    }

    public function test_handle_subscription_charge_failure_dispatches_event(): void
    {
        Event::fake([SubscriptionRenewalFailed::class]);

        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->for($user, 'customer')->create([
            'type' => 'default',
            'chip_status' => Subscription::STATUS_ACTIVE,
        ]);

        $purchaseData = [
            'id' => 'pur_123',
            'client_id' => 'cli_123',
            'status' => 'failed',
            'failure_reason' => 'Insufficient funds',
            'metadata' => ['subscription_type' => 'default'],
        ];

        $purchase = PurchaseData::from($purchaseData);
        $event = new PurchaseSubscriptionChargeFailure($purchase, $purchaseData);

        $listener = new HandleSubscriptionChargeFailure;
        OwnerContext::withOwner($user, fn (): null => tap(null, fn () => $listener->handle($event)));

        Event::assertDispatched(SubscriptionRenewalFailed::class, function ($e) use ($subscription) {
            return $e->subscription->id === $subscription->id;
        });
    }

    public function test_handle_subscription_charge_failure_marks_past_due(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->for($user, 'customer')->create([
            'type' => 'default',
            'chip_status' => Subscription::STATUS_ACTIVE,
        ]);

        $purchaseData = [
            'id' => 'pur_123',
            'client_id' => 'cli_123',
            'status' => 'failed',
            'metadata' => ['subscription_type' => 'default'],
        ];

        $purchase = PurchaseData::from($purchaseData);
        $event = new PurchaseSubscriptionChargeFailure($purchase, $purchaseData);

        $listener = new HandleSubscriptionChargeFailure;
        OwnerContext::withOwner($user, fn (): null => tap(null, fn () => $listener->handle($event)));

        $this->assertEquals(Subscription::STATUS_PAST_DUE, $subscription->fresh()->chip_status);
    }

    public function test_handle_subscription_charge_failure_returns_early_without_subscription_type(): void
    {
        Event::fake([SubscriptionRenewalFailed::class]);

        $user = $this->createUser(['chip_id' => 'cli_123']);

        $purchaseData = [
            'id' => 'pur_123',
            'client_id' => 'cli_123',
            'status' => 'failed',
        ];

        $purchase = PurchaseData::from($purchaseData);
        $event = new PurchaseSubscriptionChargeFailure($purchase, $purchaseData);

        $listener = new HandleSubscriptionChargeFailure;
        $listener->handle($event);

        Event::assertNotDispatched(SubscriptionRenewalFailed::class);
    }
}
