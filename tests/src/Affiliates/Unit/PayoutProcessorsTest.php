<?php

declare(strict_types=1);

use AIArmada\Affiliates\Contracts\PayoutProcessorInterface;
use AIArmada\Affiliates\Data\PayoutResult;
use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\PayoutMethodType;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateBalance;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Services\PayoutReconciliationService;
use AIArmada\Affiliates\Services\Payouts\ManualPayoutProcessor;
use AIArmada\Affiliates\Services\Payouts\PayoutProcessorFactory;
use AIArmada\Affiliates\Services\Payouts\PayPalProcessor;
use AIArmada\Affiliates\Services\Payouts\StripeConnectProcessor;

// PayoutProcessorFactory Tests
test('PayoutProcessorFactory can be instantiated', function (): void {
    $factory = new PayoutProcessorFactory;

    expect($factory)->toBeInstanceOf(PayoutProcessorFactory::class);
});

test('PayoutProcessorFactory make returns ManualPayoutProcessor for manual type', function (): void {
    $factory = new PayoutProcessorFactory;

    $processor = $factory->make('manual');

    expect($processor)->toBeInstanceOf(PayoutProcessorInterface::class);
    expect($processor)->toBeInstanceOf(ManualPayoutProcessor::class);
});

test('PayoutProcessorFactory make returns ManualPayoutProcessor for bank_transfer type', function (): void {
    $factory = new PayoutProcessorFactory;

    $processor = $factory->make('bank_transfer');

    expect($processor)->toBeInstanceOf(ManualPayoutProcessor::class);
});

test('PayoutProcessorFactory make accepts PayoutMethodType enum', function (): void {
    $factory = new PayoutProcessorFactory;

    $processor = $factory->make(PayoutMethodType::BankTransfer);

    expect($processor)->toBeInstanceOf(PayoutProcessorInterface::class);
});

test('PayoutProcessorFactory make throws exception for unknown type', function (): void {
    $factory = new PayoutProcessorFactory;

    $factory->make('unknown_processor_type');
})->throws(InvalidArgumentException::class, 'Unknown payout processor type');

test('PayoutProcessorFactory register adds new processor', function (): void {
    $factory = new PayoutProcessorFactory;

    $factory->register('custom', ManualPayoutProcessor::class);

    expect($factory->hasProcessor('custom'))->toBeTrue();
});

test('PayoutProcessorFactory register throws for invalid class', function (): void {
    $factory = new PayoutProcessorFactory;

    $factory->register('invalid', \stdClass::class);
})->throws(InvalidArgumentException::class, 'must implement');

test('PayoutProcessorFactory getAvailableProcessors returns array', function (): void {
    $factory = new PayoutProcessorFactory;

    $processors = $factory->getAvailableProcessors();

    expect($processors)->toBeArray();
    expect($processors)->toContain('manual');
    expect($processors)->toContain('bank_transfer');
    expect($processors)->toContain('stripe_connect');
    expect($processors)->toContain('paypal');
});

test('PayoutProcessorFactory hasProcessor returns true for existing', function (): void {
    $factory = new PayoutProcessorFactory;

    expect($factory->hasProcessor('manual'))->toBeTrue();
    expect($factory->hasProcessor('nonexistent'))->toBeFalse();
});

// ManualPayoutProcessor Tests
test('ManualPayoutProcessor can be instantiated', function (): void {
    $processor = new ManualPayoutProcessor;

    expect($processor)->toBeInstanceOf(ManualPayoutProcessor::class);
    expect($processor)->toBeInstanceOf(PayoutProcessorInterface::class);
});

