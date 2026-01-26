<?php

declare(strict_types=1);

use AIArmada\Docs\Enums\DocType;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\States\Paid;
use AIArmada\Orders\Events\OrderPaid;
use AIArmada\Orders\OrdersServiceProvider;
use AIArmada\Orders\Services\OrderService;
use Illuminate\Contracts\Events\Dispatcher;

describe('Orders ↔ Docs Integration', function (): void {
    it('creates a paid invoice document when an order is paid', function (): void {
        config()->set('orders.integrations.docs.enabled', true);

        app()->register(OrdersServiceProvider::class);

        $dispatcher = app(Dispatcher::class);
        expect($dispatcher->hasListeners(OrderPaid::class))->toBeTrue();
        $eventFired = false;
        $dispatcher->listen(OrderPaid::class, function () use (&$eventFired): void {
            $eventFired = true;
        });

        expect(interface_exists(\AIArmada\Docs\Contracts\DocServiceInterface::class))->toBeTrue();

        $service = new OrderService;

        $order = $service->createOrder(
            [
                'order_number' => 'ORD-DOCS-' . uniqid(),
                'subtotal' => 20000,
                'shipping_total' => 1500,
                'tax_total' => 1200,
                'discount_total' => 0,
                'grand_total' => 21500,
                'currency' => 'MYR',
            ],
            [
                [
                    'name' => 'Invoice Widget',
                    'quantity' => 2,
                    'unit_price' => 8000,
                    'tax_amount' => 400,
                    'sku' => 'INV-WIDGET-001',
                ],
            ],
            [
                'first_name' => 'Invoice',
                'last_name' => 'Customer',
                'line1' => '123 Invoice Street',
                'city' => 'Kuala Lumpur',
                'postcode' => '50000',
                'country_code' => 'MY',
                'email' => 'invoice@example.com',
            ],
        );

        $service->confirmPayment($order, 'txn_docs_123', 'stripe', 21500);

        expect($eventFired)->toBeTrue();

        $order->refresh();
        expect($order->paid_at)->not->toBeNull();

        $doc = Doc::query()
            ->where('docable_type', $order->getMorphClass())
            ->where('docable_id', $order->getKey())
            ->where('doc_type', DocType::Invoice->value)
            ->first();

        expect($doc)->not->toBeNull();
        expect($doc?->status->equals(Paid::class))->toBeTrue();
    });

    it('does not create a document when docs integration is disabled', function (): void {
        config()->set('orders.integrations.docs.enabled', false);

        app()->register(OrdersServiceProvider::class);

        $service = new OrderService;

        $order = $service->createOrder(
            [
                'order_number' => 'ORD-NODOCS-' . uniqid(),
                'subtotal' => 10000,
                'shipping_total' => 0,
                'tax_total' => 0,
                'discount_total' => 0,
                'grand_total' => 10000,
                'currency' => 'MYR',
            ],
            [
                [
                    'name' => 'No Docs Item',
                    'quantity' => 1,
                    'unit_price' => 10000,
                    'tax_amount' => 0,
                    'sku' => 'NO-DOCS-001',
                ],
            ],
        );

        $service->confirmPayment($order, 'txn_docs_456', 'stripe', 10000);

        $exists = Doc::query()
            ->where('docable_type', $order->getMorphClass())
            ->where('docable_id', $order->getKey())
            ->where('doc_type', DocType::Invoice->value)
            ->exists();

        expect($exists)->toBeFalse();
    });
});
