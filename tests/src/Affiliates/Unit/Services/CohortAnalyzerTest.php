<?php

declare(strict_types=1);

namespace Tests\Affiliates\Unit\Services;

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Services\CohortAnalyzer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Model::unguard();
    $this->analyzer = new CohortAnalyzer();

    // Set fixed time for consistent testing
    Carbon::setTestNow('2024-06-01 12:00:00');
});

test('analyzeMonthly returns correct cohort data', function (): void {
    // Create affiliates joined in Jan 2024 (Cohort 2024-01)
    $jan1 = Affiliate::create([
        'code' => 'JAN1',
        'name' => 'Jan Affiliate 1',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
        'created_at' => '2024-01-05 10:00:00',
    ]);

    $jan2 = Affiliate::create([
        'code' => 'JAN2',
        'name' => 'Jan Affiliate 2',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
        'created_at' => '2024-01-15 10:00:00',
    ]);

    // Create affiliates joined in Feb 2024 (Cohort 2024-02)
    $feb1 = Affiliate::create([
        'code' => 'FEB1',
        'name' => 'Feb Affiliate 1',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
        'created_at' => '2024-02-10 10:00:00',
    ]);

    // Create conversions for Jan cohort in Jan (Month 0)
    AffiliateConversion::create([
        'affiliate_id' => $jan1->id,
        'affiliate_code' => $jan1->code,
        'order_reference' => 'ORD1',
        'occurred_at' => '2024-01-20 10:00:00',
        'total_minor' => 10000, // $100.00
        'commission_minor' => 1000, // $10.00
        'commission_currency' => 'USD',
        'status' => 'approved',

    ]);

    // Create conversions for Jan cohort in Feb (Month 1)
    AffiliateConversion::create([
        'affiliate_id' => $jan1->id,
        'affiliate_code' => $jan1->code,
        'order_reference' => 'ORD2',
        'occurred_at' => '2024-02-20 10:00:00',
        'total_minor' => 5000, // $50.00
        'commission_minor' => 500, // $5.00
        'commission_currency' => 'USD',
        'status' => 'approved',

    ]);

    // Create conversions for Feb cohort in Feb (Month 0)
    AffiliateConversion::create([
        'affiliate_id' => $feb1->id,
        'affiliate_code' => $feb1->code,
        'order_reference' => 'ORD3',
        'occurred_at' => '2024-02-25 10:00:00',
        'total_minor' => 20000, // $200.00
        'commission_minor' => 2000, // $20.00
        'commission_currency' => 'USD',
        'status' => 'approved',

    ]);

    $results = $this->analyzer->analyzeMonthly(
        Carbon::parse('2024-01-01'),
        Carbon::parse('2024-02-29')
    );

    // Assert Cohort 2024-01
    expect($results)->toHaveKey('2024-01');
    $janCohort = $results['2024-01'];

    expect($janCohort['total_affiliates'])->toBe(2)
        ->and($janCohort['active_affiliates'])->toBe(2)
        ->and($janCohort['total_conversions'])->toBe(2) // 1 in Jan + 1 in Feb
        ->and($janCohort['total_revenue'])->toBe(15000)
        ->and($janCohort['total_commissions'])->toBe(1500)
        ->and($janCohort['monthly_breakdown'])->toHaveKey(0) // Jan
        ->and($janCohort['monthly_breakdown'])->toHaveKey(1); // Feb

    // Check Month 0 (Jan) for Jan Cohort
    expect($janCohort['monthly_breakdown'][0]['revenue'])->toBe(10000);
    // Check Month 1 (Feb) for Jan Cohort
    expect($janCohort['monthly_breakdown'][1]['revenue'])->toBe(5000);

    // Assert Cohort 2024-02
    expect($results)->toHaveKey('2024-02');
    $febCohort = $results['2024-02'];

    expect($febCohort['total_affiliates'])->toBe(1)
        ->and($febCohort['total_revenue'])->toBe(20000);
});

test('calculateRetentionCurve returns aggregated data', function (): void {
    // 2 Affiliates in Jan
    $jan1 = Affiliate::create([
        'code' => 'JAN_R1',
        'name' => 'Jan Affiliate Ret 1',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
        'created_at' => '2024-01-05',
    ]);

    $jan2 = Affiliate::create([
        'code' => 'JAN_R2',
        'name' => 'Jan Affiliate Ret 2',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
        'created_at' => '2024-01-15',
    ]);

    // 1 Affiliate in Feb (churned later effectively for test simulation purposes)
    // Actually the logic uses 'status' AND 'disabled_at' relative to period end.
    $feb1 = Affiliate::create([
        'code' => 'FEB_R1',
        'name' => 'Feb Affiliate Ret 1',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
        'created_at' => '2024-02-10',
    ]);

    // Add conversions to create revenue data points
    AffiliateConversion::create([
        'affiliate_id' => $jan1->id,
        'affiliate_code' => $jan1->code,
        'order_reference' => 'RET1',
        'occurred_at' => '2024-01-20', // Month 0 for Jan cohort
        'total_minor' => 10000,
        'commission_minor' => 1000,
        'commission_currency' => 'USD',
        'status' => 'approved',

    ]);

    AffiliateConversion::create([
        'affiliate_id' => $feb1->id,
        'affiliate_code' => $feb1->code,
        'order_reference' => 'RET2',
        'occurred_at' => '2024-02-20', // Month 0 for Feb cohort
        'total_minor' => 20000,
        'commission_minor' => 2000,
        'commission_currency' => 'USD',
        'status' => 'approved',

    ]);

    $curve = $this->analyzer->calculateRetentionCurve(
        Carbon::parse('2024-01-01'),
        Carbon::parse('2024-02-29'),
        3
    );

    // Curve should generally index by month number (0, 1, 2)
    expect($curve)->toHaveKey(0); // Month 0 (First month of existence)

    // Both cohorts exist in their respective Month 0.
    // Jan cohort: Month 0 is Jan. Feb cohort: Month 0 is Feb.
    // Total sample size for Month 0 should be 2 cohorts.

    expect($curve[0]['sample_size'])->toBe(2)
        ->and($curve[0]['avg_revenue'])->toBe((float) ((10000 + 20000) / 2)); // Avg of 10000 and 20000
});

