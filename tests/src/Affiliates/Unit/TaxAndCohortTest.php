<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateTaxDocument;
use AIArmada\Affiliates\Services\CohortAnalyzer;
use AIArmada\Affiliates\Services\Tax\Tax1099Generator;
use AIArmada\Affiliates\Services\Tax\TaxDocumentService;
use Illuminate\Support\Carbon;

// Tax1099Generator Tests
test('Tax1099Generator can be instantiated', function (): void {
    $generator = app(Tax1099Generator::class);

    expect($generator)->toBeInstanceOf(Tax1099Generator::class);
});

// TaxDocumentService Tests
test('TaxDocumentService can be instantiated', function (): void {
    $service = app(TaxDocumentService::class);

    expect($service)->toBeInstanceOf(TaxDocumentService::class);
});

test('TaxDocumentService calculateAnnualPayouts returns int', function (): void {
    $service = app(TaxDocumentService::class);

    $affiliate = Affiliate::create([
        'code' => 'TAXCALC001',
        'name' => 'Tax Calc Test',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $total = $service->calculateAnnualPayouts($affiliate, 2024);

    expect($total)->toBeInt();
    expect($total)->toBeGreaterThanOrEqual(0);
});

test('TaxDocumentService getDocumentsForAffiliate returns collection', function (): void {
    $service = app(TaxDocumentService::class);

    $affiliate = Affiliate::create([
        'code' => 'TAXDOCS001',
        'name' => 'Tax Docs Test',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $documents = $service->getDocumentsForAffiliate($affiliate);

    expect($documents)->toBeInstanceOf(\Illuminate\Support\Collection::class);
});

test('TaxDocumentService markDocumentAsSent updates status', function (): void {
    $service = app(TaxDocumentService::class);

    $affiliate = Affiliate::create([
        'code' => 'TAXSENT001',
        'name' => 'Tax Sent Test',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $document = AffiliateTaxDocument::create([
        'affiliate_id' => $affiliate->id,
        'document_type' => '1099-NEC',
        'tax_year' => 2024,
        'status' => 'generated',
        'total_amount_minor' => 100000,
        'currency' => 'USD',
    ]);

    $updated = $service->markDocumentAsSent($document);

    expect($updated->status)->toBe('sent');
    expect($updated->sent_at)->not->toBeNull();
});

test('TaxDocumentService generate1099ForAffiliate returns null for below threshold', function (): void {
    $service = app(TaxDocumentService::class);

    config(['affiliates.tax.1099_threshold' => 60000]);

    $affiliate = Affiliate::create([
        'code' => 'TAXBELOW001',
        'name' => 'Below Threshold Test',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    // No payouts, so below threshold
    $document = $service->generate1099ForAffiliate($affiliate, 2024);

    expect($document)->toBeNull();
});

// CohortAnalyzer Tests - Only instantiation test (other methods use MySQL-specific DATE_FORMAT)
test('CohortAnalyzer can be instantiated', function (): void {
    $analyzer = app(CohortAnalyzer::class);

    expect($analyzer)->toBeInstanceOf(CohortAnalyzer::class);
});
