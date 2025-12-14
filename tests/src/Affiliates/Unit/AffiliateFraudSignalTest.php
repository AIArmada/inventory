<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\ConversionStatus;
use AIArmada\Affiliates\Enums\FraudSeverity;
use AIArmada\Affiliates\Enums\FraudSignalStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateFraudSignal;
use AIArmada\Affiliates\Models\AffiliateTouchpoint;

describe('AffiliateFraudSignal Model', function (): void {
    beforeEach(function (): void {
        $this->affiliate = Affiliate::create([
            'code' => 'FRAUD' . uniqid(),
            'name' => 'Fraud Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => 'percentage',
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);
    });

    test('can be created with required fields', function (): void {
        $signal = AffiliateFraudSignal::create([
            'affiliate_id' => $this->affiliate->id,
            'rule_code' => 'RAPID_CLICKS',
            'risk_points' => 50,
            'severity' => FraudSeverity::Medium,
            'description' => 'Rapid click pattern detected',
            'status' => FraudSignalStatus::Detected,
            'detected_at' => now(),
        ]);

        expect($signal)->toBeInstanceOf(AffiliateFraudSignal::class);
        expect($signal->rule_code)->toBe('RAPID_CLICKS');
        expect($signal->risk_points)->toBe(50);
        expect($signal->severity)->toBe(FraudSeverity::Medium);
    });

    test('belongs to affiliate', function (): void {
        $signal = AffiliateFraudSignal::create([
            'affiliate_id' => $this->affiliate->id,
            'rule_code' => 'RAPID_CLICKS',
            'risk_points' => 50,
            'severity' => FraudSeverity::Medium,
            'description' => 'Test signal',
            'status' => FraudSignalStatus::Detected,
            'detected_at' => now(),
        ]);

        expect($signal->affiliate)->toBeInstanceOf(Affiliate::class);
        expect($signal->affiliate->id)->toBe($this->affiliate->id);
    });

    test('belongs to conversion when set', function (): void {
        $conversion = AffiliateConversion::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'order_reference' => 'ORD-FRAUD-001',
            'total_minor' => 50000,
            'commission_minor' => 5000,
            'commission_currency' => 'USD',
            'status' => ConversionStatus::Pending,
            'occurred_at' => now(),
        ]);

        $signal = AffiliateFraudSignal::create([
            'affiliate_id' => $this->affiliate->id,
            'conversion_id' => $conversion->id,
            'rule_code' => 'SELF_REFERRAL',
            'risk_points' => 100,
            'severity' => FraudSeverity::High,
            'description' => 'Self-referral detected',
            'status' => FraudSignalStatus::Detected,
            'detected_at' => now(),
        ]);

        expect($signal->conversion)->toBeInstanceOf(AffiliateConversion::class);
        expect($signal->conversion->id)->toBe($conversion->id);
    });

    test('belongs to touchpoint when set', function (): void {
        $attribution = \AIArmada\Affiliates\Models\AffiliateAttribution::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'visitor_fingerprint' => 'touchpoint123',
            'first_click_at' => now(),
            'last_click_at' => now(),
        ]);

        $touchpoint = AffiliateTouchpoint::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_attribution_id' => $attribution->id,
            'affiliate_code' => $this->affiliate->code,
            'visitor_fingerprint' => 'fingerprint123',
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0',
            'channel' => 'web',
            'entry_url' => 'https://example.com',
            'occurred_at' => now(),
        ]);

        $signal = AffiliateFraudSignal::create([
            'affiliate_id' => $this->affiliate->id,
            'touchpoint_id' => $touchpoint->id,
            'rule_code' => 'SUSPICIOUS_IP',
            'risk_points' => 30,
            'severity' => FraudSeverity::Low,
            'description' => 'Suspicious IP detected',
            'status' => FraudSignalStatus::Detected,
            'detected_at' => now(),
        ]);

        expect($signal->touchpoint)->toBeInstanceOf(AffiliateTouchpoint::class);
        expect($signal->touchpoint->id)->toBe($touchpoint->id);
    });

    test('scopePending returns only detected signals', function (): void {
        AffiliateFraudSignal::create([
            'affiliate_id' => $this->affiliate->id,
            'rule_code' => 'PENDING1',
            'risk_points' => 50,
            'severity' => FraudSeverity::Medium,
            'description' => 'Pending signal',
            'status' => FraudSignalStatus::Detected,
            'detected_at' => now(),
        ]);

        AffiliateFraudSignal::create([
            'affiliate_id' => $this->affiliate->id,
            'rule_code' => 'REVIEWED1',
            'risk_points' => 50,
            'severity' => FraudSeverity::Medium,
            'description' => 'Reviewed signal',
            'status' => FraudSignalStatus::Reviewed,
            'detected_at' => now(),
        ]);

        $pending = AffiliateFraudSignal::pending()->get();

        expect($pending)->toHaveCount(1);
        expect($pending->first()->rule_code)->toBe('PENDING1');
    });

    test('scopeConfirmed returns only confirmed signals', function (): void {
        AffiliateFraudSignal::create([
            'affiliate_id' => $this->affiliate->id,
            'rule_code' => 'DETECTED1',
            'risk_points' => 50,
            'severity' => FraudSeverity::Medium,
            'description' => 'Detected signal',
            'status' => FraudSignalStatus::Detected,
            'detected_at' => now(),
        ]);

        AffiliateFraudSignal::create([
            'affiliate_id' => $this->affiliate->id,
            'rule_code' => 'CONFIRMED1',
            'risk_points' => 100,
            'severity' => FraudSeverity::High,
            'description' => 'Confirmed signal',
            'status' => FraudSignalStatus::Confirmed,
            'detected_at' => now(),
        ]);

        $confirmed = AffiliateFraudSignal::confirmed()->get();

        expect($confirmed)->toHaveCount(1);
        expect($confirmed->first()->rule_code)->toBe('CONFIRMED1');
    });

    test('scopeHighSeverity returns high and critical signals', function (): void {
        AffiliateFraudSignal::create([
            'affiliate_id' => $this->affiliate->id,
            'rule_code' => 'LOW1',
            'risk_points' => 10,
            'severity' => FraudSeverity::Low,
            'description' => 'Low severity',
            'status' => FraudSignalStatus::Detected,
            'detected_at' => now(),
        ]);

        AffiliateFraudSignal::create([
            'affiliate_id' => $this->affiliate->id,
            'rule_code' => 'HIGH1',
            'risk_points' => 80,
            'severity' => FraudSeverity::High,
            'description' => 'High severity',
            'status' => FraudSignalStatus::Detected,
            'detected_at' => now(),
        ]);

        AffiliateFraudSignal::create([
            'affiliate_id' => $this->affiliate->id,
            'rule_code' => 'CRITICAL1',
            'risk_points' => 100,
            'severity' => FraudSeverity::Critical,
            'description' => 'Critical severity',
            'status' => FraudSignalStatus::Detected,
            'detected_at' => now(),
        ]);

        $highSeverity = AffiliateFraudSignal::highSeverity()->get();

        expect($highSeverity)->toHaveCount(2);
        expect($highSeverity->pluck('rule_code')->toArray())->toContain('HIGH1');
        expect($highSeverity->pluck('rule_code')->toArray())->toContain('CRITICAL1');
    });

    test('markAsReviewed updates status and timestamps', function (): void {
        $signal = AffiliateFraudSignal::create([
            'affiliate_id' => $this->affiliate->id,
            'rule_code' => 'REVIEW_TEST',
            'risk_points' => 50,
            'severity' => FraudSeverity::Medium,
            'description' => 'Test review',
            'status' => FraudSignalStatus::Detected,
            'detected_at' => now(),
        ]);

        $signal->markAsReviewed('admin@example.com');

        $signal->refresh();
        expect($signal->status)->toBe(FraudSignalStatus::Reviewed);
        expect($signal->reviewed_at)->not->toBeNull();
        expect($signal->reviewed_by)->toBe('admin@example.com');
    });

    test('dismiss updates status to dismissed', function (): void {
        $signal = AffiliateFraudSignal::create([
            'affiliate_id' => $this->affiliate->id,
            'rule_code' => 'DISMISS_TEST',
            'risk_points' => 50,
            'severity' => FraudSeverity::Medium,
            'description' => 'Test dismiss',
            'status' => FraudSignalStatus::Detected,
            'detected_at' => now(),
        ]);

        $signal->dismiss('reviewer@example.com');

        $signal->refresh();
        expect($signal->status)->toBe(FraudSignalStatus::Dismissed);
        expect($signal->reviewed_by)->toBe('reviewer@example.com');
    });

    test('confirm updates status to confirmed', function (): void {
        $signal = AffiliateFraudSignal::create([
            'affiliate_id' => $this->affiliate->id,
            'rule_code' => 'CONFIRM_TEST',
            'risk_points' => 100,
            'severity' => FraudSeverity::High,
            'description' => 'Test confirm',
            'status' => FraudSignalStatus::Detected,
            'detected_at' => now(),
        ]);

        $signal->confirm('security@example.com');

        $signal->refresh();
        expect($signal->status)->toBe(FraudSignalStatus::Confirmed);
        expect($signal->reviewed_by)->toBe('security@example.com');
    });

    test('can store evidence array', function (): void {
        $evidence = [
            'ip_address' => '192.168.1.100',
            'clicks_per_minute' => 150,
            'patterns' => ['rapid', 'automated'],
        ];

        $signal = AffiliateFraudSignal::create([
            'affiliate_id' => $this->affiliate->id,
            'rule_code' => 'EVIDENCE_TEST',
            'risk_points' => 75,
            'severity' => FraudSeverity::High,
            'description' => 'Test with evidence',
            'evidence' => $evidence,
            'status' => FraudSignalStatus::Detected,
            'detected_at' => now(),
        ]);

        expect($signal->evidence)->toBeArray();
        expect($signal->evidence['ip_address'])->toBe('192.168.1.100');
        expect($signal->evidence['clicks_per_minute'])->toBe(150);
        expect($signal->evidence['patterns'])->toContain('rapid');
    });

    test('casts severity correctly', function (): void {
        $signal = AffiliateFraudSignal::create([
            'affiliate_id' => $this->affiliate->id,
            'rule_code' => 'CAST_TEST',
            'risk_points' => 25,
            'severity' => FraudSeverity::Low,
            'description' => 'Test casts',
            'status' => FraudSignalStatus::Detected,
            'detected_at' => now(),
        ]);

        expect($signal->severity)->toBeInstanceOf(FraudSeverity::class);
        expect($signal->severity)->toBe(FraudSeverity::Low);
    });

    test('casts status correctly', function (): void {
        $signal = AffiliateFraudSignal::create([
            'affiliate_id' => $this->affiliate->id,
            'rule_code' => 'STATUS_CAST',
            'risk_points' => 50,
            'severity' => FraudSeverity::Medium,
            'description' => 'Test status cast',
            'status' => FraudSignalStatus::Detected,
            'detected_at' => now(),
        ]);

        expect($signal->status)->toBeInstanceOf(FraudSignalStatus::class);
        expect($signal->status)->toBe(FraudSignalStatus::Detected);
    });

    test('casts detected_at to Carbon', function (): void {
        $signal = AffiliateFraudSignal::create([
            'affiliate_id' => $this->affiliate->id,
            'rule_code' => 'DATETIME_CAST',
            'risk_points' => 50,
            'severity' => FraudSeverity::Medium,
            'description' => 'Test datetime cast',
            'status' => FraudSignalStatus::Detected,
            'detected_at' => '2024-06-15 10:30:00',
        ]);

        expect($signal->detected_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
        expect($signal->detected_at->format('Y-m-d'))->toBe('2024-06-15');
    });

    test('uses correct table name from config', function (): void {
        $signal = new AffiliateFraudSignal;

        expect($signal->getTable())->toBe(config('affiliates.table_names.fraud_signals', 'affiliate_fraud_signals'));
    });
});
