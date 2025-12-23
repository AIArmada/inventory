<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\CashierChip\Unit;

use AIArmada\CashierChip\PaymentMethod;
use AIArmada\CashierChip\Subscription;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use AIArmada\Commerce\Tests\CashierChip\Fixtures\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Carbon\Carbon;
use LogicException;
use Mockery;

class SubscriptionTest extends CashierChipTestCase
{
    public function test_can_check_active_status()
    {
        $subscription = new Subscription(['chip_status' => Subscription::STATUS_ACTIVE]);
        $this->assertTrue($subscription->active());
        $this->assertFalse($subscription->onTrial());

        $subscription->chip_status = Subscription::STATUS_TRIALING;
        $subscription->trial_ends_at = Carbon::now()->addDay();
        $this->assertTrue($subscription->onTrial());
        $this->assertTrue($subscription->active()); // trialing is active

        $subscription->chip_status = Subscription::STATUS_PAST_DUE;
        $this->assertFalse($subscription->active());

        $subscription->chip_status = Subscription::STATUS_UNPAID;
        $this->assertFalse($subscription->active());

        $subscription->chip_status = Subscription::STATUS_CANCELED;
        $subscription->ends_at = Carbon::now()->subDay();
        $this->assertFalse($subscription->active());

        $subscription->chip_status = Subscription::STATUS_INCOMPLETE;
        $this->assertFalse($subscription->active());
    }

    public function test_can_check_valid_status()
    {
        $subscription = new Subscription(['chip_status' => Subscription::STATUS_ACTIVE]);
        $this->assertTrue($subscription->valid());

        $subscription->chip_status = Subscription::STATUS_PAST_DUE;
        $this->assertFalse($subscription->valid());

        $subscription->chip_status = Subscription::STATUS_CANCELED;
        $subscription->ends_at = Carbon::now()->subDay();
        $this->assertFalse($subscription->valid());
    }

    public function test_can_check_incomplete()
    {
        $subscription = new Subscription(['chip_status' => Subscription::STATUS_INCOMPLETE]);
        $this->assertTrue($subscription->incomplete());

        $subscription->chip_status = Subscription::STATUS_INCOMPLETE_EXPIRED;
        $this->assertFalse($subscription->incomplete()); // wait, check logic

        $subscription->chip_status = Subscription::STATUS_ACTIVE;
        $this->assertFalse($subscription->incomplete());
    }

    public function test_can_check_canceled()
    {
        $subscription = new Subscription(['chip_status' => Subscription::STATUS_CANCELED, 'ends_at' => Carbon::now()]);
        $this->assertTrue($subscription->canceled());

        $subscription->chip_status = Subscription::STATUS_ACTIVE;
        $subscription->ends_at = null;
        $this->assertFalse($subscription->canceled());

        // Grace period cancellation
        $subscription->chip_status = Subscription::STATUS_ACTIVE;
        $subscription->ends_at = Carbon::now()->addDay();
        $this->assertTrue($subscription->onGracePeriod());
        $this->assertTrue($subscription->canceled());

        $subscription->ends_at = null; // Uncancel
        $this->assertFalse($subscription->canceled());
    }

    public function test_can_check_ended()
    {
        $subscription = new Subscription([
            'chip_status' => Subscription::STATUS_CANCELED,
            'ends_at' => Carbon::now()->subDay(),
        ]);
        $this->assertTrue($subscription->ended());

        $subscription->chip_status = Subscription::STATUS_ACTIVE;
        $subscription->ends_at = null;
        $this->assertFalse($subscription->ended());

        // Grace period is NOT ended
        $subscription->ends_at = Carbon::now()->addDay();
        $this->assertFalse($subscription->ended());
    }

    public function test_has_incomplete_payment()
    {
        $subscription = new Subscription([
            'chip_status' => Subscription::STATUS_PAST_DUE,
        ]);
        // Mock latestPayment?
        // Method uses $this->pastDue() || $this->isIncomplete().

        $this->assertTrue($subscription->hasIncompletePayment());

        $subscription->chip_status = Subscription::STATUS_INCOMPLETE;
        $this->assertTrue($subscription->hasIncompletePayment());

        $subscription->chip_status = Subscription::STATUS_ACTIVE;
        $this->assertFalse($subscription->hasIncompletePayment());
    }

    public function test_owner_relationship()
    {
        $user = new User;
        $subscription = new Subscription;
        $subscription->setRelation('owner', $user);

        $this->assertSame($user, $subscription->owner);
    }

