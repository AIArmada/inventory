<?php

declare(strict_types=1);

use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocEInvoiceSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('einvoice submission status helpers', function (): void {
    $sub = DocEInvoiceSubmission::factory()->create([
        'status' => 'pending',
        'validation_status' => null,
    ]);

    expect($sub->isPending())->toBeTrue();
    expect($sub->isSubmitted())->toBeFalse();

    $sub->update(['status' => 'submitted']);
    expect($sub->isSubmitted())->toBeTrue();
});

test('einvoice submission validation helpers', function (): void {
    $sub = DocEInvoiceSubmission::factory()->create([
        'validation_status' => 'valid',
        'errors' => null,
        'warnings' => [],
    ]);

    expect($sub->isValid())->toBeTrue();
    expect($sub->isRejected())->toBeFalse();
    expect($sub->hasErrors())->toBeFalse();
    expect($sub->hasWarnings())->toBeFalse();

    $sub->update([
        'validation_status' => 'invalid',
        'errors' => ['field' => 'error'],
        'warnings' => ['field' => 'warning'],
    ]);

    expect($sub->isValid())->toBeFalse();
    expect($sub->isRejected())->toBeTrue();
    expect($sub->hasErrors())->toBeTrue();
    expect($sub->hasWarnings())->toBeTrue();
});

test('einvoice portal url generation', function (): void {
    // Sandbox default
    config(['docs.einvoice.sandbox' => true]);

    $sub = DocEInvoiceSubmission::factory()->create(['long_id' => '123456']);
    expect($sub->getPortalUrl())->toBe('https://preprod.myinvois.hasil.gov.my/document/123456');

    // Production
    config(['docs.einvoice.sandbox' => false]);
    expect($sub->getPortalUrl())->toBe('https://myinvois.hasil.gov.my/document/123456');

    // No long id
    $sub->update(['long_id' => null]);
    expect($sub->getPortalUrl())->toBeNull();
});

test('einvoice relationships', function (): void {
    $doc = Doc::factory()->create();
    $sub = DocEInvoiceSubmission::factory()->create(['doc_id' => $doc->id]);

    expect($sub->doc->id)->toBe($doc->id);
});
