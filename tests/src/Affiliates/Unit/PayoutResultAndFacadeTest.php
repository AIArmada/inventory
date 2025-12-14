<?php

declare(strict_types=1);

use AIArmada\Affiliates\Data\PayoutResult;
use AIArmada\Affiliates\Facades\Affiliate;
use AIArmada\Affiliates\Services\AffiliateService;

// PayoutResult Tests
test('PayoutResult can be created with success and external reference', function (): void {
    $result = PayoutResult::success('TXN_12345', ['provider' => 'paypal']);

    expect($result->success)->toBeTrue();
    expect($result->externalReference)->toBe('TXN_12345');
    expect($result->failureReason)->toBeNull();
    expect($result->failureCode)->toBeNull();
    expect($result->metadata)->toBe(['provider' => 'paypal']);
});

test('PayoutResult can be created with failure', function (): void {
    $result = PayoutResult::failure('Insufficient funds', 'INSUFFICIENT_FUNDS');

    expect($result->success)->toBeFalse();
    expect($result->externalReference)->toBeNull();
    expect($result->failureReason)->toBe('Insufficient funds');
    expect($result->failureCode)->toBe('INSUFFICIENT_FUNDS');
    expect($result->metadata)->toBe([]);
});

test('PayoutResult can be created as pending', function (): void {
    $result = PayoutResult::pending('TXN_PENDING_123', ['estimated_arrival' => '2024-01-20']);

    expect($result->success)->toBeTrue();
    expect($result->externalReference)->toBe('TXN_PENDING_123');
    expect($result->metadata['status'])->toBe('pending');
    expect($result->metadata['estimated_arrival'])->toBe('2024-01-20');
});

test('PayoutResult isSuccess returns correct value', function (): void {
    $successResult = PayoutResult::success('TXN_123');
    $failureResult = PayoutResult::failure('Error');

    expect($successResult->isSuccess())->toBeTrue();
    expect($failureResult->isSuccess())->toBeFalse();
});

test('PayoutResult isPending returns correct value', function (): void {
    $pendingResult = PayoutResult::pending('TXN_123');
    $successResult = PayoutResult::success('TXN_123');
    $failureResult = PayoutResult::failure('Error');

    expect($pendingResult->isPending())->toBeTrue();
    expect($successResult->isPending())->toBeFalse();
    expect($failureResult->isPending())->toBeFalse();
});

test('PayoutResult getStatus returns correct status string', function (): void {
    $successResult = PayoutResult::success('TXN_123');
    $pendingResult = PayoutResult::pending('TXN_123');
    $failureResult = PayoutResult::failure('Error');

    expect($successResult->getStatus())->toBe('completed');
    expect($pendingResult->getStatus())->toBe('pending');
    expect($failureResult->getStatus())->toBe('failed');
});

test('PayoutResult can be created via constructor directly', function (): void {
    $result = new PayoutResult(
        success: true,
        externalReference: 'DIRECT_123',
        failureReason: null,
        failureCode: null,
        metadata: ['source' => 'test']
    );

    expect($result->success)->toBeTrue();
    expect($result->externalReference)->toBe('DIRECT_123');
    expect($result->metadata)->toBe(['source' => 'test']);
});

test('PayoutResult failure without code works', function (): void {
    $result = PayoutResult::failure('Generic error');

    expect($result->success)->toBeFalse();
    expect($result->failureReason)->toBe('Generic error');
    expect($result->failureCode)->toBeNull();
});

// Affiliate Facade Tests
test('Affiliate facade resolves to AffiliateService', function (): void {
    expect(Affiliate::getFacadeRoot())->toBeInstanceOf(AffiliateService::class);
});

test('Affiliate facade accessor returns affiliates', function (): void {
    $accessor = (new ReflectionClass(Affiliate::class))->getMethod('getFacadeAccessor');
    $accessor->setAccessible(true);

    expect($accessor->invoke(null))->toBe('affiliates');
});
