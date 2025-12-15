<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\ConversionStatus;
use AIArmada\Affiliates\Enums\PayoutStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\FilamentAffiliates\Services\PayoutExportService;
use Illuminate\Support\Str;

beforeEach(function (): void {
    AffiliatePayout::query()->delete();
    AffiliateConversion::query()->delete();
    Affiliate::query()->delete();
});

function createPayoutWithConversions(): AffiliatePayout
{
    $affiliate = Affiliate::create([
        'code' => 'EXPORT-' . Str::uuid(),
        'name' => 'Export Test Affiliate',
        'status' => 'active',
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);

    $payout = AffiliatePayout::create([
        'affiliate_id' => $affiliate->getKey(),
        'reference' => 'PAY-' . Str::uuid(),
        'amount_minor' => 15000,
        'currency' => 'USD',
        'status' => PayoutStatus::Pending,
    ]);

    // Create conversions linked to this payout
    AffiliateConversion::create([
        'affiliate_id' => $affiliate->getKey(),
        'affiliate_code' => $affiliate->code,
        'payout_id' => $payout->getKey(),
        'order_reference' => 'ORD-001',
        'total_minor' => 10000,
        'commission_minor' => 500,
        'commission_currency' => 'USD',
        'status' => ConversionStatus::Approved,
    ]);

    AffiliateConversion::create([
        'affiliate_id' => $affiliate->getKey(),
        'affiliate_code' => $affiliate->code,
        'payout_id' => $payout->getKey(),
        'order_reference' => 'ORD-002',
        'total_minor' => 20000,
        'commission_minor' => 1000,
        'commission_currency' => 'USD',
        'status' => ConversionStatus::Paid,
    ]);

    // Reload with conversions
    return AffiliatePayout::with('conversions')->find($payout->getKey());
}

it('downloads payout as CSV', function (): void {
    $payout = createPayoutWithConversions();
    $service = new PayoutExportService;

    $response = $service->downloadCsv($payout);

    expect($response)->toBeInstanceOf(Symfony\Component\HttpFoundation\StreamedResponse::class)
        ->and($response->headers->get('Content-Type'))->toBe('text/csv');
});

it('download method returns CSV format (backward compatibility)', function (): void {
    $payout = createPayoutWithConversions();
    $service = new PayoutExportService;

    $response = $service->download($payout);

    expect($response)->toBeInstanceOf(Symfony\Component\HttpFoundation\StreamedResponse::class)
        ->and($response->headers->get('Content-Type'))->toBe('text/csv');
});

it('downloads payout as Excel (XML fallback)', function (): void {
    $payout = createPayoutWithConversions();
    $service = new PayoutExportService;

    $response = $service->downloadExcel($payout);

    expect($response)->toBeInstanceOf(Symfony\Component\HttpFoundation\StreamedResponse::class)
        ->and($response->headers->get('Content-Type'))->toBe('application/vnd.ms-excel');
});

it('downloads payout as PDF (HTML fallback)', function (): void {
    $payout = createPayoutWithConversions();
    $service = new PayoutExportService;

    $response = $service->downloadPdf($payout);

    // Will fallback to HTML since no PDF library is likely installed in tests
    expect($response)->toBeInstanceOf(Symfony\Component\HttpFoundation\StreamedResponse::class);
});

it('handles payouts with zero conversions', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'EMPTY-' . Str::uuid(),
        'name' => 'Empty Test Affiliate',
        'status' => 'active',
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);

    $payout = AffiliatePayout::create([
        'affiliate_id' => $affiliate->getKey(),
        'reference' => 'PAY-EMPTY-' . Str::uuid(),
        'amount_minor' => 0,
        'currency' => 'USD',
        'status' => PayoutStatus::Pending,
    ]);

    $payout = AffiliatePayout::with('conversions')->find($payout->getKey());

    $service = new PayoutExportService;

    $response = $service->downloadCsv($payout);

    expect($response)->toBeInstanceOf(Symfony\Component\HttpFoundation\StreamedResponse::class);
});
