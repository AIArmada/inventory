<?php

declare(strict_types=1);

use AIArmada\FilamentCashier\Support\InvoiceStatus;
use AIArmada\FilamentCashier\Support\UnifiedInvoice;
use Carbon\CarbonImmutable;

it('can format amount in USD correctly', function (): void {
    $original = new stdClass;
    $original->id = 'inv_123';

    $invoice = new UnifiedInvoice(
        id: 'inv_123',
        gateway: 'stripe',
        userId: 'user_456',
        number: 'INV-0001',
        amount: 2999,
        currency: 'USD',
        status: InvoiceStatus::Paid,
        date: CarbonImmutable::now(),
        dueDate: null,
        paidAt: CarbonImmutable::now(),
        pdfUrl: null,
        original: $original
    );

    $formatted = $invoice->formattedAmount();

    expect($formatted)->toBe('$29.99');
});

it('can format amount in MYR correctly', function (): void {
    $original = new stdClass;
    $original->id = 'inv_456';

    $invoice = new UnifiedInvoice(
        id: 'inv_456',
        gateway: 'chip',
        userId: 'user_456',
        number: 'INV-0002',
        amount: 5000,
        currency: 'MYR',
        status: InvoiceStatus::Open,
        date: CarbonImmutable::now(),
        dueDate: CarbonImmutable::now()->addDays(30),
        paidAt: null,
        pdfUrl: null,
        original: $original
    );

    $formatted = $invoice->formattedAmount();

    expect($formatted)->toBe('RM50.00');
});

it('returns gateway config', function (): void {
    $original = new stdClass;
    $original->id = 'inv_123';

    $invoice = new UnifiedInvoice(
        id: 'inv_123',
        gateway: 'stripe',
        userId: 'user_456',
        number: 'INV-0001',
        amount: 2999,
        currency: 'USD',
        status: InvoiceStatus::Paid,
        date: CarbonImmutable::now(),
        dueDate: null,
        paidAt: CarbonImmutable::now(),
        pdfUrl: null,
        original: $original
    );

    $config = $invoice->gatewayConfig();

    expect($config)->toBeArray()
        ->and($config)->toHaveKeys(['label', 'color', 'icon']);
});

it('can have a pdf url', function (): void {
    $original = new stdClass;
    $original->id = 'inv_123';

    $invoice = new UnifiedInvoice(
        id: 'inv_123',
        gateway: 'stripe',
        userId: 'user_456',
        number: 'INV-0001',
        amount: 2999,
        currency: 'USD',
        status: InvoiceStatus::Paid,
        date: CarbonImmutable::now(),
        dueDate: null,
        paidAt: CarbonImmutable::now(),
        pdfUrl: 'https://example.com/invoice.pdf',
        original: $original
    );

    expect($invoice->pdfUrl)->toBe('https://example.com/invoice.pdf');
});

it('can have null pdf url', function (): void {
    $original = new stdClass;
    $original->id = 'inv_123';

    $invoice = new UnifiedInvoice(
        id: 'inv_123',
        gateway: 'stripe',
        userId: 'user_456',
        number: 'INV-0001',
        amount: 2999,
        currency: 'USD',
        status: InvoiceStatus::Paid,
        date: CarbonImmutable::now(),
        dueDate: null,
        paidAt: CarbonImmutable::now(),
        pdfUrl: null,
        original: $original
    );

    expect($invoice->pdfUrl)->toBeNull();
});

it('can be open without paid date', function (): void {
    $original = new stdClass;
    $original->id = 'inv_123';

    $invoice = new UnifiedInvoice(
        id: 'inv_123',
        gateway: 'stripe',
        userId: 'user_456',
        number: 'INV-0001',
        amount: 2999,
        currency: 'USD',
        status: InvoiceStatus::Open,
        date: CarbonImmutable::now(),
        dueDate: CarbonImmutable::now()->addDays(30),
        paidAt: null,
        pdfUrl: null,
        original: $original
    );

    expect($invoice->status)->toBe(InvoiceStatus::Open)
        ->and($invoice->paidAt)->toBeNull()
        ->and($invoice->dueDate)->not->toBeNull();
});

it('can be void', function (): void {
    $original = new stdClass;
    $original->id = 'inv_123';

    $invoice = new UnifiedInvoice(
        id: 'inv_123',
        gateway: 'stripe',
        userId: 'user_456',
        number: 'INV-0001',
        amount: 2999,
        currency: 'USD',
        status: InvoiceStatus::Void,
        date: CarbonImmutable::now(),
        dueDate: null,
        paidAt: null,
        pdfUrl: null,
        original: $original
    );

    expect($invoice->status)->toBe(InvoiceStatus::Void);
});

it('formats non-USD currencies with correct symbols', function (): void {
    $original = new stdClass;
    $original->id = 'inv_999';

    $eur = new UnifiedInvoice(
        id: 'inv_999',
        gateway: 'stripe',
        userId: 'user_456',
        number: 'INV-9999',
        amount: 1234,
        currency: 'EUR',
        status: InvoiceStatus::Paid,
        date: CarbonImmutable::now(),
        dueDate: null,
        paidAt: null,
        pdfUrl: null,
        original: $original
    );

    $gbp = new UnifiedInvoice(
        id: 'inv_999',
        gateway: 'stripe',
        userId: 'user_456',
        number: 'INV-9999',
        amount: 1234,
        currency: 'GBP',
        status: InvoiceStatus::Paid,
        date: CarbonImmutable::now(),
        dueDate: null,
        paidAt: null,
        pdfUrl: null,
        original: $original
    );

    $unknown = new UnifiedInvoice(
        id: 'inv_999',
        gateway: 'stripe',
        userId: 'user_456',
        number: 'INV-9999',
        amount: 1234,
        currency: 'JPY',
        status: InvoiceStatus::Paid,
        date: CarbonImmutable::now(),
        dueDate: null,
        paidAt: null,
        pdfUrl: null,
        original: $original
    );

    expect($eur->formattedAmount())->toBe('€12.34');
    expect($gbp->formattedAmount())->toBe('£12.34');
    expect($unknown->formattedAmount())->toBe('JPY 12.34');
});
