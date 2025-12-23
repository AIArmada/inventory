<?php

declare(strict_types=1);

use AIArmada\Vouchers\Campaigns\Enums\CampaignEventType;
use AIArmada\Vouchers\Campaigns\Enums\CampaignObjective;
use AIArmada\Vouchers\Campaigns\Enums\CampaignStatus;
use AIArmada\Vouchers\Campaigns\Enums\CampaignType;
use AIArmada\Vouchers\Campaigns\Models\Campaign;
use AIArmada\Vouchers\Campaigns\Models\CampaignEvent;
use AIArmada\Vouchers\Campaigns\Models\CampaignVariant;
use AIArmada\Vouchers\Campaigns\Services\CampaignAnalytics;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->analytics = new CampaignAnalytics;

    $this->campaign = Campaign::create([
        'name' => 'Analytics Test Campaign',
        'type' => CampaignType::Promotional,
        'objective' => CampaignObjective::RevenueIncrease,
        'status' => CampaignStatus::Active,
        'budget_cents' => 1000000,
        'spent_cents' => 250000,
        'max_redemptions' => 1000,
        'current_redemptions' => 250,
        'starts_at' => Carbon::now()->subDays(7),
        'ends_at' => Carbon::now()->addDays(7),
        'timezone' => 'UTC',
        'ab_testing_enabled' => true,
    ]);
});

describe('CampaignAnalytics Funnel Metrics', function (): void {
    beforeEach(function (): void {
        // Create 100 impressions
        for ($i = 0; $i < 100; $i++) {
            CampaignEvent::create([
                'campaign_id' => $this->campaign->id,
                'event_type' => CampaignEventType::Impression,
                'occurred_at' => Carbon::now()->subHours(rand(1, 24)),
            ]);
        }

        // Create 30 applications
        for ($i = 0; $i < 30; $i++) {
            CampaignEvent::create([
                'campaign_id' => $this->campaign->id,
                'event_type' => CampaignEventType::Application,
                'voucher_code' => 'CODE' . $i,
                'occurred_at' => Carbon::now()->subHours(rand(1, 24)),
            ]);
        }

        // Create 10 conversions
        for ($i = 0; $i < 10; $i++) {
            CampaignEvent::create([
                'campaign_id' => $this->campaign->id,
                'event_type' => CampaignEventType::Conversion,
                'voucher_code' => 'CODE' . $i,
                'value_cents' => 50000,
                'discount_cents' => 5000,
                'occurred_at' => Carbon::now()->subHours(rand(1, 24)),
            ]);
        }

        // Create 5 abandonments
        for ($i = 0; $i < 5; $i++) {
            CampaignEvent::create([
                'campaign_id' => $this->campaign->id,
                'event_type' => CampaignEventType::Abandonment,
                'voucher_code' => 'ABANDONED' . $i,
                'occurred_at' => Carbon::now()->subHours(rand(1, 24)),
            ]);
        }
    });

    it('calculates funnel metrics correctly', function (): void {
        $funnel = $this->analytics->getFunnelMetrics($this->campaign);

        expect($funnel['impressions'])->toBe(100)
            ->and($funnel['applications'])->toBe(30)
            ->and($funnel['conversions'])->toBe(10)
            ->and($funnel['abandonments'])->toBe(5)
            ->and($funnel['application_rate'])->toBe(30.0) // 30/100 * 100
            ->and($funnel['conversion_rate'])->toBe(33.33) // 10/30 * 100, rounded
            ->and($funnel['abandonment_rate'])->toBe(16.67) // 5/30 * 100, rounded
            ->and($funnel['overall_conversion_rate'])->toBe(10.0); // 10/100 * 100
    });

    it('filters funnel metrics by date range', function (): void {
        // Add older events that should be excluded
        CampaignEvent::create([
            'campaign_id' => $this->campaign->id,
            'event_type' => CampaignEventType::Impression,
            'occurred_at' => Carbon::now()->subDays(30),
        ]);

        $from = Carbon::now()->subDays(2);
        $to = Carbon::now();

        $funnel = $this->analytics->getFunnelMetrics($this->campaign, $from, $to);

        // Only events within the range should be counted
        expect($funnel['impressions'])->toBe(100); // Excludes the old one
    });

    it('returns zero rates when no data', function (): void {
        $emptyCampaign = Campaign::create([
            'name' => 'Empty Campaign',
            'type' => CampaignType::Promotional,
            'objective' => CampaignObjective::RevenueIncrease,
            'status' => CampaignStatus::Draft,
            'spent_cents' => 0,
            'current_redemptions' => 0,
            'timezone' => 'UTC',
        ]);

        $funnel = $this->analytics->getFunnelMetrics($emptyCampaign);

        expect($funnel['impressions'])->toBe(0)
            ->and($funnel['application_rate'])->toBe(0.0)
            ->and($funnel['conversion_rate'])->toBe(0.0);
    });
});

