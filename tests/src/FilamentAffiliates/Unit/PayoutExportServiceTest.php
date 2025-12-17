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
        'reference' => 'PAY-' . Str::uuid(),
        'amount_minor' => 15000,
        'currency' => 'USD',
        'status' => PayoutStatus::Pending,
        'owner_type' => $affiliate->getMorphClass(),
        'owner_id' => $affiliate->getKey(),
    ]);

    // Create conversions linked to this payout
    AffiliateConversion::create([
        'affiliate_id' => $affiliate->getKey(),
        'affiliate_code' => $affiliate->code,
        'affiliate_payout_id' => $payout->getKey(),
        'order_reference' => 'ORD-001',
        'total_minor' => 10000,
        'commission_minor' => 500,
        'commission_currency' => 'USD',
        'status' => ConversionStatus::Approved,
        'occurred_at' => now(),
    ]);

    AffiliateConversion::create([
        'affiliate_id' => $affiliate->getKey(),
        'affiliate_code' => $affiliate->code,
        'affiliate_payout_id' => $payout->getKey(),
        'order_reference' => 'ORD-002',
        'total_minor' => 20000,
        'commission_minor' => 1000,
        'commission_currency' => 'USD',
        'status' => ConversionStatus::Paid,
        'occurred_at' => now(),
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

    ob_start();
    $response->sendContent();
    $content = (string) ob_get_clean();

    expect($content)->toContain('Affiliate Code')
        ->and($content)->toContain('ORD-001');
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

    expect($response)->toBeInstanceOf(Symfony\Component\HttpFoundation\StreamedResponse::class);

    ob_start();
    $response->sendContent();
    $content = (string) ob_get_clean();

    // Either a ZIP-based XLSX (starts with PK) or XML spreadsheet fallback.
    expect($content === '' || str_starts_with($content, 'PK') || str_contains($content, '<Workbook'))
        ->toBeTrue();
});

it('builds PDF HTML for a payout (without invoking external PDF generators)', function (): void {
    $payout = createPayoutWithConversions();
    $service = new PayoutExportService;

    $reflection = new ReflectionClass($service);

    $buildExportData = $reflection->getMethod('buildExportData');
    $buildExportData->setAccessible(true);
    /** @var array<int, array<string>> $data */
    $data = $buildExportData->invoke($service, $payout);

    $buildPdfHtml = $reflection->getMethod('buildPdfHtml');
    $buildPdfHtml->setAccessible(true);
    /** @var string $html */
    $html = $buildPdfHtml->invoke($service, $payout, $data);

    expect($html)->toContain('Affiliate Payout Report')
        ->and($html)->toContain('ORD-001');
});

it('formats payout status safely for both enum and string statuses', function (): void {
    $payout = createPayoutWithConversions();
    $service = new PayoutExportService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('getStatusValue');
    $method->setAccessible(true);

    /** @var string $enumStatus */
    $enumStatus = $method->invoke($service, $payout);
    expect($enumStatus)->toBe('pending');

    $payout->status = 'processing';

    /** @var string $stringStatus */
    $stringStatus = $method->invoke($service, $payout);
    expect($stringStatus)->toBe('processing');
});

it('streams an HTML download via the internal fallback', function (): void {
    $payout = createPayoutWithConversions();
    $service = new PayoutExportService;

    $reflection = new ReflectionClass($service);

    $buildExportData = $reflection->getMethod('buildExportData');
    $buildExportData->setAccessible(true);
    /** @var array<int, array<string>> $data */
    $data = $buildExportData->invoke($service, $payout);

    $streamHtml = $reflection->getMethod('streamHtml');
    $streamHtml->setAccessible(true);

    /** @var Symfony\Component\HttpFoundation\StreamedResponse $response */
    $response = $streamHtml->invoke($service, $payout, $data, $payout->reference . '.pdf');

    ob_start();
    $response->sendContent();
    $content = (string) ob_get_clean();

    expect($content)->toContain('Affiliate Payout Report');
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
        'reference' => 'PAY-EMPTY-' . Str::uuid(),
        'amount_minor' => 0,
        'currency' => 'USD',
        'status' => PayoutStatus::Pending,
        'owner_type' => $affiliate->getMorphClass(),
        'owner_id' => $affiliate->getKey(),
    ]);

    $payout = AffiliatePayout::with('conversions')->find($payout->getKey());

    $service = new PayoutExportService;

    $response = $service->downloadCsv($payout);

    expect($response)->toBeInstanceOf(Symfony\Component\HttpFoundation\StreamedResponse::class);
});
