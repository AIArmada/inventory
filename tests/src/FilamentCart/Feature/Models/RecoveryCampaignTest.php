<?php

declare(strict_types=1);

use AIArmada\FilamentCart\Models\RecoveryCampaign;
use AIArmada\FilamentCart\Models\RecoveryAttempt;
use AIArmada\FilamentCart\Models\RecoveryTemplate;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::create(2025, 1, 15, 12, 0, 0));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

describe('RecoveryCampaign', function (): void {
    it('can be created with required attributes', function (): void {
        $campaign = RecoveryCampaign::create([
            'name' => 'Winter Recovery Campaign',
            'description' => 'Recover abandoned carts during winter sale',
            'status' => 'active',
            'trigger_type' => 'abandonment',
            'trigger_delay_minutes' => 60,
            'max_attempts' => 3,
            'attempt_interval_hours' => 24,
            'strategy' => 'email',
        ]);

        expect($campaign)->toBeInstanceOf(RecoveryCampaign::class);
        expect($campaign->id)->not->toBeNull();
        expect($campaign->name)->toBe('Winter Recovery Campaign');
        expect($campaign->status)->toBe('active');
    });

    it('returns table name from config', function (): void {
        $campaign = new RecoveryCampaign();
        $tableName = $campaign->getTable();

        expect($tableName)->toContain('recovery_campaigns');
    });

    it('checks if campaign is active correctly', function (): void {
        // Active campaign without date restrictions
        $activeCampaign = RecoveryCampaign::create([
            'name' => 'Active Campaign',
            'status' => 'active',
            'trigger_type' => 'abandonment',
            'trigger_delay_minutes' => 60,
            'max_attempts' => 3,
            'attempt_interval_hours' => 24,
            'strategy' => 'email',
        ]);

        expect($activeCampaign->isActive())->toBeTrue();

        // Inactive campaign
        $inactiveCampaign = RecoveryCampaign::create([
            'name' => 'Inactive Campaign',
            'status' => 'paused',
            'trigger_type' => 'abandonment',
            'trigger_delay_minutes' => 60,
            'max_attempts' => 3,
            'attempt_interval_hours' => 24,
            'strategy' => 'email',
        ]);

        expect($inactiveCampaign->isActive())->toBeFalse();
    });

    it('checks date range for active status', function (): void {
        // Future campaign
        $futureCampaign = RecoveryCampaign::create([
            'name' => 'Future Campaign',
            'status' => 'active',
            'trigger_type' => 'abandonment',
            'trigger_delay_minutes' => 60,
            'max_attempts' => 3,
            'attempt_interval_hours' => 24,
            'strategy' => 'email',
            'starts_at' => now()->addDays(7),
        ]);

        expect($futureCampaign->isActive())->toBeFalse();

        // Expired campaign
        $expiredCampaign = RecoveryCampaign::create([
            'name' => 'Expired Campaign',
            'status' => 'active',
            'trigger_type' => 'abandonment',
            'trigger_delay_minutes' => 60,
            'max_attempts' => 3,
            'attempt_interval_hours' => 24,
            'strategy' => 'email',
            'ends_at' => now()->subDays(1),
        ]);

        expect($expiredCampaign->isActive())->toBeFalse();
    });

    it('calculates open rate correctly', function (): void {
        $campaign = RecoveryCampaign::create([
            'name' => 'Test Campaign',
            'status' => 'active',
            'trigger_type' => 'abandonment',
            'trigger_delay_minutes' => 60,
            'max_attempts' => 3,
            'attempt_interval_hours' => 24,
            'strategy' => 'email',
            'total_sent' => 100,
            'total_opened' => 40,
        ]);

        expect($campaign->getOpenRate())->toBe(0.4);
    });

    it('handles zero sent for rate calculations', function (): void {
        $campaign = RecoveryCampaign::create([
            'name' => 'New Campaign',
            'status' => 'draft',
            'trigger_type' => 'abandonment',
            'trigger_delay_minutes' => 60,
            'max_attempts' => 3,
            'attempt_interval_hours' => 24,
            'strategy' => 'email',
            'total_sent' => 0,
        ]);

        expect($campaign->getOpenRate())->toBe(0.0);
        expect($campaign->getClickRate())->toBe(0.0);
        expect($campaign->getConversionRate())->toBe(0.0);
    });

    it('calculates click rate correctly', function (): void {
        $campaign = RecoveryCampaign::create([
            'name' => 'Test Campaign',
            'status' => 'active',
            'trigger_type' => 'abandonment',
            'trigger_delay_minutes' => 60,
            'max_attempts' => 3,
            'attempt_interval_hours' => 24,
            'strategy' => 'email',
            'total_sent' => 100,
            'total_clicked' => 25,
        ]);

        expect($campaign->getClickRate())->toBe(0.25);
    });

    it('calculates conversion rate correctly', function (): void {
        $campaign = RecoveryCampaign::create([
            'name' => 'Test Campaign',
            'status' => 'active',
            'trigger_type' => 'abandonment',
            'trigger_delay_minutes' => 60,
            'max_attempts' => 3,
            'attempt_interval_hours' => 24,
            'strategy' => 'email',
            'total_sent' => 100,
            'total_recovered' => 10,
        ]);

        expect($campaign->getConversionRate())->toBe(0.1);
    });

    it('calculates average recovered value', function (): void {
        $campaign = RecoveryCampaign::create([
            'name' => 'Test Campaign',
            'status' => 'active',
            'trigger_type' => 'abandonment',
            'trigger_delay_minutes' => 60,
            'max_attempts' => 3,
            'attempt_interval_hours' => 24,
            'strategy' => 'email',
            'total_recovered' => 10,
            'recovered_revenue_cents' => 100000, // $1000
        ]);

        expect($campaign->getAverageRecoveredValue())->toBe(10000); // $100 average
    });

    it('handles zero recovered for average value', function (): void {
        $campaign = RecoveryCampaign::create([
            'name' => 'New Campaign',
            'status' => 'draft',
            'trigger_type' => 'abandonment',
            'trigger_delay_minutes' => 60,
            'max_attempts' => 3,
            'attempt_interval_hours' => 24,
            'strategy' => 'email',
            'total_recovered' => 0,
        ]);

        expect($campaign->getAverageRecoveredValue())->toBe(0);
    });

    it('has relationship to attempts', function (): void {
        $campaign = RecoveryCampaign::create([
            'name' => 'Test Campaign',
            'status' => 'active',
            'trigger_type' => 'abandonment',
            'trigger_delay_minutes' => 60,
            'max_attempts' => 3,
            'attempt_interval_hours' => 24,
            'strategy' => 'email',
        ]);

        expect($campaign->attempts())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    });
});