describe('CampaignAnalytics Revenue Metrics', function (): void {
    beforeEach(function (): void {
        // Create conversions with varying values
        $conversions = [
            ['value' => 50000, 'discount' => 5000],
            ['value' => 75000, 'discount' => 7500],
            ['value' => 100000, 'discount' => 10000],
            ['value' => 25000, 'discount' => 2500],
        ];

        foreach ($conversions as $conversion) {
            CampaignEvent::create([
                'campaign_id' => $this->campaign->id,
                'event_type' => CampaignEventType::Conversion,
                'voucher_code' => 'TEST',
                'value_cents' => $conversion['value'],
                'discount_cents' => $conversion['discount'],
                'occurred_at' => Carbon::now()->subHours(rand(1, 24)),
            ]);
        }
    });

    it('calculates revenue metrics correctly', function (): void {
        $revenue = $this->analytics->getRevenueMetrics($this->campaign);

        // Total: 50000 + 75000 + 100000 + 25000 = 250000
        // Discount: 5000 + 7500 + 10000 + 2500 = 25000
        // Net: 250000 - 25000 = 225000
        // AOV: 250000 / 4 = 62500
        // Avg Discount: 25000 / 4 = 6250

        expect($revenue['total_revenue_cents'])->toBe(250000)
            ->and($revenue['total_discount_cents'])->toBe(25000)
            ->and($revenue['net_revenue_cents'])->toBe(225000)
            ->and($revenue['average_order_value_cents'])->toBe(62500.0)
            ->and($revenue['average_discount_cents'])->toBe(6250.0);
    });

    it('calculates ROI percentage', function (): void {
        $revenue = $this->analytics->getRevenueMetrics($this->campaign);

        // ROI: (225000 / 25000) * 100 = 900%
        expect($revenue['roi_percentage'])->toBe(900.0);
    });

    it('returns null for metrics when no conversions', function (): void {
        $emptyCampaign = Campaign::create([
            'name' => 'No Conversions',
            'type' => CampaignType::Promotional,
            'objective' => CampaignObjective::RevenueIncrease,
            'status' => CampaignStatus::Draft,
            'spent_cents' => 0,
            'current_redemptions' => 0,
            'timezone' => 'UTC',
        ]);

        $revenue = $this->analytics->getRevenueMetrics($emptyCampaign);

        expect($revenue['total_revenue_cents'])->toBe(0)
            ->and($revenue['average_order_value_cents'])->toBeNull()
            ->and($revenue['roi_percentage'])->toBeNull();
    });
});

