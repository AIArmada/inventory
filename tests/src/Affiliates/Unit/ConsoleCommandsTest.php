<?php

declare(strict_types=1);

use AIArmada\Affiliates\Console\Commands\AggregateDailyStatsCommand;
use AIArmada\Affiliates\Console\Commands\ExportAffiliatePayoutCommand;
use AIArmada\Affiliates\Console\Commands\ProcessCommissionMaturityCommand;
use AIArmada\Affiliates\Console\Commands\ProcessRankUpgradesCommand;
use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\ConversionStatus;
use AIArmada\Affiliates\Events\DailyStatsAggregated;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliatePayout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;

// AggregateDailyStatsCommand Tests
test('AggregateDailyStatsCommand aggregates stats for yesterday by default', function (): void {
    Event::fake([DailyStatsAggregated::class]);

    Affiliate::create([
        'code' => 'CMD001',
        'name' => 'Command Test Affiliate',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $this->artisan('affiliates:aggregate-daily')
        ->expectsOutputToContain('Aggregating stats')
        ->expectsOutputToContain('Aggregated stats for')
        ->assertSuccessful();

    Event::assertDispatched(DailyStatsAggregated::class);
});

test('AggregateDailyStatsCommand accepts custom date', function (): void {
    Event::fake([DailyStatsAggregated::class]);

    Affiliate::create([
        'code' => 'CMD002',
        'name' => 'Custom Date Test',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $this->artisan('affiliates:aggregate-daily', ['--date' => '2024-01-15'])
        ->expectsOutputToContain('2024-01-15')
        ->assertSuccessful();
});

test('AggregateDailyStatsCommand handles backfill mode', function (): void {
    Affiliate::create([
        'code' => 'CMD003',
        'name' => 'Backfill Test',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $this->artisan('affiliates:aggregate-daily', [
        '--backfill' => true,
        '--from' => now()->subDays(3)->format('Y-m-d'),
        '--to' => now()->subDay()->format('Y-m-d'),
    ])
        ->expectsOutputToContain('Backfilling stats')
        ->expectsOutputToContain('Backfill complete')
        ->assertSuccessful();
});

// ExportAffiliatePayoutCommand Tests
test('ExportAffiliatePayoutCommand fails when payout not found', function (): void {
    $this->artisan('affiliates:payout:export', ['payout' => 'nonexistent'])
        ->expectsOutputToContain('Payout not found')
        ->assertFailed();
});

test('ExportAffiliatePayoutCommand exports payout to CSV', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'EXPORT001',
        'name' => 'Export Test',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $payout = AffiliatePayout::create([
        'affiliate_id' => $affiliate->id,
        'reference' => 'PAY_20240115_001',
        'amount_minor' => 10000,
        'currency' => 'USD',
        'status' => 'pending',
    ]);

    AffiliateConversion::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'payout_id' => $payout->id,
        'order_reference' => 'ORDER_001',
        'total_minor' => 50000,
        'commission_minor' => 5000,
        'commission_currency' => 'USD',
        'status' => ConversionStatus::Approved,
        'occurred_at' => now(),
    ]);

    $path = storage_path('payouts/test_export.csv');

    $this->artisan('affiliates:payout:export', [
        'payout' => 'PAY_20240115_001',
        '--path' => $path,
    ])
        ->expectsOutputToContain('Exported payout')
        ->assertSuccessful();

    expect(File::exists($path))->toBeTrue();

    // Clean up
    File::delete($path);
});

// ProcessRankUpgradesCommand Tests
test('ProcessRankUpgradesCommand runs successfully', function (): void {
    $this->artisan('affiliates:process-ranks')
        ->expectsOutputToContain('Processing rank qualifications')
        ->expectsOutputToContain('Processed rank changes')
        ->assertSuccessful();
});

test('ProcessRankUpgradesCommand handles dry run mode', function (): void {
    $this->artisan('affiliates:process-ranks', ['--dry-run' => true])
        ->expectsOutputToContain('Dry run mode')
        ->assertSuccessful();
});

// ProcessCommissionMaturityCommand Tests
test('ProcessCommissionMaturityCommand runs successfully', function (): void {
    $this->artisan('affiliates:process-maturity')
        ->expectsOutputToContain('Processing commission maturity')
        ->expectsOutputToContain('Matured')
        ->assertSuccessful();
});

test('ProcessCommissionMaturityCommand handles dry run mode', function (): void {
    $this->artisan('affiliates:process-maturity', ['--dry-run' => true])
        ->expectsOutputToContain('Processing commission maturity')
        ->expectsOutputToContain('dry-run mode')
        ->assertSuccessful();
});
