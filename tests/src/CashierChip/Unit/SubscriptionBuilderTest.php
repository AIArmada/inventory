<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\CashierChip\Unit;

use AIArmada\CashierChip\Subscription;
use AIArmada\CashierChip\SubscriptionBuilder;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use AIArmada\Commerce\Tests\CashierChip\Fixtures\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Carbon\Carbon;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;

class SubscriptionBuilderTest extends CashierChipTestCase
{
    public function test_can_create_builder(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $builder = new SubscriptionBuilder($user, 'default', 'price_123');

        $this->assertInstanceOf(SubscriptionBuilder::class, $builder);
    }

    public function test_can_add_price(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $builder = new SubscriptionBuilder($user, 'default');

        $result = $builder->price('price_123');

        $this->assertSame($builder, $result);
    }

    public function test_can_add_price_with_quantity(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $builder = new SubscriptionBuilder($user, 'default');

        $result = $builder->price('price_123', 5);

        $this->assertSame($builder, $result);
    }

    public function test_quantity_throws_without_price(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $builder = new SubscriptionBuilder($user, 'default');

        $this->expectException(InvalidArgumentException::class);

        $builder->quantity(5);
    }

    public function test_quantity_with_single_price(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $builder = new SubscriptionBuilder($user, 'default', 'price_123');

        $result = $builder->quantity(5);

        $this->assertSame($builder, $result);
    }

    public function test_trial_days(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $builder = new SubscriptionBuilder($user, 'default', 'price_123');

        $result = $builder->trialDays(14);

        $this->assertSame($builder, $result);
    }

    public function test_trial_until(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $builder = new SubscriptionBuilder($user, 'default', 'price_123');

        $trialEnd = Carbon::now()->addDays(30);
        $result = $builder->trialUntil($trialEnd);

        $this->assertSame($builder, $result);
    }

    public function test_skip_trial(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $builder = new SubscriptionBuilder($user, 'default', 'price_123');

        $result = $builder->skipTrial();

        $this->assertSame($builder, $result);
    }

    public function test_billing_interval(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $builder = new SubscriptionBuilder($user, 'default', 'price_123');

        $result = $builder->billingInterval('month', 1);

        $this->assertSame($builder, $result);
    }

    public function test_monthly(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $builder = new SubscriptionBuilder($user, 'default', 'price_123');

        $result = $builder->monthly();

        $this->assertSame($builder, $result);
    }

    public function test_yearly(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $builder = new SubscriptionBuilder($user, 'default', 'price_123');

        $result = $builder->yearly();

        $this->assertSame($builder, $result);
    }

    public function test_weekly(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $builder = new SubscriptionBuilder($user, 'default', 'price_123');

        $result = $builder->weekly();

        $this->assertSame($builder, $result);
    }

    public function test_daily(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $builder = new SubscriptionBuilder($user, 'default', 'price_123');

        $result = $builder->daily();

        $this->assertSame($builder, $result);
    }

    public function test_anchor_billing_cycle(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $builder = new SubscriptionBuilder($user, 'default', 'price_123');

        $anchor = Carbon::now()->addDay();
        $result = $builder->anchorBillingCycleOn($anchor);

        $this->assertSame($builder, $result);
    }

    public function test_with_metadata(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $builder = new SubscriptionBuilder($user, 'default', 'price_123');

        $result = $builder->withMetadata(['key' => 'value']);

        $this->assertSame($builder, $result);
    }

    public function test_add_throws_without_prices(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $builder = new SubscriptionBuilder($user, 'default');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('At least one price is required');

        $builder->add();
    }

    public function test_create_throws_without_prices(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $builder = new SubscriptionBuilder($user, 'default');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('At least one price is required');

        $builder->create();
    }

    public function test_create_requires_owner_context_when_owner_scoping_enabled(): void
    {
        OwnerContext::clearOverride();

        $user = User::create([
            'name' => 'Test User',
            'email' => 'test-' . uniqid() . '@example.com',
            'chip_id' => 'cli_123',
        ]);

        $builder = new SubscriptionBuilder($user, 'default', 'price_123');

        try {
            $builder->create();
            $this->fail('Expected AuthorizationException was not thrown.');
        } catch (AuthorizationException) {
            $this->assertSame(0, Subscription::query()->withoutOwnerScope()->count());
        }
    }
}
