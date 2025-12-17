<?php

declare(strict_types=1);

use AIArmada\FilamentChip\Pages\Billing\Invoices;
use AIArmada\FilamentChip\Pages\Billing\PaymentMethods;
use AIArmada\FilamentChip\Pages\Billing\Subscriptions;

it('formats known card brands', function (): void {
    $page = app(PaymentMethods::class);

    expect($page->formatCardBrand('visa'))->toBe('Visa');
    expect($page->formatCardBrand('MASTERCARD'))->toBe('Mastercard');
    expect($page->formatCardBrand('unknownBrand'))->toBe('UnknownBrand');
});

it('formats invoice statuses and colors', function (): void {
    $page = app(Invoices::class);

    expect($page->formatInvoiceStatus('paid'))->toBe('Paid');
    expect($page->getStatusColor('paid'))->toBe('success');

    expect($page->formatInvoiceStatus('VOID'))->toBe('Void');
    expect($page->getStatusColor('void'))->toBe('danger');

    expect($page->formatInvoiceStatus('custom_status'))->toBe('Custom_status');
    expect($page->getStatusColor('custom_status'))->toBe('gray');
});

it('uses billing config toggles for navigation registration', function (): void {
    config()->set('filament-chip.billing.features.subscriptions', false);
    config()->set('filament-chip.billing.features.payment_methods', true);
    config()->set('filament-chip.billing.features.invoices', false);

    expect(Subscriptions::shouldRegisterNavigation())->toBeFalse();
    expect(PaymentMethods::shouldRegisterNavigation())->toBeTrue();
    expect(Invoices::shouldRegisterNavigation())->toBeFalse();
});