describe('CampaignAnalytics Channel Performance', function (): void {
    beforeEach(function (): void {
        $channels = ['web', 'mobile', 'email'];

        foreach ($channels as $channel) {
            // Impressions
            for ($i = 0; $i < 10; $i++) {
                CampaignEvent::create([
                    'campaign_id' => $this->campaign->id,
                    'event_type' => CampaignEventType::Impression,
                    'channel' => $channel,
                    'occurred_at' => Carbon::now()->subHours(rand(1, 24)),
                ]);
            }

            // Applications
            for ($i = 0; $i < 5; $i++) {
                CampaignEvent::create([
                    'campaign_id' => $this->campaign->id,
                    'event_type' => CampaignEventType::Application,
                    'channel' => $channel,
                    'voucher_code' => $channel . '_CODE',
                    'occurred_at' => Carbon::now()->subHours(rand(1, 24)),
                ]);
            }

            // Conversions (varying by channel)
            $conversions = $channel === 'web' ? 3 : ($channel === 'mobile' ? 2 : 1);
            for ($i = 0; $i < $conversions; $i++) {
                CampaignEvent::create([
                    'campaign_id' => $this->campaign->id,
                    'event_type' => CampaignEventType::Conversion,
                    'channel' => $channel,
                    'voucher_code' => $channel . '_CODE',
                    'value_cents' => 50000,
                    'discount_cents' => 5000,
                    'occurred_at' => Carbon::now()->subHours(rand(1, 24)),
                ]);
            }
        }
    });

    it('breaks down performance by channel', function (): void {
        $channelData = $this->analytics->getChannelPerformance($this->campaign);

        expect($channelData)->toHaveKeys(['web', 'mobile', 'email']);

        // Web channel
        expect($channelData['web']['impressions'])->toBe(10)
            ->and($channelData['web']['applications'])->toBe(5)
            ->and($channelData['web']['conversions'])->toBe(3)
            ->and($channelData['web']['conversion_rate'])->toBe(60.0);

        // Mobile channel
        expect($channelData['mobile']['conversions'])->toBe(2)
            ->and($channelData['mobile']['conversion_rate'])->toBe(40.0);

        // Email channel
        expect($channelData['email']['conversions'])->toBe(1)
            ->and($channelData['email']['conversion_rate'])->toBe(20.0);
    });

    it('calculates channel revenue', function (): void {
        $channelData = $this->analytics->getChannelPerformance($this->campaign);

        // Web: 3 conversions * 50000 = 150000
        expect($channelData['web']['revenue_cents'])->toBe(150000);

        // Mobile: 2 conversions * 50000 = 100000
        expect($channelData['mobile']['revenue_cents'])->toBe(100000);
    });
});

