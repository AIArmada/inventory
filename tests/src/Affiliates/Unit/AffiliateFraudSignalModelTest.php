<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\FraudSeverity;
use AIArmada\Affiliates\Enums\FraudSignalStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateFraudSignal;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

describe('AffiliateFraudSignal Model', function (): void {
    it('can be created with required fields', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'FRAUD-TEST-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $signal = AffiliateFraudSignal::create([
            'affiliate_id' => $affiliate->id,
            'rule_code' => 'ip_velocity',
            'risk_points' => 50,
            'severity' => FraudSeverity::Medium,
            'description' => 'Multiple conversions from same IP',
            'status' => FraudSignalStatus::Detected,
            'detected_at' => now(),
        ]);

        expect($signal)->toBeInstanceOf(AffiliateFraudSignal::class)
            ->and($signal->rule_code)->toBe('ip_velocity')
            ->and($signal->risk_points)->toBe(50)
            ->and($signal->severity)->toBe(FraudSeverity::Medium)
            ->and($signal->status)->toBe(FraudSignalStatus::Detected);
    });

    it('belongs to an affiliate', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'FRAUD-AFF-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $signal = AffiliateFraudSignal::create([
            'affiliate_id' => $affiliate->id,
            'rule_code' => 'suspicious_pattern',
            'risk_points' => 30,
            'severity' => FraudSeverity::Low,
            'description' => 'Suspicious activity',
            'status' => FraudSignalStatus::Detected,
            'detected_at' => now(),
        ]);

        expect($signal->affiliate())->toBeInstanceOf(BelongsTo::class)
            ->and($signal->affiliate->id)->toBe($affiliate->id);
    });

    it('scopes pending signals', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'FRAUD-PEND-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        AffiliateFraudSignal::create([
            'affiliate_id' => $affiliate->id,
            'rule_code' => 'pending_test',
            'risk_points' => 40,
            'severity' => FraudSeverity::Medium,
            'description' => 'Pending signal',
            'status' => FraudSignalStatus::Detected,
            'detected_at' => now(),
        ]);

        AffiliateFraudSignal::create([
            'affiliate_id' => $affiliate->id,
            'rule_code' => 'reviewed_test',
            'risk_points' => 40,
            'severity' => FraudSeverity::Medium,
            'description' => 'Reviewed signal',
            'status' => FraudSignalStatus::Reviewed,
            'detected_at' => now(),
        ]);

        $pendingSignals = AffiliateFraudSignal::pending()
            ->where('affiliate_id', $affiliate->id)
            ->get();

        expect($pendingSignals)->toHaveCount(1)
            ->and($pendingSignals->first()->rule_code)->toBe('pending_test');
    });

    it('scopes confirmed signals', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'FRAUD-CONF-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        AffiliateFraudSignal::create([
            'affiliate_id' => $affiliate->id,
            'rule_code' => 'confirmed_test',
            'risk_points' => 80,
            'severity' => FraudSeverity::High,
            'description' => 'Confirmed fraud',
            'status' => FraudSignalStatus::Confirmed,
            'detected_at' => now(),
        ]);

        AffiliateFraudSignal::create([
            'affiliate_id' => $affiliate->id,
            'rule_code' => 'dismissed_test',
            'risk_points' => 20,
            'severity' => FraudSeverity::Low,
            'description' => 'False positive',
            'status' => FraudSignalStatus::Dismissed,
            'detected_at' => now(),
        ]);

        $confirmedSignals = AffiliateFraudSignal::confirmed()
            ->where('affiliate_id', $affiliate->id)
            ->get();

        expect($confirmedSignals)->toHaveCount(1)
            ->and($confirmedSignals->first()->rule_code)->toBe('confirmed_test');
    });

    it('scopes high severity signals', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'FRAUD-HIGH-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        AffiliateFraudSignal::create([
            'affiliate_id' => $affiliate->id,
            'rule_code' => 'critical_fraud',
            'risk_points' => 100,
            'severity' => FraudSeverity::Critical,
            'description' => 'Critical fraud detected',
            'status' => FraudSignalStatus::Detected,
            'detected_at' => now(),
        ]);

        AffiliateFraudSignal::create([
            'affiliate_id' => $affiliate->id,
            'rule_code' => 'high_fraud',
            'risk_points' => 80,
            'severity' => FraudSeverity::High,
            'description' => 'High fraud detected',
            'status' => FraudSignalStatus::Detected,
            'detected_at' => now(),
        ]);

        AffiliateFraudSignal::create([
            'affiliate_id' => $affiliate->id,
            'rule_code' => 'low_fraud',
            'risk_points' => 20,
            'severity' => FraudSeverity::Low,
            'description' => 'Low risk',
            'status' => FraudSignalStatus::Detected,
            'detected_at' => now(),
        ]);

        $highSeveritySignals = AffiliateFraudSignal::highSeverity()
            ->where('affiliate_id', $affiliate->id)
            ->get();

        expect($highSeveritySignals)->toHaveCount(2);
    });

    it('can mark signal as reviewed', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'FRAUD-REVIEW-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $signal = AffiliateFraudSignal::create([
            'affiliate_id' => $affiliate->id,
            'rule_code' => 'to_review',
            'risk_points' => 50,
            'severity' => FraudSeverity::Medium,
            'description' => 'Needs review',
            'status' => FraudSignalStatus::Detected,
            'detected_at' => now(),
        ]);

        $signal->markAsReviewed('admin@example.com');

        $signal->refresh();
        expect($signal->status)->toBe(FraudSignalStatus::Reviewed)
            ->and($signal->reviewed_at)->not->toBeNull()
            ->and($signal->reviewed_by)->toBe('admin@example.com');
    });

    it('can dismiss signal', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'FRAUD-DISM-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $signal = AffiliateFraudSignal::create([
            'affiliate_id' => $affiliate->id,
            'rule_code' => 'false_positive',
            'risk_points' => 20,
            'severity' => FraudSeverity::Low,
            'description' => 'Not actually fraud',
            'status' => FraudSignalStatus::Detected,
            'detected_at' => now(),
        ]);

        $signal->dismiss('reviewer@example.com');

        $signal->refresh();
        expect($signal->status)->toBe(FraudSignalStatus::Dismissed)
            ->and($signal->reviewed_at)->not->toBeNull()
            ->and($signal->reviewed_by)->toBe('reviewer@example.com');
    });

    it('can confirm signal', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'FRAUD-CONFIRM-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $signal = AffiliateFraudSignal::create([
            'affiliate_id' => $affiliate->id,
            'rule_code' => 'real_fraud',
            'risk_points' => 100,
            'severity' => FraudSeverity::Critical,
            'description' => 'Confirmed fraud activity',
            'status' => FraudSignalStatus::Detected,
            'detected_at' => now(),
        ]);

        $signal->confirm('security@example.com');

        $signal->refresh();
        expect($signal->status)->toBe(FraudSignalStatus::Confirmed)
            ->and($signal->reviewed_at)->not->toBeNull()
            ->and($signal->reviewed_by)->toBe('security@example.com');
    });

    it('casts evidence as array', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'FRAUD-EVID-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $signal = AffiliateFraudSignal::create([
            'affiliate_id' => $affiliate->id,
            'rule_code' => 'evidence_test',
            'risk_points' => 60,
            'severity' => FraudSeverity::Medium,
            'description' => 'Signal with evidence',
            'status' => FraudSignalStatus::Detected,
            'detected_at' => now(),
            'evidence' => [
                'ip_address' => '192.168.1.1',
                'user_agent' => 'Mozilla/5.0',
                'duplicate_count' => 5,
            ],
        ]);

        expect($signal->evidence)->toBeArray()
            ->and($signal->evidence['ip_address'])->toBe('192.168.1.1')
            ->and($signal->evidence['duplicate_count'])->toBe(5);
    });

    it('casts severity as enum', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'FRAUD-SEV-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $signal = AffiliateFraudSignal::create([
            'affiliate_id' => $affiliate->id,
            'rule_code' => 'severity_test',
            'risk_points' => 90,
            'severity' => FraudSeverity::High,
            'description' => 'High severity signal',
            'status' => FraudSignalStatus::Detected,
            'detected_at' => now(),
        ]);

        expect($signal->severity)->toBeInstanceOf(FraudSeverity::class)
            ->and($signal->severity)->toBe(FraudSeverity::High);
    });

    it('uses correct table name from config', function (): void {
        $signal = new AffiliateFraudSignal;
        expect($signal->getTable())->toBe('affiliate_fraud_signals');
    });
});
