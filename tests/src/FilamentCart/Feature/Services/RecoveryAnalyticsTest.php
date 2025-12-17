<?php

declare(strict_types=1);

use AIArmada\FilamentCart\Data\CampaignMetrics;
use AIArmada\FilamentCart\Models\RecoveryAttempt;
use AIArmada\FilamentCart\Models\RecoveryCampaign;
use AIArmada\FilamentCart\Services\RecoveryAnalytics;
use AIArmada\FilamentCart\Data\RecoveryInsight;
use AIArmada\FilamentCart\Models\Cart;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::create(2025, 1, 15, 12, 0, 0));
    // Setup shared instances for this scope
    $this->analytics = new RecoveryAnalytics();

    $this->campaign = RecoveryCampaign::create([
        'name' => 'Test Campaign',
        'status' => 'active',
        'strategy' => 'email',
        'ab_testing_enabled' => true,
        'trigger_type' => 'abandonment',
        'total_targeted' => 0,
        'total_sent' => 0,
    ]);

    $this->cart = Cart::create([
        'instance' => 'default',
        'identifier' => 'dummy-cart',
        'currency' => 'USD',
    ]);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

describe('RecoveryAnalytics', function (): void {
    it('calculates campaign metrics', function (): void {
        RecoveryAttempt::create([
            'campaign_id' => $this->campaign->id,
            'cart_id' => $this->cart->id,
            'channel' => 'email',
            'status' => 'converted',
            'attempt_number' => 1,
            'opened_at' => now(),
            'clicked_at' => now(),
            'converted_at' => now(),
        ]);

        RecoveryAttempt::create([
            'campaign_id' => $this->campaign->id,
            'cart_id' => $this->cart->id,
            'channel' => 'email',
            'status' => 'sent',
            'attempt_number' => 2,
            'sent_at' => now(),
        ]);

        // Update campaign stats
        $this->campaign->update([
            'total_sent' => 2,
            'total_opened' => 1,
            'total_clicked' => 1,
            'total_recovered' => 1,
            'recovered_revenue_cents' => 5000,
        ]);

        $metrics = $this->analytics->getCampaignMetrics($this->campaign);

        expect($metrics)->toBeInstanceOf(CampaignMetrics::class);
        expect($metrics->total_sent)->toBe(2);
        expect($metrics->total_recovered)->toBe(1);
        expect($metrics->conversion_rate)->toBe(0.5);

        expect($metrics->by_channel)->toHaveKey('email');
        expect($metrics->by_channel['email']['conversions'])->toBe(1);

        expect($metrics->by_attempt_number)->toHaveKey('Attempt 1');
        expect($metrics->by_attempt_number['Attempt 1']['conversions'])->toBe(1);
    });

    it('calculates AB test results', function (): void {
        // Control group (100 sent, 10 converted)
        for ($i = 0; $i < 100; $i++) {
            RecoveryAttempt::create([
                'campaign_id' => $this->campaign->id,
                'cart_id' => $this->cart->id,
                'channel' => 'email',
                'is_control' => true,
                'status' => $i < 10 ? 'converted' : 'sent',
                'sent_at' => now(),
                'converted_at' => $i < 10 ? now() : null,
            ]);
        }

        // Variant group (100 sent, 20 converted)
        for ($i = 0; $i < 100; $i++) {
            RecoveryAttempt::create([
                'campaign_id' => $this->campaign->id,
                'cart_id' => $this->cart->id,
                'channel' => 'email',
                'is_variant' => true,
                'status' => $i < 20 ? 'converted' : 'sent',
                'sent_at' => now(),
                'converted_at' => $i < 20 ? now() : null,
            ]);
        }

        $results = $this->analytics->getAbTestResults($this->campaign);

        expect($results['control']['sent'])->toBe(100);
        expect($results['control']['conversions'])->toBe(10);
        expect($results['control']['conversion_rate'])->toBe(10.0);

        expect($results['variant']['sent'])->toBe(100);
        expect($results['variant']['conversions'])->toBe(20);
        expect($results['variant']['conversion_rate'])->toBe(20.0);

        expect($results['winner'])->toBeIn(['variant', null]);
    });

    it('gets strategy comparison', function (): void {
        RecoveryCampaign::create([
            'name' => 'Email Campaign',
            'status' => 'active',
            'strategy' => 'email',
            'trigger_type' => 'abandonment',
            'total_sent' => 100,
            'total_recovered' => 10,
            'recovered_revenue_cents' => 10000,
            'created_at' => now()->subDay(),
        ]);

        RecoveryCampaign::create([
            'name' => 'SMS Campaign',
            'status' => 'active',
            'strategy' => 'sms',
            'trigger_type' => 'abandonment',
            'total_sent' => 50,
            'total_recovered' => 2,
            'recovered_revenue_cents' => 2000,
            'created_at' => now()->subDay(),
        ]);

        $comparison = $this->analytics->getStrategyComparison(now()->subWeek(), now());

        expect($comparison)->toHaveCount(2);

        $emailStats = $comparison->firstWhere('strategy', 'email');
        expect($emailStats['conversion_rate'])->toBe(10.0);

        $smsStats = $comparison->firstWhere('strategy', 'sms');
        expect($smsStats['conversion_rate'])->toBe(4.0);
    });

    it('generates insights with timing data', function (): void {
        // Create attempts sent at 10 AM with high conversion
        $sentTime = now()->setTime(10, 0, 0);

        for ($i = 0; $i < 10; $i++) {
            RecoveryAttempt::create([
                'campaign_id' => $this->campaign->id,
                'cart_id' => $this->cart->id,
                'channel' => 'email',
                'sent_at' => $sentTime,
                'converted_at' => $i < 5 ? now() : null, // 50% conversion
            ]);
        }

        $insights = $this->analytics->generateInsights($this->campaign);

        $timing = $insights->first(fn(RecoveryInsight $i) => $i->type === 'timing');

        expect($timing)->not->toBeNull();
        expect($timing->recommendation)->toContain('10:00');
    });

    it('generates strategy insights', function (): void {
        // Create history
        RecoveryCampaign::create([
            'name' => 'SMS Campaign',
            'status' => 'active',
            'strategy' => 'sms',
            'trigger_type' => 'abandonment',
            'total_sent' => 500,
            'total_recovered' => 100,
            'recovered_revenue_cents' => 2000,
            'created_at' => now()->subDay(),
        ]);

        RecoveryCampaign::create([
            'name' => 'Email Campaign Old',
            'status' => 'active',
            'strategy' => 'email',
            'trigger_type' => 'abandonment',
            'total_sent' => 500,
            'total_recovered' => 5,
            'recovered_revenue_cents' => 2000,
            'created_at' => now()->subDay(),
        ]);

        $insights = $this->analytics->generateInsights($this->campaign);

        $strategy = $insights->first(fn(RecoveryInsight $i) => $i->type === 'strategy');

        expect($strategy)->not->toBeNull();
        // Access via data array for properties not in main class body
        expect($strategy->data['suggested_strategy'])->toBe('sms');
    });

    it('generates discount insights', function (): void {
        $this->campaign->update(['offer_discount' => true, 'discount_value' => 5, 'discount_type' => 'percentage']);

        RecoveryCampaign::create([
            'name' => '10% Off',
            'status' => 'active',
            'offer_discount' => true,
            'discount_value' => 10,
            'discount_type' => 'percentage',
            'trigger_type' => 'abandonment',
            'total_sent' => 100,
            'total_recovered' => 20,
            'created_at' => now()->subDay(),
        ]);

        RecoveryCampaign::create([
            'name' => '5% Off',
            'status' => 'active',
            'offer_discount' => true,
            'discount_value' => 5,
            'discount_type' => 'percentage',
            'trigger_type' => 'abandonment',
            'total_sent' => 100,
            'total_recovered' => 5,
            'created_at' => now()->subDay(),
        ]);

        $insights = $this->analytics->generateInsights($this->campaign);

        $discount = $insights->first(fn(RecoveryInsight $i) => $i->type === 'discount');

        expect($discount)->not->toBeNull();
        expect($discount->data['suggested_discount_percent'])->toBe(10);
    });

    it('generates targeting insights', function (): void {
        for ($i = 0; $i < 20; $i++) {
            RecoveryAttempt::create([
                'campaign_id' => $this->campaign->id,
                'cart_id' => Cart::create(['identifier' => "high-$i", 'instance' => 'default', 'subtotal' => 20000])->id,
                'channel' => 'email',
                'cart_value_cents' => 20000,
                'sent_at' => now(),
                'converted_at' => $i < 10 ? now() : null, // 50% rate
            ]);
        }

        for ($i = 0; $i < 20; $i++) {
            RecoveryAttempt::create([
                'campaign_id' => $this->campaign->id,
                'cart_id' => Cart::create(['identifier' => "low-$i", 'instance' => 'default', 'subtotal' => 1000])->id,
                'channel' => 'email',
                'cart_value_cents' => 1000,
                'sent_at' => now(),
                'converted_at' => $i < 1 ? now() : null, // 5% rate
            ]);
        }

        $insights = $this->analytics->generateInsights($this->campaign);

        $targeting = $insights->first(fn(RecoveryInsight $i) => $i->type === 'targeting');

        expect($targeting)->not->toBeNull();
        expect($targeting->data['segment_to_focus'])->toBe('high');
    });
});
