<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Models\AffiliatePayoutEvent;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

describe('AffiliatePayoutEvent Model', function (): void {
    it('can be created with required fields', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'EVENT-TEST-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $payout = AffiliatePayout::create([
            'affiliate_id' => $affiliate->id,
            'reference' => 'PAY-EVENT-' . uniqid(),
            'owner_type' => 'App\\Models\\User',
            'owner_id' => 'user-123',
            'status' => 'pending',
            'total_minor' => 10000,
            'conversion_count' => 5,
            'currency' => 'USD',
        ]);

        $event = AffiliatePayoutEvent::create([
            'affiliate_payout_id' => $payout->id,
            'from_status' => null,
            'to_status' => 'pending',
        ]);

        expect($event)->toBeInstanceOf(AffiliatePayoutEvent::class)
            ->and($event->from_status)->toBeNull()
            ->and($event->to_status)->toBe('pending');
    });

    it('belongs to a payout', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'EVENT-PAY-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $payout = AffiliatePayout::create([
            'affiliate_id' => $affiliate->id,
            'reference' => 'PAY-BELONG-' . uniqid(),
            'owner_type' => 'App\\Models\\User',
            'owner_id' => 'user-456',
            'status' => 'pending',
            'total_minor' => 5000,
            'conversion_count' => 3,
            'currency' => 'USD',
        ]);

        $event = AffiliatePayoutEvent::create([
            'affiliate_payout_id' => $payout->id,
            'from_status' => null,
            'to_status' => 'pending',
        ]);

        expect($event->payout())->toBeInstanceOf(BelongsTo::class)
            ->and($event->payout->id)->toBe($payout->id);
    });

    it('has status accessor for to_status', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'EVENT-STAT-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $payout = AffiliatePayout::create([
            'affiliate_id' => $affiliate->id,
            'reference' => 'PAY-STATUS-' . uniqid(),
            'owner_type' => 'App\\Models\\User',
            'owner_id' => 'user-789',
            'status' => 'processing',
            'total_minor' => 7500,
            'conversion_count' => 4,
            'currency' => 'USD',
        ]);

        $event = AffiliatePayoutEvent::create([
            'affiliate_payout_id' => $payout->id,
            'from_status' => 'pending',
            'to_status' => 'processing',
        ]);

        expect($event->status)->toBe('processing')
            ->and($event->status)->toBe($event->to_status);
    });

    it('tracks status transitions', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'EVENT-TRANS-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $payout = AffiliatePayout::create([
            'affiliate_id' => $affiliate->id,
            'reference' => 'PAY-TRANS-' . uniqid(),
            'owner_type' => 'App\\Models\\User',
            'owner_id' => 'user-trans',
            'status' => 'completed',
            'total_minor' => 12000,
            'conversion_count' => 6,
            'currency' => 'USD',
        ]);

        // Create multiple events representing state transitions
        AffiliatePayoutEvent::create([
            'affiliate_payout_id' => $payout->id,
            'from_status' => null,
            'to_status' => 'pending',
        ]);

        AffiliatePayoutEvent::create([
            'affiliate_payout_id' => $payout->id,
            'from_status' => 'pending',
            'to_status' => 'processing',
        ]);

        AffiliatePayoutEvent::create([
            'affiliate_payout_id' => $payout->id,
            'from_status' => 'processing',
            'to_status' => 'completed',
        ]);

        $events = AffiliatePayoutEvent::where('affiliate_payout_id', $payout->id)
            ->orderBy('created_at')
            ->get();

        expect($events)->toHaveCount(3)
            ->and($events[0]->to_status)->toBe('pending')
            ->and($events[1]->to_status)->toBe('processing')
            ->and($events[2]->to_status)->toBe('completed');
    });

    it('stores metadata', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'EVENT-META-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $payout = AffiliatePayout::create([
            'affiliate_id' => $affiliate->id,
            'reference' => 'PAY-META-' . uniqid(),
            'owner_type' => 'App\\Models\\User',
            'owner_id' => 'user-meta',
            'status' => 'pending',
            'total_minor' => 8000,
            'conversion_count' => 4,
            'currency' => 'USD',
        ]);

        $event = AffiliatePayoutEvent::create([
            'affiliate_payout_id' => $payout->id,
            'from_status' => null,
            'to_status' => 'pending',
            'metadata' => [
                'initiated_by' => 'system',
                'batch_id' => 'BATCH-001',
                'ip_address' => '192.168.1.1',
            ],
        ]);

        expect($event->metadata)->toBeArray()
            ->and($event->metadata['initiated_by'])->toBe('system')
            ->and($event->metadata['batch_id'])->toBe('BATCH-001');
    });

    it('stores notes', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'EVENT-NOTE-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $payout = AffiliatePayout::create([
            'affiliate_id' => $affiliate->id,
            'reference' => 'PAY-NOTE-' . uniqid(),
            'owner_type' => 'App\\Models\\User',
            'owner_id' => 'user-note',
            'status' => 'failed',
            'total_minor' => 3000,
            'conversion_count' => 2,
            'currency' => 'USD',
        ]);

        $event = AffiliatePayoutEvent::create([
            'affiliate_payout_id' => $payout->id,
            'from_status' => 'processing',
            'to_status' => 'failed',
            'notes' => 'Payment gateway rejected the transaction',
        ]);

        expect($event->notes)->toBe('Payment gateway rejected the transaction');
    });

    it('casts metadata as array', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'EVENT-CAST-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $payout = AffiliatePayout::create([
            'affiliate_id' => $affiliate->id,
            'reference' => 'PAY-CAST-' . uniqid(),
            'owner_type' => 'App\\Models\\User',
            'owner_id' => 'user-cast',
            'status' => 'pending',
            'total_minor' => 5000,
            'conversion_count' => 2,
            'currency' => 'USD',
        ]);

        $event = AffiliatePayoutEvent::create([
            'affiliate_payout_id' => $payout->id,
            'from_status' => null,
            'to_status' => 'pending',
            'metadata' => ['test' => 'value'],
        ]);

        expect($event->metadata)->toBeArray();
    });

    it('uses correct table name from config', function (): void {
        $event = new AffiliatePayoutEvent;
        expect($event->getTable())->toBe('affiliate_payout_events');
    });
});
