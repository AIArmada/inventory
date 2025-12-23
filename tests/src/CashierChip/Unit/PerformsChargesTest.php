<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\CashierChip\Unit;

use AIArmada\CashierChip\Checkout;
use AIArmada\CashierChip\Payment;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;

class PerformsChargesTest extends CashierChipTestCase
{
    public function test_charge_returns_payment(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $payment = $user->charge(1000);

        $this->assertInstanceOf(Payment::class, $payment);
    }

    public function test_pay_returns_payment(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $payment = $user->pay(1000);

        $this->assertInstanceOf(Payment::class, $payment);
    }

    public function test_pay_with_returns_payment(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $payment = $user->payWith(1000, ['card', 'fpx']);

        $this->assertInstanceOf(Payment::class, $payment);
    }

    public function test_create_payment_returns_payment(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $payment = $user->createPayment(1000);

        $this->assertInstanceOf(Payment::class, $payment);
    }

    public function test_create_payment_with_options(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $payment = $user->createPayment(1000, [
            'product_name' => 'Test Product',
            'currency' => 'MYR',
            'success_url' => 'https://example.com/success',
            'failure_url' => 'https://example.com/failure',
            'cancel_url' => 'https://example.com/cancel',
            'webhook_url' => 'https://example.com/webhook',
        ]);

        $this->assertInstanceOf(Payment::class, $payment);
    }

    public function test_find_payment_returns_null_on_error(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $payment = $user->findPayment('non_existent_id');

        $this->assertNull($payment);
    }

    public function test_charge_with_recurring_token(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $payment = $user->chargeWithRecurringToken(1000, null);

        $this->assertInstanceOf(Payment::class, $payment);
    }

    public function test_checkout_returns_checkout(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $checkout = $user->checkout(1000);

        $this->assertInstanceOf(Checkout::class, $checkout);
    }

    public function test_checkout_with_options(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $checkout = $user->checkout(1000, [
            'success_url' => 'https://example.com/success',
            'cancel_url' => 'https://example.com/cancel',
        ]);

        $this->assertInstanceOf(Checkout::class, $checkout);
    }

    public function test_checkout_charge_returns_checkout(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $checkout = $user->checkoutCharge(1000, 'Test Product', 2);

        $this->assertInstanceOf(Checkout::class, $checkout);

        $payload = $checkout->toArray();
        $this->assertSame(1000, $payload['purchase']['products'][0]['price']);
        $this->assertSame('2', $payload['purchase']['products'][0]['quantity']);
    }

    public function test_charge_without_chip_id(): void
    {
        $user = $this->createUser(['email' => 'test@example.com', 'name' => 'Test User']);

        $payment = $user->charge(1000);

        $this->assertInstanceOf(Payment::class, $payment);
    }

    public function test_create_payment_with_skip_capture(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $payment = $user->createPayment(1000, [
            'skip_capture' => true,
        ]);

        $this->assertInstanceOf(Payment::class, $payment);
    }

    public function test_create_payment_with_force_recurring(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);

        $payment = $user->createPayment(1000, [
            'force_recurring' => true,
        ]);

        $this->assertInstanceOf(Payment::class, $payment);
    }
}
