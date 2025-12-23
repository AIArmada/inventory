<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\CashierChip\Integration;

use AIArmada\CashierChip\Subscription;
use AIArmada\CashierChip\SubscriptionItem;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use Carbon\Carbon;
use Exception;
use InvalidArgumentException;

class SubscriptionIntegrationTest extends CashierChipTestCase
{
    public function test_can_swap_single_price(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_swap_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_price' => 'price_old',
            'chip_status' => Subscription::STATUS_ACTIVE,
        ]);
        SubscriptionItem::factory()->for($subscription)->create([
            'chip_price' => 'price_old',
        ]);

        $subscription->swap('price_new');

        $this->assertEquals('price_new', $subscription->fresh()->chip_price);
    }

    public function test_can_swap_multiple_prices(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_swap_multi_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_price' => 'price_old',
            'chip_status' => Subscription::STATUS_ACTIVE,
        ]);
        SubscriptionItem::factory()->for($subscription)->create([
            'chip_price' => 'price_old',
        ]);

        $subscription->swap(['price_new_1', 'price_new_2']);

        $this->assertNull($subscription->fresh()->chip_price);
        $this->assertEquals(2, $subscription->fresh()->items->count());
    }

    public function test_swap_throws_on_incomplete(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_swap_incomplete_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => Subscription::STATUS_INCOMPLETE,
        ]);

        $this->expectException(Exception::class);

        $subscription->swap('price_new');
    }

    public function test_swap_throws_with_empty_prices(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_swap_empty_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => Subscription::STATUS_ACTIVE,
        ]);

        $this->expectException(InvalidArgumentException::class);

        $subscription->swap([]);
    }

    public function test_swap_clears_ends_at(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_swap_ends_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_price' => 'price_old',
            'chip_status' => Subscription::STATUS_ACTIVE,
            'ends_at' => Carbon::now()->addDays(5),
        ]);
        SubscriptionItem::factory()->for($subscription)->create([
            'chip_price' => 'price_old',
        ]);

        $subscription->swap('price_new');

        $this->assertNull($subscription->fresh()->ends_at);
    }

    public function test_update_quantity(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_qty_update_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_price' => 'price_per_seat',
            'quantity' => 1,
            'chip_status' => Subscription::STATUS_ACTIVE,
        ]);
        SubscriptionItem::factory()->for($subscription)->create([
            'chip_price' => 'price_per_seat',
            'quantity' => 1,
        ]);

        $subscription->updateQuantity(5);

        $this->assertEquals(5, $subscription->fresh()->quantity);
    }

    public function test_update_quantity_clamps_to_minimum_one(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_qty_min_clamp_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_price' => 'price_per_seat',
            'quantity' => 2,
            'chip_status' => Subscription::STATUS_ACTIVE,
        ]);
        SubscriptionItem::factory()->for($subscription)->create([
            'chip_price' => 'price_per_seat',
            'quantity' => 2,
        ]);

        $subscription->updateQuantity(0);

        $this->assertEquals(1, $subscription->fresh()->quantity);
    }

    public function test_increment_quantity(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_qty_inc_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_price' => 'price_per_seat',
            'quantity' => 5,
            'chip_status' => Subscription::STATUS_ACTIVE,
        ]);
        SubscriptionItem::factory()->for($subscription)->create([
            'chip_price' => 'price_per_seat',
            'quantity' => 5,
        ]);

        $subscription->incrementQuantity(2);

        $this->assertEquals(7, $subscription->fresh()->quantity);
    }

    public function test_decrement_quantity(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_qty_dec_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_price' => 'price_per_seat',
            'quantity' => 5,
            'chip_status' => Subscription::STATUS_ACTIVE,
        ]);
        SubscriptionItem::factory()->for($subscription)->create([
            'chip_price' => 'price_per_seat',
            'quantity' => 5,
        ]);

        $subscription->decrementQuantity(2);

        $this->assertEquals(3, $subscription->fresh()->quantity);
    }

    public function test_decrement_quantity_minimum_one(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_qty_min_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_price' => 'price_per_seat',
            'quantity' => 2,
            'chip_status' => Subscription::STATUS_ACTIVE,
        ]);
        SubscriptionItem::factory()->for($subscription)->create([
            'chip_price' => 'price_per_seat',
            'quantity' => 2,
        ]);

        $subscription->decrementQuantity(5);

        $this->assertEquals(1, $subscription->fresh()->quantity);
    }

    public function test_quantity_throws_on_incomplete(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_qty_incomplete_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_price' => 'price_per_seat',
            'chip_status' => Subscription::STATUS_INCOMPLETE,
        ]);

        $this->expectException(Exception::class);

        $subscription->updateQuantity(5);
    }

    public function test_current_period_start(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_period_123']);
        $nextBilling = Carbon::now()->addMonth();
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'billing_interval' => 'month',
            'next_billing_at' => $nextBilling,
        ]);

        $periodStart = $subscription->currentPeriodStart();

        $this->assertNotNull($periodStart);
    }

    public function test_current_period_start_null_without_billing_date(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_period_null_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'next_billing_at' => null,
        ]);

        $this->assertNull($subscription->currentPeriodStart());
    }

    public function test_current_period_end(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_period_end_123']);
        $nextBilling = Carbon::now()->addMonth();
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'next_billing_at' => $nextBilling,
        ]);

        $periodEnd = $subscription->currentPeriodEnd();

        $this->assertNotNull($periodEnd);
    }

    public function test_current_period_end_with_timezone(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_period_tz_123']);
        $nextBilling = Carbon::now()->addMonth();
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'next_billing_at' => $nextBilling,
        ]);

        $periodEnd = $subscription->currentPeriodEnd('Asia/Kuala_Lumpur');

        $this->assertNotNull($periodEnd);
        $this->assertEquals('Asia/Kuala_Lumpur', $periodEnd->timezoneName);
    }

    public function test_recurring_token_from_subscription(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_token_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'recurring_token' => 'tok_sub_123',
        ]);

        $this->assertEquals('tok_sub_123', $subscription->recurringToken());
    }

    public function test_has_discount(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_discount_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'coupon_id' => 'COUPON123',
            'coupon_discount' => 1000,
        ]);

        $this->assertTrue($subscription->hasDiscount());
    }

    public function test_no_discount(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_no_discount_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'coupon_id' => null,
        ]);

        $this->assertFalse($subscription->hasDiscount());
    }

    public function test_has_product(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_prod_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create();
        SubscriptionItem::factory()->for($subscription)->create([
            'chip_product' => 'prod_123',
        ]);

        $this->assertTrue($subscription->hasProduct('prod_123'));
        $this->assertFalse($subscription->hasProduct('prod_456'));
    }

    public function test_has_price_with_multiple_prices(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_multi_price_check_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_price' => null,
        ]);
        SubscriptionItem::factory()->for($subscription)->create([
            'chip_price' => 'price_123',
        ]);
        SubscriptionItem::factory()->for($subscription)->create([
            'chip_price' => 'price_456',
        ]);

        $this->assertTrue($subscription->hasPrice('price_123'));
        $this->assertTrue($subscription->hasPrice('price_456'));
        $this->assertFalse($subscription->hasPrice('price_789'));
    }

    public function test_find_item_or_fail(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_find_item_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create();
        SubscriptionItem::factory()->for($subscription)->create([
            'chip_price' => 'price_123',
        ]);

        $item = $subscription->findItemOrFail('price_123');

        $this->assertInstanceOf(SubscriptionItem::class, $item);
    }

    public function test_find_item_or_fail_throws(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_find_item_fail_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create();

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $subscription->findItemOrFail('non_existent_price');
    }

    public function test_discount_returns_discount_instance(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_discount_inst_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'coupon_id' => 'COUPON123',
            'coupon_discount' => 1000,
            'coupon_applied_at' => now(),
        ]);

        $discount = $subscription->discount();

        $this->assertInstanceOf(\AIArmada\CashierChip\Discount::class, $discount);
    }

    public function test_discount_returns_null_without_coupon(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_discount_null_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'coupon_id' => null,
        ]);

        $this->assertNull($subscription->discount());
    }

    public function test_discounts_returns_collection(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_discounts_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'coupon_id' => 'COUPON123',
            'coupon_discount' => 1000,
        ]);

        $discounts = $subscription->discounts();

        $this->assertCount(1, $discounts);
    }

    public function test_discounts_returns_empty_without_coupon(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_discounts_empty_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'coupon_id' => null,
        ]);

        $this->assertCount(0, $subscription->discounts());
    }

    public function test_remove_discount(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_remove_discount_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'coupon_id' => 'COUPON123',
            'coupon_discount' => 1000,
        ]);

        $subscription->removeDiscount();

        $this->assertNull($subscription->fresh()->coupon_id);
        $this->assertNull($subscription->fresh()->coupon_discount);
    }

    public function test_paused(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_paused_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => Subscription::STATUS_PAUSED,
        ]);

        $this->assertTrue($subscription->paused());
    }

    public function test_pause(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_pause_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => Subscription::STATUS_ACTIVE,
        ]);

        $subscription->pause();

        $this->assertEquals(Subscription::STATUS_PAUSED, $subscription->chip_status);
    }

    public function test_unpause(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_unpause_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => Subscription::STATUS_PAUSED,
        ]);

        $subscription->unpause();

        $this->assertEquals(Subscription::STATUS_ACTIVE, $subscription->chip_status);
    }

    public function test_scope_paused(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_scope_paused_123']);
        Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => Subscription::STATUS_PAUSED,
        ]);
        Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => Subscription::STATUS_ACTIVE,
        ]);

        $this->assertEquals(1, Subscription::query()->paused()->count());
    }

    public function test_invoices_returns_empty_collection(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_invoices_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create();

        $this->assertCount(0, $subscription->invoices());
    }

    public function test_upcoming_invoice_returns_null_when_canceled(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_upcoming_invoice_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'ends_at' => now()->subDay(),
        ]);

        $this->assertNull($subscription->upcomingInvoice());
    }

    public function test_latest_invoice_returns_null(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_latest_invoice_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create();

        $this->assertNull($subscription->latestInvoice());
    }

    public function test_latest_payment_returns_null(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_latest_payment_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create();

        $this->assertNull($subscription->latestPayment());
    }

    public function test_add_price(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_add_price_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_price' => 'price_base',
            'chip_status' => Subscription::STATUS_ACTIVE,
        ]);
        SubscriptionItem::factory()->for($subscription)->create([
            'chip_price' => 'price_base',
        ]);

        $subscription->addPrice('price_addon');

        $this->assertEquals(2, $subscription->fresh()->items->count());
        $this->assertNull($subscription->fresh()->chip_price);
    }

    public function test_add_price_throws_on_duplicate(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_add_dup_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => Subscription::STATUS_ACTIVE,
        ]);
        SubscriptionItem::factory()->for($subscription)->create([
            'chip_price' => 'price_existing',
        ]);

        $this->expectException(\AIArmada\CashierChip\Exceptions\SubscriptionUpdateFailure::class);

        $subscription->addPrice('price_existing');
    }

    public function test_remove_price_throws_on_single_price(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_remove_single_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_price' => 'price_only',
            'chip_status' => Subscription::STATUS_ACTIVE,
        ]);
        SubscriptionItem::factory()->for($subscription)->create([
            'chip_price' => 'price_only',
        ]);

        $this->expectException(\AIArmada\CashierChip\Exceptions\SubscriptionUpdateFailure::class);

        $subscription->removePrice('price_only');
    }

    public function test_sync_chip_status(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_sync_status_123']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => Subscription::STATUS_ACTIVE,
            'ends_at' => null,
            'trial_ends_at' => null,
        ]);

        $subscription->syncChipStatus();

        // Status should remain active since not ended
        $this->assertEquals(Subscription::STATUS_ACTIVE, $subscription->chip_status);
    }
}