    public function test_items_relationship()
    {
        // hasMany relation
        $subscription = new Subscription;
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $subscription->items());
    }

    public function test_can_cancel_immediately()
    {
        $user = User::create(['email' => 'test@example.com', 'name' => 'Test', 'chip_id' => 'cli_1']);
        $subscription = Subscription::factory()->for($user, 'owner')->create(['chip_status' => Subscription::STATUS_ACTIVE]);

        $subscription->cancelNow();

        $this->assertTrue($subscription->canceled());
        $this->assertEquals(Subscription::STATUS_CANCELED, $subscription->chip_status);
        $this->assertNotNull($subscription->ends_at);
    }

    public function test_can_resume()
    {
        $user = User::create(['email' => 'test@example.com', 'name' => 'Test', 'chip_id' => 'cli_1']);
        $subscription = Subscription::factory()->for($user, 'owner')->create([
            'chip_status' => Subscription::STATUS_CANCELED,
            'ends_at' => Carbon::tomorrow(),
        ]);

        $subscription->resume();

        $this->assertTrue($subscription->active());
        $this->assertFalse($subscription->canceled());
        $this->assertNull($subscription->ends_at);
        $this->assertEquals(Subscription::STATUS_ACTIVE, $subscription->chip_status);
    }

    public function test_resume_throws_exception_if_not_on_grace_period()
    {
        $subscription = new Subscription([
            'chip_status' => Subscription::STATUS_CANCELED,
            'ends_at' => Carbon::yesterday(),
        ]);

        $this->expectException(LogicException::class);
        $subscription->resume();
    }

    public function test_skip_trial()
    {
        $subscription = new Subscription([
            'trial_ends_at' => Carbon::tomorrow(),
        ]);

        $subscription->skipTrial();
        $this->assertNull($subscription->trial_ends_at);
    }

    public function test_end_trial()
    {
        $user = User::create(['email' => 'test@example.com', 'name' => 'Test', 'chip_id' => 'cli_1']);
        $subscription = Subscription::factory()->for($user, 'customer')->create([
            'trial_ends_at' => Carbon::tomorrow(),
        ]);

        $subscription->endTrial();

        $this->assertNull($subscription->fresh()->trial_ends_at);
    }

    public function test_recurring_token()
    {
        $subscription = new Subscription(['recurring_token' => 'tok_123']);
        $this->assertEquals('tok_123', $subscription->recurringToken());

        $subscription = new Subscription;
        $owner = Mockery::mock(User::class);
        $pm = Mockery::mock(PaymentMethod::class);
        $pm->shouldReceive('id')->andReturn('tok_default');
        $owner->shouldReceive('defaultPaymentMethod')->andReturn($pm);
        $subscription->setRelation('customer', $owner);

        $this->assertEquals('tok_default', $subscription->recurringToken());
    }

    public function test_increment_decrement_quantity()
    {
        $user = User::create(['email' => 'test@example.com', 'name' => 'Test', 'chip_id' => 'cli_1']);

        $subscription = null;
        OwnerContext::withOwner($user, function () use ($user, &$subscription): void {
            $subscription = Subscription::factory()
                ->for($user, 'owner')
                ->for($user, 'customer')
                ->create(['quantity' => 1, 'chip_price' => 'price_1']);

            $subscription->items()->create(['quantity' => 1, 'chip_id' => 'si_1', 'chip_price' => 'price_1']);
        });

        $this->assertInstanceOf(Subscription::class, $subscription);

        OwnerContext::withOwner($user, fn (): mixed => $subscription->incrementQuantity());
        $this->assertEquals(2, $subscription->fresh()->quantity);

        OwnerContext::withOwner($user, fn (): mixed => $subscription->decrementQuantity());
        $this->assertEquals(1, $subscription->fresh()->quantity);
    }

    public function test_scope_active()
    {
        $user = User::create(['email' => 'u1', 'name' => 'U1', 'chip_id' => 'c1']);
        Subscription::factory()->for($user, 'customer')->create(['chip_status' => Subscription::STATUS_ACTIVE]);
        Subscription::factory()->for($user, 'customer')->create(['chip_status' => Subscription::STATUS_CANCELED, 'ends_at' => Carbon::now()->subDay()]);

        // Use query()->active()
        $this->assertEquals(1, Subscription::query()->active()->count());
    }
}
