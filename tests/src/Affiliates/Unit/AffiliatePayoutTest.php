<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\ConversionStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Models\AffiliatePayoutEvent;
use Illuminate\Database\Eloquent\Relations\HasMany;

describe('AffiliatePayout Model', function (): void {
    beforeEach(function (): void {
        $this->affiliate = Affiliate::create([
            'code' => 'PAYOUT' . uniqid(),
            'name' => 'Payout Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => 'percentage',
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);
    });

    test('can be created with required fields', function (): void {
        $payout = AffiliatePayout::create([
            'reference' => 'PAY-' . uniqid(),
            'status' => 'pending',
            'total_minor' => 100000,
            'conversion_count' => 10,
            'currency' => 'USD',
            'owner_type' => Affiliate::class,
            'owner_id' => $this->affiliate->id,
        ]);

        expect($payout)->toBeInstanceOf(AffiliatePayout::class);
        expect($payout->total_minor)->toBe(100000);
        expect($payout->conversion_count)->toBe(10);
        expect($payout->currency)->toBe('USD');
    });

    test('has polymorphic owner relationship', function (): void {
        $payout = AffiliatePayout::create([
            'reference' => 'PAY-MORPH-' . uniqid(),
            'status' => 'pending',
            'total_minor' => 50000,
            'conversion_count' => 5,
            'currency' => 'USD',
            'owner_type' => Affiliate::class,
            'owner_id' => $this->affiliate->id,
        ]);

        expect($payout->owner)->toBeInstanceOf(Affiliate::class);
        expect($payout->owner->id)->toBe($this->affiliate->id);
    });

    test('has conversions relationship', function (): void {
        $payout = new AffiliatePayout;
        expect($payout->conversions())->toBeInstanceOf(HasMany::class);
    });

    test('has events relationship', function (): void {
        $payout = new AffiliatePayout;
        expect($payout->events())->toBeInstanceOf(HasMany::class);
    });

    test('has many conversions', function (): void {
        $payout = AffiliatePayout::create([
            'reference' => 'PAY-CONV-' . uniqid(),
            'status' => 'completed',
            'total_minor' => 75000,
            'conversion_count' => 3,
            'currency' => 'USD',
            'owner_type' => Affiliate::class,
            'owner_id' => $this->affiliate->id,
        ]);

        AffiliateConversion::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'affiliate_payout_id' => $payout->id,
            'order_reference' => 'ORD-PAY-001',
            'total_minor' => 25000,
            'commission_minor' => 2500,
            'commission_currency' => 'USD',
            'status' => ConversionStatus::Paid,
            'occurred_at' => now(),
        ]);

        AffiliateConversion::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'affiliate_payout_id' => $payout->id,
            'order_reference' => 'ORD-PAY-002',
            'total_minor' => 50000,
            'commission_minor' => 5000,
            'commission_currency' => 'USD',
            'status' => ConversionStatus::Paid,
            'occurred_at' => now(),
        ]);

        expect($payout->conversions)->toHaveCount(2);
    });

    test('has many events', function (): void {
        $payout = AffiliatePayout::create([
            'reference' => 'PAY-EVT-' . uniqid(),
            'status' => 'pending',
            'total_minor' => 30000,
            'conversion_count' => 3,
            'currency' => 'USD',
            'owner_type' => Affiliate::class,
            'owner_id' => $this->affiliate->id,
        ]);

        AffiliatePayoutEvent::create([
            'affiliate_payout_id' => $payout->id,
            'to_status' => 'created',
            'metadata' => ['note' => 'Payout created'],
        ]);

        AffiliatePayoutEvent::create([
            'affiliate_payout_id' => $payout->id,
            'from_status' => 'created',
            'to_status' => 'approved',
            'metadata' => ['note' => 'Payout approved'],
        ]);

        expect($payout->events)->toHaveCount(2);
    });

    test('affiliate accessor returns owner when owner is Affiliate', function (): void {
        $payout = AffiliatePayout::create([
            'reference' => 'PAY-AFF-' . uniqid(),
            'status' => 'pending',
            'total_minor' => 20000,
            'conversion_count' => 2,
            'currency' => 'USD',
            'owner_type' => Affiliate::class,
            'owner_id' => $this->affiliate->id,
        ]);

        expect($payout->affiliate)->toBeInstanceOf(Affiliate::class);
        expect($payout->affiliate->id)->toBe($this->affiliate->id);
    });

    test('amount_minor accessor returns total_minor', function (): void {
        $payout = AffiliatePayout::create([
            'reference' => 'PAY-AMT-' . uniqid(),
            'status' => 'pending',
            'total_minor' => 45000,
            'conversion_count' => 4,
            'currency' => 'USD',
            'owner_type' => Affiliate::class,
            'owner_id' => $this->affiliate->id,
        ]);

        expect($payout->amount_minor)->toBe(45000);
    });

    test('external_reference accessor returns metadata value', function (): void {
        $payout = AffiliatePayout::create([
            'reference' => 'PAY-EXT-' . uniqid(),
            'status' => 'completed',
            'total_minor' => 60000,
            'conversion_count' => 6,
            'currency' => 'USD',
            'owner_type' => Affiliate::class,
            'owner_id' => $this->affiliate->id,
            'metadata' => ['external_reference' => 'EXT-12345'],
        ]);

        expect($payout->external_reference)->toBe('EXT-12345');
    });

    test('external_reference accessor returns null when not set', function (): void {
        $payout = AffiliatePayout::create([
            'reference' => 'PAY-NOEXT-' . uniqid(),
            'status' => 'pending',
            'total_minor' => 10000,
            'conversion_count' => 1,
            'currency' => 'USD',
            'owner_type' => Affiliate::class,
            'owner_id' => $this->affiliate->id,
        ]);

        expect($payout->external_reference)->toBeNull();
    });

    test('notes accessor returns metadata value', function (): void {
        $payout = AffiliatePayout::create([
            'reference' => 'PAY-NOTE-' . uniqid(),
            'status' => 'processing',
            'total_minor' => 35000,
            'conversion_count' => 3,
            'currency' => 'USD',
            'owner_type' => Affiliate::class,
            'owner_id' => $this->affiliate->id,
            'metadata' => ['notes' => 'Monthly payout batch'],
        ]);

        expect($payout->notes)->toBe('Monthly payout batch');
    });

    test('notes accessor returns null when not set', function (): void {
        $payout = AffiliatePayout::create([
            'reference' => 'PAY-NONOTE-' . uniqid(),
            'status' => 'pending',
            'total_minor' => 8000,
            'conversion_count' => 1,
            'currency' => 'USD',
            'owner_type' => Affiliate::class,
            'owner_id' => $this->affiliate->id,
        ]);

        expect($payout->notes)->toBeNull();
    });

    test('casts metadata as array', function (): void {
        $payout = AffiliatePayout::create([
            'reference' => 'PAY-META-' . uniqid(),
            'status' => 'pending',
            'total_minor' => 25000,
            'conversion_count' => 2,
            'currency' => 'USD',
            'owner_type' => Affiliate::class,
            'owner_id' => $this->affiliate->id,
            'metadata' => [
                'batch_id' => 'BATCH-001',
                'processed_by' => 'admin@example.com',
            ],
        ]);

        expect($payout->metadata)->toBeArray();
        expect($payout->metadata['batch_id'])->toBe('BATCH-001');
        expect($payout->metadata['processed_by'])->toBe('admin@example.com');
    });

    test('casts scheduled_at as datetime', function (): void {
        $payout = AffiliatePayout::create([
            'reference' => 'PAY-SCHED-' . uniqid(),
            'status' => 'pending',
            'total_minor' => 15000,
            'conversion_count' => 2,
            'currency' => 'USD',
            'owner_type' => Affiliate::class,
            'owner_id' => $this->affiliate->id,
            'scheduled_at' => '2024-12-25 10:00:00',
        ]);

        expect($payout->scheduled_at)->toBeInstanceOf(Illuminate\Support\Carbon::class);
        expect($payout->scheduled_at->format('Y-m-d'))->toBe('2024-12-25');
    });

    test('casts paid_at as datetime', function (): void {
        $payout = AffiliatePayout::create([
            'reference' => 'PAY-PAID-' . uniqid(),
            'status' => 'completed',
            'total_minor' => 55000,
            'conversion_count' => 5,
            'currency' => 'USD',
            'owner_type' => Affiliate::class,
            'owner_id' => $this->affiliate->id,
            'paid_at' => '2024-12-20 14:30:00',
        ]);

        expect($payout->paid_at)->toBeInstanceOf(Illuminate\Support\Carbon::class);
        expect($payout->paid_at->format('Y-m-d'))->toBe('2024-12-20');
    });

    test('cascade deletes events on delete', function (): void {
        $payout = AffiliatePayout::create([
            'reference' => 'PAY-DEL-' . uniqid(),
            'status' => 'pending',
            'total_minor' => 20000,
            'conversion_count' => 2,
            'currency' => 'USD',
            'owner_type' => Affiliate::class,
            'owner_id' => $this->affiliate->id,
        ]);

        $eventId = AffiliatePayoutEvent::create([
            'affiliate_payout_id' => $payout->id,
            'to_status' => 'created',
            'metadata' => ['note' => 'Test event'],
        ])->id;

        $payout->delete();

        expect(AffiliatePayoutEvent::find($eventId))->toBeNull();
    });

    test('nullifies conversion payout_id on delete', function (): void {
        $payout = AffiliatePayout::create([
            'reference' => 'PAY-NULL-' . uniqid(),
            'status' => 'completed',
            'total_minor' => 30000,
            'conversion_count' => 1,
            'currency' => 'USD',
            'owner_type' => Affiliate::class,
            'owner_id' => $this->affiliate->id,
        ]);

        $conversion = AffiliateConversion::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'affiliate_payout_id' => $payout->id,
            'order_reference' => 'ORD-NULL-001',
            'total_minor' => 30000,
            'commission_minor' => 3000,
            'commission_currency' => 'USD',
            'status' => ConversionStatus::Paid,
            'occurred_at' => now(),
        ]);

        $payout->delete();

        $conversion->refresh();
        expect($conversion->affiliate_payout_id)->toBeNull();
    });

    test('uses correct table name from config', function (): void {
        $payout = new AffiliatePayout;

        expect($payout->getTable())->toBe(config('affiliates.table_names.payouts', 'affiliate_payouts'));
    });
});
