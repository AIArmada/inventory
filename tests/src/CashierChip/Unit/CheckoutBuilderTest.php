<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\CashierChip\Unit;

use AIArmada\CashierChip\CheckoutBuilder;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;

class CheckoutBuilderTest extends CashierChipTestCase
{
    public function test_can_create_guest_builder(): void
    {
        $builder = new CheckoutBuilder;

        $this->assertInstanceOf(CheckoutBuilder::class, $builder);
    }

    public function test_can_create_builder_with_owner(): void
    {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $builder = new CheckoutBuilder($user);

        $this->assertInstanceOf(CheckoutBuilder::class, $builder);
    }

    public function test_recurring(): void
    {
        $builder = new CheckoutBuilder;

        $result = $builder->recurring();

        $this->assertSame($builder, $result);
    }

    public function test_recurring_with_false(): void
    {
        $builder = new CheckoutBuilder;
        $builder->recurring();

        $result = $builder->recurring(false);

        $this->assertSame($builder, $result);
    }

    public function test_success_url(): void
    {
        $builder = new CheckoutBuilder;

        $result = $builder->successUrl('https://example.com/success');

        $this->assertSame($builder, $result);
    }

    public function test_cancel_url(): void
    {
        $builder = new CheckoutBuilder;

        $result = $builder->cancelUrl('https://example.com/cancel');

        $this->assertSame($builder, $result);
    }

    public function test_webhook_url(): void
    {
        $builder = new CheckoutBuilder;

        $result = $builder->webhookUrl('https://example.com/webhook');

        $this->assertSame($builder, $result);
    }

    public function test_with_metadata(): void
    {
        $builder = new CheckoutBuilder;

        $result = $builder->withMetadata(['key' => 'value']);

        $this->assertSame($builder, $result);
    }

    public function test_add_product(): void
    {
        $builder = new CheckoutBuilder;

        $result = $builder->addProduct('Test Product', 1000);

        $this->assertSame($builder, $result);
    }

    public function test_add_product_with_quantity(): void
    {
        $builder = new CheckoutBuilder;

        $result = $builder->addProduct('Test Product', 1000, 5);

        $this->assertSame($builder, $result);
    }

    public function test_products(): void
    {
        $builder = new CheckoutBuilder;

        $result = $builder->products([
            ['name' => 'Product 1', 'price' => 1000, 'quantity' => 1],
        ]);

        $this->assertSame($builder, $result);
    }

    public function test_currency(): void
    {
        $builder = new CheckoutBuilder;

        $result = $builder->currency('MYR');

        $this->assertSame($builder, $result);
    }

    public function test_fluent_chaining(): void
    {
        $builder = new CheckoutBuilder;

        $result = $builder
            ->recurring()
            ->successUrl('https://example.com/success')
            ->cancelUrl('https://example.com/cancel')
            ->webhookUrl('https://example.com/webhook')
            ->withMetadata(['key' => 'value'])
            ->addProduct('Test', 1000)
            ->currency('MYR');

        $this->assertInstanceOf(CheckoutBuilder::class, $result);
    }

    public function test_create_keeps_prices_in_cents(): void
    {
        $checkout = (new CheckoutBuilder)
            ->addProduct('Test Product', 1000, 2)
            ->create(2000);

        $payload = $checkout->toArray();

        $this->assertSame(1000, $payload['purchase']['products'][0]['price']);
        $this->assertSame('2', $payload['purchase']['products'][0]['quantity']);
        $this->assertSame(2000, $payload['purchase']['total']);
    }
}
