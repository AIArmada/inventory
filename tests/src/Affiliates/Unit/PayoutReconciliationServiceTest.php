<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\PayoutStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateBalance;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Services\PayoutReconciliationService;

beforeEach(function (): void {
    $this->service = new PayoutReconciliationService;

    $this->affiliate = Affiliate::create([
        'code' => 'RECON-' . uniqid(),
        'name' => 'Reconciliation Test Affiliate',
        'contact_email' => 'recon@example.com',
        'status' => AffiliateStatus::Active,
        'commission_type' => CommissionType::Percentage,
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);
});

describe('PayoutReconciliationService', function (): void {
    describe('reconcilePayout', function (): void {
        test('returns false for unknown external status', function (): void {
            $payout = AffiliatePayout::create([
                'reference' => 'PAY-' . uniqid(),
                'owner_type' => Affiliate::class,
                'owner_id' => $this->affiliate->id,
                'amount_minor' => 5000,
                'currency' => 'USD',
                'status' => PayoutStatus::Processing,
                'method' => 'bank_transfer',
            ]);

            $result = $this->service->reconcilePayout($payout, 'unknown_status');

            expect($result)->toBeFalse();

            $payout->refresh();
            expect($payout->status)->toBe(PayoutStatus::Processing);
        });

        test('returns false when status is unchanged', function (): void {
            $payout = AffiliatePayout::create([
                'reference' => 'PAY-' . uniqid(),
                'owner_type' => Affiliate::class,
                'owner_id' => $this->affiliate->id,
                'amount_minor' => 5000,
                'currency' => 'USD',
                'status' => PayoutStatus::Completed,
                'method' => 'bank_transfer',
            ]);

            $result = $this->service->reconcilePayout($payout, 'completed');

            expect($result)->toBeFalse();
        });

        test('updates payout status from external completed status', function (): void {
            $payout = AffiliatePayout::create([
                'reference' => 'PAY-' . uniqid(),
                'owner_type' => Affiliate::class,
                'owner_id' => $this->affiliate->id,
                'amount_minor' => 10000,
                'currency' => 'USD',
                'status' => PayoutStatus::Processing,
                'method' => 'bank_transfer',
            ]);

            $result = $this->service->reconcilePayout($payout, 'completed');

            expect($result)->toBeTrue();

            $payout->refresh();
            expect($payout->status)->toBe(PayoutStatus::Completed);
            expect($payout->paid_at)->not->toBeNull();
        });

        test('merges external data into metadata', function (): void {
            $payout = AffiliatePayout::create([
                'reference' => 'PAY-' . uniqid(),
                'owner_type' => Affiliate::class,
                'owner_id' => $this->affiliate->id,
                'amount_minor' => 5000,
                'currency' => 'USD',
                'status' => PayoutStatus::Processing,
                'method' => 'bank_transfer',
                'metadata' => ['original' => 'data'],
            ]);

            $this->service->reconcilePayout($payout, 'completed', [
                'processor' => 'stripe',
                'fee' => 250,
            ]);

            $payout->refresh();
            expect($payout->metadata['original'])->toBe('data');
            expect($payout->metadata['reconciled_at'])->not->toBeNull();
            expect($payout->metadata['external_data']['processor'])->toBe('stripe');
            expect($payout->metadata['external_data']['fee'])->toBe(250);
        });

        test('handles case-insensitive status mapping', function (): void {
            $payout = AffiliatePayout::create([
                'reference' => 'PAY-' . uniqid(),
                'owner_type' => Affiliate::class,
                'owner_id' => $this->affiliate->id,
                'amount_minor' => 5000,
                'currency' => 'USD',
                'status' => PayoutStatus::Processing,
                'method' => 'bank_transfer',
            ]);

            $result = $this->service->reconcilePayout($payout, 'COMPLETED');

            expect($result)->toBeTrue();

            $payout->refresh();
            expect($payout->status)->toBe(PayoutStatus::Completed);
        });
    });

    describe('getPayoutsNeedingReconciliation', function (): void {
        test('returns payouts in processing status older than 1 hour', function (): void {
            // Create old processing payout with external reference
            $oldPayout = AffiliatePayout::create([
                'reference' => 'PAY-OLD-' . uniqid(),
                'owner_type' => Affiliate::class,
                'owner_id' => $this->affiliate->id,
                'amount_minor' => 5000,
                'currency' => 'USD',
                'status' => PayoutStatus::Processing,
                'method' => 'bank_transfer',
                'external_reference' => 'EXT-OLD',
            ]);

            // Manually update the timestamp
            AffiliatePayout::where('id', $oldPayout->id)
                ->update(['updated_at' => now()->subHours(2)]);

            // Create recent payout (should not be returned)
            AffiliatePayout::create([
                'reference' => 'PAY-NEW-' . uniqid(),
                'owner_type' => Affiliate::class,
                'owner_id' => $this->affiliate->id,
                'amount_minor' => 5000,
                'currency' => 'USD',
                'status' => PayoutStatus::Processing,
                'method' => 'bank_transfer',
                'external_reference' => 'EXT-NEW',
            ]);

            $result = $this->service->getPayoutsNeedingReconciliation();

            expect($result)->toHaveCount(1);
            expect($result->first()->id)->toBe($oldPayout->id);
        });

        test('excludes completed payouts', function (): void {
            $payout = AffiliatePayout::create([
                'reference' => 'PAY-' . uniqid(),
                'owner_type' => Affiliate::class,
                'owner_id' => $this->affiliate->id,
                'amount_minor' => 5000,
                'currency' => 'USD',
                'status' => PayoutStatus::Completed,
                'method' => 'bank_transfer',
                'external_reference' => 'EXT-123',
            ]);

            AffiliatePayout::where('id', $payout->id)
                ->update(['updated_at' => now()->subHours(2)]);

            $result = $this->service->getPayoutsNeedingReconciliation();

            expect($result)->toBeEmpty();
        });

        test('includes pending payouts', function (): void {
            $payout = AffiliatePayout::create([
                'reference' => 'PAY-' . uniqid(),
                'owner_type' => Affiliate::class,
                'owner_id' => $this->affiliate->id,
                'amount_minor' => 5000,
                'currency' => 'USD',
                'status' => PayoutStatus::Pending,
                'method' => 'bank_transfer',
                'external_reference' => 'EXT-123',
            ]);

            AffiliatePayout::where('id', $payout->id)
                ->update(['updated_at' => now()->subHours(2)]);

            $result = $this->service->getPayoutsNeedingReconciliation();

            expect($result)->toHaveCount(1);
        });
    });

    describe('generateReport', function (): void {
        test('returns report structure', function (): void {
            $result = $this->service->generateReport();

            expect($result)->toHaveKeys(['period', 'summary', 'by_status', 'discrepancies']);
            expect($result['summary'])->toHaveKeys([
                'total_payouts',
                'total_amount_minor',
                'completed_amount_minor',
                'failed_amount_minor',
                'pending_amount_minor',
            ]);
        });

        test('groups by status', function (): void {
            AffiliatePayout::create([
                'reference' => 'PAY-1',
                'owner_type' => Affiliate::class,
                'owner_id' => $this->affiliate->id,
                'amount_minor' => 5000,
                'currency' => 'USD',
                'status' => PayoutStatus::Completed,
                'method' => 'bank_transfer',
            ]);

            AffiliatePayout::create([
                'reference' => 'PAY-2',
                'owner_type' => Affiliate::class,
                'owner_id' => $this->affiliate->id,
                'amount_minor' => 5000,
                'currency' => 'USD',
                'status' => PayoutStatus::Completed,
                'method' => 'bank_transfer',
            ]);

            AffiliatePayout::create([
                'reference' => 'PAY-3',
                'owner_type' => Affiliate::class,
                'owner_id' => $this->affiliate->id,
                'amount_minor' => 3000,
                'currency' => 'USD',
                'status' => PayoutStatus::Pending,
                'method' => 'bank_transfer',
            ]);

            $result = $this->service->generateReport();

            expect($result['by_status'][PayoutStatus::Completed->value])->toBe(2);
            expect($result['by_status'][PayoutStatus::Pending->value])->toBe(1);
        });

        test('handles empty payout result set', function (): void {
            $result = $this->service->generateReport();

            expect($result['summary']['total_payouts'])->toBe(0);
            expect($result['summary']['total_amount_minor'])->toBe(0);
            expect($result['discrepancies'])->toBeEmpty();
        });
    });

    describe('auditAffiliateBalance', function (): void {
        test('returns audit structure', function (): void {
            AffiliateBalance::create([
                'affiliate_id' => $this->affiliate->id,
                'available_minor' => 5000,
                'pending_minor' => 0,
                'minimum_payout_minor' => 5000,
                'currency' => 'USD',
            ]);

            $result = $this->service->auditAffiliateBalance($this->affiliate);

            expect($result)->toHaveKeys([
                'affiliate_id',
                'expected_available_minor',
                'actual_available_minor',
                'discrepancy_minor',
                'has_discrepancy',
                'approved_commissions_minor',
                'paid_out_minor',
                'pending_payouts_minor',
            ]);
        });

        test('detects balance discrepancy', function (): void {
            // Create approved conversion
            AffiliateConversion::create([
                'affiliate_id' => $this->affiliate->id,
                'affiliate_code' => $this->affiliate->code,
                'reference' => 'CONV-1',
                'total_minor' => 100000,
                'commission_minor' => 10000,
                'status' => 'approved',
                'currency' => 'USD',
                'occurred_at' => now(),
            ]);

            // Balance doesn't match (should be 10000)
            AffiliateBalance::create([
                'affiliate_id' => $this->affiliate->id,
                'available_minor' => 5000, // Wrong!
                'pending_minor' => 0,
                'minimum_payout_minor' => 5000,
                'currency' => 'USD',
            ]);

            $result = $this->service->auditAffiliateBalance($this->affiliate);

            expect($result['expected_available_minor'])->toBe(10000);
            expect($result['actual_available_minor'])->toBe(5000);
            expect($result['discrepancy_minor'])->toBe(5000);
            expect($result['has_discrepancy'])->toBeTrue();
        });

        test('handles affiliate without balance', function (): void {
            $result = $this->service->auditAffiliateBalance($this->affiliate);

            expect($result['actual_available_minor'])->toBe(0);
        });
    });
});

describe('PayoutReconciliationService class structure', function (): void {
    test('can be instantiated', function (): void {
        $service = new PayoutReconciliationService;
        expect($service)->toBeInstanceOf(PayoutReconciliationService::class);
    });

    test('has required public methods', function (): void {
        $reflection = new ReflectionClass(PayoutReconciliationService::class);

        expect($reflection->hasMethod('reconcilePayout'))->toBeTrue();
        expect($reflection->hasMethod('getPayoutsNeedingReconciliation'))->toBeTrue();
        expect($reflection->hasMethod('generateReport'))->toBeTrue();
        expect($reflection->hasMethod('auditAffiliateBalance'))->toBeTrue();
    });

    test('is declared as final', function (): void {
        $reflection = new ReflectionClass(PayoutReconciliationService::class);
        expect($reflection->isFinal())->toBeTrue();
    });
});
