<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\ConversionStatus;
use AIArmada\Affiliates\Enums\FraudSeverity;
use AIArmada\Affiliates\Enums\FraudSignalStatus;
use AIArmada\Affiliates\Events\FraudSignalDetected;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateFraudSignal;
use AIArmada\Affiliates\Services\FraudDetectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $this->service = app(FraudDetectionService::class);

    $this->affiliate = Affiliate::create([
        'code' => 'FRAUD-' . uniqid(),
        'name' => 'Fraud Test Affiliate',
        'contact_email' => 'fraud@example.com',
        'status' => AffiliateStatus::Active,
        'commission_type' => CommissionType::Percentage,
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);
});

describe('FraudDetectionService', function (): void {
    describe('analyzeClick', function (): void {
        test('returns allowed true when no fraud signals', function (): void {
            $request = Request::create('/', 'GET', [], [], [], [
                'REMOTE_ADDR' => '192.168.1.1',
                'HTTP_USER_AGENT' => 'Mozilla/5.0 Test Browser',
            ]);

            $result = $this->service->analyzeClick($this->affiliate, $request);

            expect($result)->toHaveKeys(['allowed', 'score', 'signals']);
            expect($result['allowed'])->toBeTrue();
            expect($result['score'])->toBe(0);
            expect($result['signals'])->toBeEmpty();
        });

        test('detects click velocity violation', function (): void {
            config(['affiliates.fraud.velocity.enabled' => true]);
            config(['affiliates.fraud.velocity.max_clicks_per_hour' => 2]);

            $request = Request::create('/', 'GET', [], [], [], [
                'REMOTE_ADDR' => '192.168.1.100',
                'HTTP_USER_AGENT' => 'Mozilla/5.0 Velocity Test',
            ]);

            // Set cache to simulate previous clicks
            $cacheKey = "fraud:clicks:{$this->affiliate->id}:192.168.1.100";
            Cache::put($cacheKey, 5, now()->addHour());

            $result = $this->service->analyzeClick($this->affiliate, $request);

            expect($result['signals'])->not->toBeEmpty();
            expect($result['score'])->toBeGreaterThan(0);

            $velocitySignal = collect($result['signals'])->firstWhere('rule_code', 'CLICK_VELOCITY');
            expect($velocitySignal)->not->toBeNull();
        });

        test('increments click counter in cache', function (): void {
            config(['affiliates.fraud.velocity.enabled' => true]);
            config(['affiliates.fraud.velocity.max_clicks_per_hour' => 100]);

            $request = Request::create('/', 'GET', [], [], [], [
                'REMOTE_ADDR' => '192.168.1.200',
                'HTTP_USER_AGENT' => 'Mozilla/5.0 Counter Test',
            ]);

            $cacheKey = "fraud:clicks:{$this->affiliate->id}:192.168.1.200";

            $this->service->analyzeClick($this->affiliate, $request);

            expect(Cache::get($cacheKey))->toBe(1);

            $this->service->analyzeClick($this->affiliate, $request);

            expect(Cache::get($cacheKey))->toBe(2);
        });

        test('respects velocity disabled config', function (): void {
            config(['affiliates.fraud.velocity.enabled' => false]);

            $request = Request::create('/', 'GET', [], [], [], [
                'REMOTE_ADDR' => '192.168.1.1',
                'HTTP_USER_AGENT' => 'Mozilla/5.0',
            ]);

            $cacheKey = "fraud:clicks:{$this->affiliate->id}:192.168.1.1";
            Cache::put($cacheKey, 1000, now()->addHour()); // Way over limit

            $result = $this->service->analyzeClick($this->affiliate, $request);

            // Should not flag because velocity check is disabled
            $velocitySignal = collect($result['signals'])->firstWhere('rule_code', 'CLICK_VELOCITY');
            expect($velocitySignal)->toBeNull();
        });
    });

    describe('analyzeConversion', function (): void {
        test('returns allowed true when no fraud signals', function (): void {
            $conversion = AffiliateConversion::create([
                'affiliate_id' => $this->affiliate->id,
                'affiliate_code' => $this->affiliate->code,
                'order_reference' => 'CLEAN-001',
                'subtotal_minor' => 10000,
                'total_minor' => 10000,
                'commission_minor' => 1000,
                'status' => ConversionStatus::Pending,
                'occurred_at' => now(),
            ]);

            $result = $this->service->analyzeConversion($conversion);

            expect($result)->toHaveKeys(['allowed', 'score', 'signals']);
            expect($result['allowed'])->toBeTrue();
        });

        test('detects self-referral when enabled', function (): void {
            config(['affiliates.tracking.block_self_referral' => true]);

            $this->affiliate->update(['owner_id' => 'user-123', 'owner_type' => 'user']);

            $conversion = AffiliateConversion::create([
                'affiliate_id' => $this->affiliate->id,
                'affiliate_code' => $this->affiliate->code,
                'order_reference' => 'SELF-001',
                'subtotal_minor' => 10000,
                'total_minor' => 10000,
                'commission_minor' => 1000,
                'status' => ConversionStatus::Pending,
                'occurred_at' => now(),
                'owner_id' => 'user-123', // Same as affiliate owner
                'owner_type' => 'user',
            ]);

            Event::fake([FraudSignalDetected::class]);

            $result = $this->service->analyzeConversion($conversion);

            $selfReferralSignal = collect($result['signals'])->firstWhere('rule_code', 'SELF_REFERRAL');
            expect($selfReferralSignal)->not->toBeNull();
            expect($selfReferralSignal->severity)->toBe(FraudSeverity::Critical);
        });

        test('detects conversion velocity violation', function (): void {
            config(['affiliates.fraud.velocity.max_conversions_per_day' => 2]);

            // Create existing conversions for today
            for ($i = 0; $i < 3; $i++) {
                AffiliateConversion::create([
                    'affiliate_id' => $this->affiliate->id,
                    'affiliate_code' => $this->affiliate->code,
                    'order_reference' => "EXISTING-{$i}",
                    'subtotal_minor' => 10000,
                    'total_minor' => 10000,
                    'commission_minor' => 1000,
                    'status' => ConversionStatus::Approved,
                    'occurred_at' => now(),
                ]);
            }

            $conversion = AffiliateConversion::create([
                'affiliate_id' => $this->affiliate->id,
                'affiliate_code' => $this->affiliate->code,
                'order_reference' => 'NEW-001',
                'subtotal_minor' => 10000,
                'total_minor' => 10000,
                'commission_minor' => 1000,
                'status' => ConversionStatus::Pending,
                'occurred_at' => now(),
            ]);

            Event::fake([FraudSignalDetected::class]);

            $result = $this->service->analyzeConversion($conversion);

            $velocitySignal = collect($result['signals'])->firstWhere('rule_code', 'CONVERSION_VELOCITY');
            expect($velocitySignal)->not->toBeNull();
        });

        test('detects fast conversion time', function (): void {
            config(['affiliates.fraud.anomaly.conversion_time.min_seconds' => 10]);

            $attribution = AffiliateAttribution::create([
                'affiliate_id' => $this->affiliate->id,
                'affiliate_code' => $this->affiliate->code,
                'session_id' => 'FAST-SESSION',
                'first_seen_at' => now()->subSeconds(3), // Only 3 seconds ago
                'last_seen_at' => now(),
            ]);

            $conversion = AffiliateConversion::create([
                'affiliate_id' => $this->affiliate->id,
                'affiliate_code' => $this->affiliate->code,
                'affiliate_attribution_id' => $attribution->id,
                'order_reference' => 'FAST-001',
                'subtotal_minor' => 10000,
                'total_minor' => 10000,
                'commission_minor' => 1000,
                'status' => ConversionStatus::Pending,
                'occurred_at' => now(),
            ]);

            Event::fake([FraudSignalDetected::class]);

            $result = $this->service->analyzeConversion($conversion);

            $fastSignal = collect($result['signals'])->firstWhere('rule_code', 'FAST_CONVERSION');
            expect($fastSignal)->not->toBeNull();
            expect($fastSignal->severity)->toBe(FraudSeverity::High);
        });

        test('dispatches FraudSignalDetected event', function (): void {
            config(['affiliates.fraud.velocity.max_conversions_per_day' => 0]); // Force flag

            $conversion = AffiliateConversion::create([
                'affiliate_id' => $this->affiliate->id,
                'affiliate_code' => $this->affiliate->code,
                'order_reference' => 'EVENT-001',
                'subtotal_minor' => 10000,
                'total_minor' => 10000,
                'commission_minor' => 1000,
                'status' => ConversionStatus::Pending,
                'occurred_at' => now(),
            ]);

            Event::fake([FraudSignalDetected::class]);

            $this->service->analyzeConversion($conversion);

            Event::assertDispatched(FraudSignalDetected::class);
        });
    });

    describe('getRiskProfile', function (): void {
        test('returns risk profile array', function (): void {
            $result = $this->service->getRiskProfile($this->affiliate);

            expect($result)->toHaveKeys([
                'total_score',
                'severity',
                'signal_count',
                'by_rule',
                'pending_review',
                'confirmed',
            ]);
        });

        test('calculates total score from signals', function (): void {
            AffiliateFraudSignal::create([
                'affiliate_id' => $this->affiliate->id,
                'rule_code' => 'TEST_RULE_1',
                'risk_points' => 25,
                'severity' => FraudSeverity::Medium,
                'description' => 'Test signal 1',
                'status' => FraudSignalStatus::Detected,
                'detected_at' => now(),
            ]);

            AffiliateFraudSignal::create([
                'affiliate_id' => $this->affiliate->id,
                'rule_code' => 'TEST_RULE_2',
                'risk_points' => 15,
                'severity' => FraudSeverity::Low,
                'description' => 'Test signal 2',
                'status' => FraudSignalStatus::Detected,
                'detected_at' => now(),
            ]);

            $result = $this->service->getRiskProfile($this->affiliate);

            expect($result['total_score'])->toBe(40);
            expect($result['signal_count'])->toBe(2);
        });

        test('groups signals by rule code', function (): void {
            AffiliateFraudSignal::create([
                'affiliate_id' => $this->affiliate->id,
                'rule_code' => 'CLICK_VELOCITY',
                'risk_points' => 30,
                'severity' => FraudSeverity::Medium,
                'description' => 'Velocity 1',
                'status' => FraudSignalStatus::Detected,
                'detected_at' => now(),
            ]);

            AffiliateFraudSignal::create([
                'affiliate_id' => $this->affiliate->id,
                'rule_code' => 'CLICK_VELOCITY',
                'risk_points' => 30,
                'severity' => FraudSeverity::Medium,
                'description' => 'Velocity 2',
                'status' => FraudSignalStatus::Detected,
                'detected_at' => now(),
            ]);

            $result = $this->service->getRiskProfile($this->affiliate);

            expect($result['by_rule'])->toHaveKey('CLICK_VELOCITY');
            expect($result['by_rule']['CLICK_VELOCITY']['count'])->toBe(2);
            expect($result['by_rule']['CLICK_VELOCITY']['total_points'])->toBe(60);
        });

        test('excludes signals older than 30 days', function (): void {
            AffiliateFraudSignal::create([
                'affiliate_id' => $this->affiliate->id,
                'rule_code' => 'OLD_SIGNAL',
                'risk_points' => 100,
                'severity' => FraudSeverity::Critical,
                'description' => 'Old signal',
                'status' => FraudSignalStatus::Detected,
                'detected_at' => now()->subDays(35),
            ]);

            $result = $this->service->getRiskProfile($this->affiliate);

            expect($result['total_score'])->toBe(0);
            expect($result['signal_count'])->toBe(0);
        });

        test('counts pending and confirmed signals separately', function (): void {
            AffiliateFraudSignal::create([
                'affiliate_id' => $this->affiliate->id,
                'rule_code' => 'PENDING_SIGNAL',
                'risk_points' => 20,
                'severity' => FraudSeverity::Low,
                'description' => 'Pending',
                'status' => FraudSignalStatus::Detected,
                'detected_at' => now(),
            ]);

            AffiliateFraudSignal::create([
                'affiliate_id' => $this->affiliate->id,
                'rule_code' => 'CONFIRMED_SIGNAL',
                'risk_points' => 50,
                'severity' => FraudSeverity::High,
                'description' => 'Confirmed',
                'status' => FraudSignalStatus::Confirmed,
                'detected_at' => now(),
            ]);

            $result = $this->service->getRiskProfile($this->affiliate);

            expect($result['pending_review'])->toBe(1);
            expect($result['confirmed'])->toBe(1);
        });

        test('determines severity based on total score', function (): void {
            // Create signals to reach critical severity
            AffiliateFraudSignal::create([
                'affiliate_id' => $this->affiliate->id,
                'rule_code' => 'CRITICAL_SIGNAL',
                'risk_points' => 150,
                'severity' => FraudSeverity::Critical,
                'description' => 'Critical',
                'status' => FraudSignalStatus::Detected,
                'detected_at' => now(),
            ]);

            $result = $this->service->getRiskProfile($this->affiliate);

            expect($result['severity'])->toBeInstanceOf(FraudSeverity::class);
        });
    });
});

describe('FraudDetectionService class structure', function (): void {
    test('can be instantiated', function (): void {
        $service = app(FraudDetectionService::class);
        expect($service)->toBeInstanceOf(FraudDetectionService::class);
    });

    test('is declared as final', function (): void {
        $reflection = new ReflectionClass(FraudDetectionService::class);
        expect($reflection->isFinal())->toBeTrue();
    });

    test('has required public methods', function (): void {
        $reflection = new ReflectionClass(FraudDetectionService::class);

        expect($reflection->hasMethod('analyzeClick'))->toBeTrue();
        expect($reflection->hasMethod('analyzeConversion'))->toBeTrue();
        expect($reflection->hasMethod('getRiskProfile'))->toBeTrue();
    });
});