describe('CampaignAnalytics A/B Test Results', function (): void {
    beforeEach(function (): void {
        $this->control = CampaignVariant::create([
            'campaign_id' => $this->campaign->id,
            'name' => 'Control - 10% Off',
            'variant_code' => 'A',
            'traffic_percentage' => 50,
            'is_control' => true,
            'impressions' => 1000,
            'applications' => 200,
            'conversions' => 40,
            'revenue_cents' => 400000,
            'discount_cents' => 40000,
        ]);

        $this->treatment = CampaignVariant::create([
            'campaign_id' => $this->campaign->id,
            'name' => 'Treatment - 15% Off',
            'variant_code' => 'B',
            'traffic_percentage' => 50,
            'is_control' => false,
            'impressions' => 1000,
            'applications' => 200,
            'conversions' => 60,
            'revenue_cents' => 540000,
            'discount_cents' => 81000,
        ]);
    });

    it('returns A/B test results', function (): void {
        $results = $this->analytics->getABTestResults($this->campaign);

        expect($results)->toHaveKeys(['variants', 'winner', 'is_significant', 'confidence', 'recommendation'])
            ->and($results['variants'])->toHaveKeys(['A', 'B']);
    });

    it('includes variant metrics in results', function (): void {
        $results = $this->analytics->getABTestResults($this->campaign);

        $variantA = $results['variants']['A'];
        $variantB = $results['variants']['B'];

        expect($variantA['name'])->toBe('Control - 10% Off')
            ->and($variantA['is_control'])->toBeTrue()
            ->and($variantA['conversion_rate'])->toBe(20.0) // 40/200 * 100
            ->and($variantB['conversion_rate'])->toBe(30.0); // 60/200 * 100
    });

    it('compares treatment to control', function (): void {
        $results = $this->analytics->getABTestResults($this->campaign);

        $variantB = $results['variants']['B'];

        expect($variantB['comparison_to_control'])->not->toBeNull()
            ->and($variantB['comparison_to_control']['conversion_lift'])->toBe(50.0); // (30-20)/20 * 100
    });

    it('returns empty results when A/B testing disabled', function (): void {
        $nonAbCampaign = Campaign::create([
            'name' => 'Non A/B Campaign',
            'type' => CampaignType::Promotional,
            'objective' => CampaignObjective::RevenueIncrease,
            'status' => CampaignStatus::Active,
            'ab_testing_enabled' => false,
            'spent_cents' => 0,
            'current_redemptions' => 0,
            'timezone' => 'UTC',
        ]);

        $results = $this->analytics->getABTestResults($nonAbCampaign);

        expect($results['variants'])->toBeEmpty()
            ->and($results['recommendation'])->toContain('not enabled');
    });

    it('provides recommendation for more data when sample size is small', function (): void {
        $smallCampaign = Campaign::create([
            'name' => 'Small Campaign',
            'type' => CampaignType::Promotional,
            'objective' => CampaignObjective::RevenueIncrease,
            'status' => CampaignStatus::Active,
            'ab_testing_enabled' => true,
            'spent_cents' => 0,
            'current_redemptions' => 0,
            'timezone' => 'UTC',
        ]);

        CampaignVariant::create([
            'campaign_id' => $smallCampaign->id,
            'name' => 'Control',
            'variant_code' => 'A',
            'traffic_percentage' => 50,
            'is_control' => true,
            'impressions' => 20,
            'applications' => 10,
            'conversions' => 2,
            'revenue_cents' => 20000,
            'discount_cents' => 2000,
        ]);

        CampaignVariant::create([
            'campaign_id' => $smallCampaign->id,
            'name' => 'Treatment',
            'variant_code' => 'B',
            'traffic_percentage' => 50,
            'is_control' => false,
            'impressions' => 20,
            'applications' => 10,
            'conversions' => 3,
            'revenue_cents' => 30000,
            'discount_cents' => 3000,
        ]);

        $results = $this->analytics->getABTestResults($smallCampaign);

        expect($results['recommendation'])->toContain('more applications');
    });
});

describe('CampaignAnalytics Performance Summary', function (): void {
    it('returns comprehensive performance summary', function (): void {
        // Add some events
        for ($i = 0; $i < 50; $i++) {
            CampaignEvent::create([
                'campaign_id' => $this->campaign->id,
                'event_type' => CampaignEventType::Impression,
                'occurred_at' => Carbon::now()->subHours(rand(1, 24)),
            ]);
        }

        $summary = $this->analytics->getPerformanceSummary($this->campaign);

        expect($summary)->toHaveKeys([
            'campaign_id',
            'campaign_name',
            'status',
            'is_active',
            'funnel',
            'revenue',
            'budget',
            'redemptions',
            'timeline',
        ]);

        expect($summary['campaign_name'])->toBe('Analytics Test Campaign')
            ->and($summary['status'])->toBe('active')
            ->and($summary['is_active'])->toBeTrue();
    });

    it('calculates budget metrics in summary', function (): void {
        $summary = $this->analytics->getPerformanceSummary($this->campaign);

        expect($summary['budget']['total_cents'])->toBe(1000000)
            ->and($summary['budget']['spent_cents'])->toBe(250000)
            ->and($summary['budget']['remaining_cents'])->toBe(750000)
            ->and($summary['budget']['utilization_percentage'])->toBe(25.0);
    });

    it('calculates redemption metrics in summary', function (): void {
        $summary = $this->analytics->getPerformanceSummary($this->campaign);

        expect($summary['redemptions']['max'])->toBe(1000)
            ->and($summary['redemptions']['current'])->toBe(250)
            ->and($summary['redemptions']['remaining'])->toBe(750);
    });

    it('calculates timeline metrics in summary', function (): void {
        $summary = $this->analytics->getPerformanceSummary($this->campaign);

        // Verify that the days are calculated (approximately 7 days given our setup)
        expect($summary['timeline']['days_since_start'])->toBeGreaterThanOrEqual(6)
            ->and($summary['timeline']['days_since_start'])->toBeLessThanOrEqual(8)
            ->and($summary['timeline']['days_remaining'])->toBeGreaterThanOrEqual(6)
            ->and($summary['timeline']['days_remaining'])->toBeLessThanOrEqual(8);
    });
});

