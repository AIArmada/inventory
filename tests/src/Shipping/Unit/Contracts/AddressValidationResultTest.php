<?php

declare(strict_types=1);

use AIArmada\Shipping\Contracts\AddressValidationResult;
use AIArmada\Shipping\Data\AddressData;

// ============================================
// AddressValidationResult Tests
// ============================================

it('creates valid result', function (): void {
    $result = new AddressValidationResult(valid: true);

    expect($result->valid)->toBeTrue();
    expect($result->isValid())->toBeTrue();
    expect($result->correctedAddress)->toBeNull();
    expect($result->warnings)->toBe([]);
    expect($result->errors)->toBe([]);
});

it('creates invalid result with errors', function (): void {
    $result = new AddressValidationResult(
        valid: false,
        errors: ['Invalid postal code', 'Unknown city'],
    );

    expect($result->valid)->toBeFalse();
    expect($result->isValid())->toBeFalse();
    expect($result->errors)->toBe(['Invalid postal code', 'Unknown city']);
    expect($result->hasErrors())->toBeTrue();
});

it('creates result with corrected address', function (): void {
    $corrected = new AddressData(
        name: 'John Doe',
        phone: '123456789',
        line1: '123 Corrected Street',
        postcode: '50001',
        country: 'MY',
    );

    $result = new AddressValidationResult(
        valid: true,
        correctedAddress: $corrected,
    );

    expect($result->hasCorrectedAddress())->toBeTrue();
    expect($result->correctedAddress)->toBe($corrected);
    expect($result->correctedAddress->line1)->toBe('123 Corrected Street');
});

it('creates result with warnings', function (): void {
    $result = new AddressValidationResult(
        valid: true,
        warnings: ['Address may be residential', 'Postal code format adjusted'],
    );

    expect($result->valid)->toBeTrue();
    expect($result->hasWarnings())->toBeTrue();
    expect($result->warnings)->toBe(['Address may be residential', 'Postal code format adjusted']);
});

it('returns false for hasCorrectedAddress when null', function (): void {
    $result = new AddressValidationResult(valid: true);

    expect($result->hasCorrectedAddress())->toBeFalse();
});

it('returns false for hasWarnings when empty', function (): void {
    $result = new AddressValidationResult(valid: true);

    expect($result->hasWarnings())->toBeFalse();
});

it('returns false for hasErrors when empty', function (): void {
    $result = new AddressValidationResult(valid: true);

    expect($result->hasErrors())->toBeFalse();
});

it('creates result with all fields', function (): void {
    $corrected = new AddressData(
        name: 'John Doe',
        phone: '123456789',
        line1: '123 Corrected Street',
        postcode: '50001',
        country: 'MY',
    );

    $result = new AddressValidationResult(
        valid: true,
        correctedAddress: $corrected,
        warnings: ['Minor adjustment made'],
        errors: [],
    );

    expect($result->isValid())->toBeTrue();
    expect($result->hasCorrectedAddress())->toBeTrue();
    expect($result->hasWarnings())->toBeTrue();
    expect($result->hasErrors())->toBeFalse();
});