test('ManualPayoutProcessor process returns pending result', function (): void {
    $processor = new ManualPayoutProcessor;

    $affiliate = Affiliate::create([
        'code' => 'MANUAL001',
        'name' => 'Manual Payout Test',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $payout = AffiliatePayout::create([
        'affiliate_id' => $affiliate->id,
        'reference' => 'PAY-MANUAL-001',
        'amount_minor' => 10000,
        'currency' => 'USD',
        'status' => 'pending',
    ]);

    $result = $processor->process($payout);

    expect($result)->toBeInstanceOf(PayoutResult::class);
    // PayoutResult::pending returns success=true (pending is a valid state)
    expect($result->externalReference)->toStartWith('MANUAL-');
});

test('ManualPayoutProcessor getStatus returns payout status', function (): void {
    $processor = new ManualPayoutProcessor;

    $affiliate = Affiliate::create([
        'code' => 'MANUAL002',
        'name' => 'Status Test',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $payout = AffiliatePayout::create([
        'affiliate_id' => $affiliate->id,
        'reference' => 'PAY-MANUAL-002',
        'amount_minor' => 5000,
        'currency' => 'USD',
        'status' => 'processing',
    ]);

    $status = $processor->getStatus($payout);

    expect($status)->toBe('processing');
});

test('ManualPayoutProcessor cancel returns true', function (): void {
    $processor = new ManualPayoutProcessor;

    $affiliate = Affiliate::create([
        'code' => 'MANUAL003',
        'name' => 'Cancel Test',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $payout = AffiliatePayout::create([
        'affiliate_id' => $affiliate->id,
        'reference' => 'PAY-MANUAL-003',
        'amount_minor' => 5000,
        'currency' => 'USD',
        'status' => 'pending',
    ]);

    $result = $processor->cancel($payout);

    expect($result)->toBeTrue();
});

test('ManualPayoutProcessor getEstimatedArrival returns future date', function (): void {
    $processor = new ManualPayoutProcessor;

    $affiliate = Affiliate::create([
        'code' => 'MANUAL004',
        'name' => 'Arrival Test',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $payout = AffiliatePayout::create([
        'affiliate_id' => $affiliate->id,
        'reference' => 'PAY-MANUAL-004',
        'amount_minor' => 5000,
        'currency' => 'USD',
        'status' => 'pending',
    ]);

    $arrival = $processor->getEstimatedArrival($payout);

    expect($arrival)->toBeInstanceOf(\DateTimeInterface::class);
    expect($arrival->getTimestamp())->toBeGreaterThan(now()->getTimestamp());
});

test('ManualPayoutProcessor getFees returns zero', function (): void {
    $processor = new ManualPayoutProcessor;

    $fees = $processor->getFees(10000, 'USD');

    expect($fees)->toBe(0);
});

test('ManualPayoutProcessor validateDetails returns empty array', function (): void {
    $processor = new ManualPayoutProcessor;

    $errors = $processor->validateDetails(['key' => 'value']);

    expect($errors)->toBeArray();
    expect($errors)->toBeEmpty();
});

test('ManualPayoutProcessor getIdentifier returns manual', function (): void {
    $processor = new ManualPayoutProcessor;

    $identifier = $processor->getIdentifier();

    expect($identifier)->toBe('manual');
});

// PayPalProcessor Tests
test('PayPalProcessor can be instantiated', function (): void {
    $processor = new PayPalProcessor;

    expect($processor)->toBeInstanceOf(PayPalProcessor::class);
});

test('PayPalProcessor getIdentifier returns paypal', function (): void {
    $processor = new PayPalProcessor;

    expect($processor->getIdentifier())->toBe('paypal');
});

test('PayPalProcessor getFees calculates correctly', function (): void {
    $processor = new PayPalProcessor;

    $fees = $processor->getFees(10000, 'USD');

    expect($fees)->toBeInt();
    expect($fees)->toBeGreaterThanOrEqual(0);
    expect($fees)->toBeLessThanOrEqual(100); // Max $1 per their fee structure
});

test('PayPalProcessor validateDetails requires email', function (): void {
    $processor = new PayPalProcessor;

    $errors = $processor->validateDetails([]);

    expect($errors)->toHaveKey('email');
});

test('PayPalProcessor validateDetails validates email format', function (): void {
    $processor = new PayPalProcessor;

    $errors = $processor->validateDetails(['email' => 'not-an-email']);

    expect($errors)->toHaveKey('email');
});

test('PayPalProcessor validateDetails passes for valid email', function (): void {
    $processor = new PayPalProcessor;

    $errors = $processor->validateDetails(['email' => 'test@example.com']);

    expect($errors)->toBeEmpty();
});

// StripeConnectProcessor Tests
test('StripeConnectProcessor can be instantiated', function (): void {
    $processor = new StripeConnectProcessor;

    expect($processor)->toBeInstanceOf(StripeConnectProcessor::class);
});

test('StripeConnectProcessor getIdentifier returns stripe_connect', function (): void {
    $processor = new StripeConnectProcessor;

    expect($processor->getIdentifier())->toBe('stripe_connect');
});

test('StripeConnectProcessor getFees calculates correctly', function (): void {
    $processor = new StripeConnectProcessor;

    $fees = $processor->getFees(10000, 'USD');

    expect($fees)->toBeInt();
    expect($fees)->toBeGreaterThan(0); // 0.25% + $0.25 flat
});

test('StripeConnectProcessor validateDetails requires stripe_account_id', function (): void {
    $processor = new StripeConnectProcessor;

    $errors = $processor->validateDetails([]);

    expect($errors)->toHaveKey('stripe_account_id');
});

test('StripeConnectProcessor validateDetails validates account ID format', function (): void {
    $processor = new StripeConnectProcessor;

    $errors = $processor->validateDetails(['stripe_account_id' => 'invalid']);

    expect($errors)->toHaveKey('stripe_account_id');
});

test('StripeConnectProcessor validateDetails passes for valid account ID', function (): void {
    $processor = new StripeConnectProcessor;

    $errors = $processor->validateDetails(['stripe_account_id' => 'acct_123456789']);

    expect($errors)->toBeEmpty();
});

// PayoutReconciliationService Tests
test('PayoutReconciliationService can be instantiated', function (): void {
    $service = app(PayoutReconciliationService::class);

    expect($service)->toBeInstanceOf(PayoutReconciliationService::class);
});