describe('CampaignAnalytics Campaign Comparison', function (): void {
    it('compares multiple campaigns', function (): void {
        // Create a second campaign
        $campaign2 = Campaign::create([
            'name' => 'Second Campaign',
            'type' => CampaignType::Flash,
            'objective' => CampaignObjective::InventoryClearance,
            'status' => CampaignStatus::Active,
            'budget_cents' => 500000,
            'spent_cents' => 100000,
            'timezone' => 'UTC',
            'ab_testing_enabled' => false,
        ]);

        // Add events to first campaign
        CampaignEvent::create([
            'campaign_id' => $this->campaign->id,
            'event_type' => CampaignEventType::Conversion,
            'value_cents' => 100000,
            'discount_cents' => 10000,
            'occurred_at' => Carbon::now(),
        ]);

        // Add events to second campaign
        CampaignEvent::create([
            'campaign_id' => $campaign2->id,
            'event_type' => CampaignEventType::Conversion,
            'value_cents' => 50000,
            'discount_cents' => 5000,
            'occurred_at' => Carbon::now(),
        ]);

        $campaigns = Campaign::whereIn('id', [$this->campaign->id, $campaign2->id])->get();
        $comparison = $this->analytics->compareCampaigns($campaigns);

        expect($comparison)->toHaveCount(2)
            ->and($comparison[$this->campaign->id]['name'])->toBe('Analytics Test Campaign')
            ->and($comparison[$campaign2->id]['name'])->toBe('Second Campaign')
            ->and($comparison[$this->campaign->id]['revenue_cents'])->toBe(100000)
            ->and($comparison[$campaign2->id]['revenue_cents'])->toBe(50000);
    });
});

describe('CampaignAnalytics Time Series', function (): void {
    it('returns time series grouped by day', function (): void {
        $from = Carbon::now()->subDays(2)->startOfDay();
        $to = Carbon::now()->endOfDay();

        CampaignEvent::create([
            'campaign_id' => $this->campaign->id,
            'event_type' => CampaignEventType::Impression,
            'occurred_at' => $from->copy()->addHours(1),
        ]);

        CampaignEvent::create([
            'campaign_id' => $this->campaign->id,
            'event_type' => CampaignEventType::Application,
            'voucher_code' => 'TS1',
            'occurred_at' => $from->copy()->addHours(2),
        ]);

        CampaignEvent::create([
            'campaign_id' => $this->campaign->id,
            'event_type' => CampaignEventType::Conversion,
            'voucher_code' => 'TS1',
            'value_cents' => 50000,
            'discount_cents' => 5000,
            'occurred_at' => $from->copy()->addHours(3),
        ]);

        $series = $this->analytics->getTimeSeries($this->campaign, $from, $to, 'day');

        $key = $from->format('Y-m-d');

        expect($series)->toHaveKey($key)
            ->and($series[$key]['impressions'])->toBe(1)
            ->and($series[$key]['applications'])->toBe(1)
            ->and($series[$key]['conversions'])->toBe(1)
            ->and($series[$key]['revenue_cents'])->toBe(50000);
    });
});