test('calculateLtv returns correct lifetime value metrics', function (): void {
    $aff = Affiliate::create([
        'code' => 'LTV1',
        'name' => 'LTV Affiliate',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
        'created_at' => '2024-01-15',
    ]);

    AffiliateConversion::create([
        'affiliate_id' => $aff->id,
        'affiliate_code' => $aff->code,
        'order_reference' => 'LTV_O1',
        'occurred_at' => '2024-01-20',
        'total_minor' => 12000, // 120.00
        'commission_minor' => 1200,
        'commission_currency' => 'USD',
        'status' => 'approved',

    ]); // Month 0

    AffiliateConversion::create([
        'affiliate_id' => $aff->id,
        'affiliate_code' => $aff->code,
        'order_reference' => 'LTV_O2',
        'occurred_at' => '2024-02-20',
        'total_minor' => 12000, // 120.00
        'commission_minor' => 1200,
        'commission_currency' => 'USD',
        'status' => 'approved',

    ]); // Month 1

    // 2 months of data for this single affiliate
    // Total Revenue: 24000
    // Avg per affiliate: 24000

    $results = $this->analyzer->calculateLtv(
        Carbon::parse('2024-01-01'),
        Carbon::parse('2024-02-29')
    );

    expect($results)->toHaveKey('2024-01');
    $ltv = $results['2024-01'];

    expect($ltv['ltv'])->toBe(24000.0)
        ->and($ltv['projected_annual_ltv'])->toBeGreaterThan(0);
});

test('compareCohorts correctly identifies best and worst cohorts', function (): void {
    // Cohort A (Jan): High performer
    $jan = Affiliate::create([
        'code' => 'COMP_JAN',
        'name' => 'Comp Jan',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
        'created_at' => '2024-01-01',
    ]);
    AffiliateConversion::create([
        'affiliate_id' => $jan->id,
        'affiliate_code' => $jan->code,
        'order_reference' => 'COMP_O1',
        'occurred_at' => '2024-01-15',
        'total_minor' => 100000,
        'commission_minor' => 10000,
        'commission_currency' => 'USD',
        'status' => 'approved',

    ]);

    // Cohort B (Feb): Low performer
    $feb = Affiliate::create([
        'code' => 'COMP_FEB',
        'name' => 'Comp Feb',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
        'created_at' => '2024-02-01',
    ]);
    AffiliateConversion::create([
        'affiliate_id' => $feb->id,
        'affiliate_code' => $feb->code,
        'order_reference' => 'COMP_O2',
        'occurred_at' => '2024-02-15',
        'total_minor' => 1000,
        'commission_minor' => 100,
        'commission_currency' => 'USD',
        'status' => 'approved',

    ]);

    $comparison = $this->analyzer->compareCohorts(
        Carbon::parse('2024-01-01'),
        Carbon::parse('2024-02-29')
    );

    expect($comparison['best_cohort'])->toBe('2024-01')
        ->and($comparison['worst_cohort'])->toBe('2024-02')
        ->and($comparison['trend'])->toBeString();
});

test('compareCohorts handles empty data', function (): void {
    $comparison = $this->analyzer->compareCohorts(
        Carbon::parse('2020-01-01'),
        Carbon::parse('2020-02-01')
    );

    expect($comparison['best_cohort'])->toBeNull()
        ->and($comparison['trend'])->toBe('no_data');
});

test('analyzeBySource groups by metadata source', function (): void {
    $googleAff = Affiliate::create([
        'code' => 'SRC_GOOGLE',
        'name' => 'Google Aff',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
        'created_at' => '2024-01-10',
        'metadata' => ['source' => 'google_ads'],
    ]);

    $directAff = Affiliate::create([
        'code' => 'SRC_DIRECT',
        'name' => 'Direct Aff',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
        'created_at' => '2024-01-12',
        // No metadata or explicit direct
    ]);

    AffiliateConversion::create([
        'affiliate_id' => $googleAff->id,
        'affiliate_code' => $googleAff->code,
        'order_reference' => 'SRC_O1',
        'occurred_at' => '2024-01-15',
        'total_minor' => 5000,
        'commission_minor' => 500,
        'commission_currency' => 'USD',
        'status' => 'approved',

    ]);

    $results = $this->analyzer->analyzeBySource(
        Carbon::parse('2024-01-01'),
        Carbon::parse('2024-01-31')
    );

    // Direct affiliates often default to 'direct' if source is missing in JSON extraction logic
    // "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.source')), 'direct')"

    expect($results)->toHaveKey('google_ads')
        ->and($results)->toHaveKey('direct');

    expect($results['google_ads']['total_revenue'])->toBe(5000)
        ->and($results['direct']['total_affiliates'])->toBe(1);
});
