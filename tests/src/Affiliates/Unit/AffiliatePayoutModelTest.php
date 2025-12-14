<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\ConversionStatus;
use AIArmada\Affiliates\Enums\PayoutStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Models\AffiliatePayoutEvent;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

describe('AffiliatePayout Model', function (): void {
    beforeEach(function (): void {
        $this->affiliate = Affiliate::create([
            'code' => 'PAYOUT-TEST-' . uniqid(),
            'name' => 'Payout Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);
    });

    it('can be created with required fields', function (): void {
        $payout = AffiliatePayout::create([
            'reference' => 'PAY-' . uniqid(),
            'owner_type' => Affiliate::class,
            'owner_id' => $this->affiliate->id,
            'status' => PayoutStatus::Pending,
            'total_minor' => 50000,
            'conversion_count' => 5,
            'currency' => 'USD',
        ]);

        expect($payout)->toBeInstanceOf(AffiliatePayout::class)
            ->and($payout->status)->toBe(PayoutStatus::Pending)
            ->and($payout->total_minor)->toBe(50000)
            ->and($payout->conversion_count)->toBe(5);
    });

    it('has polymorphic owner relationship', function (): void {
        $payout = AffiliatePayout::create([
            'reference' => 'PAY-' . uniqid(),
            'owner_type' => Affiliate::class,
            'owner_id' => $this->affiliate->id,
            'status' => PayoutStatus::Pending,
            'total_minor' => 50000,
            'conversion_count' => 5,
            'currency' => 'USD',
        ]);

        expect($payout->owner())->toBeInstanceOf(MorphTo::class)
            ->and($payout->owner)->toBeInstanceOf(Affiliate::class)
            ->and($payout->owner->id)->toBe($this->affiliate->id);
    });

    it('has many conversions', function (): void {
        $payout = AffiliatePayout::create([
            'reference' => 'PAY-' . uniqid(),
            'owner_type' => Affiliate::class,
            'owner_id' => $this->affiliate->id,
            'status' => PayoutStatus::Pending,
            'total_minor' => 50000,
            'conversion_count' => 5,
            'currency' => 'USD',
        ]);

        expect($payout->conversions())->toBeInstanceOf(HasMany::class);
    });

    it('has many events', function (): void {
        $payout = AffiliatePayout::create([
            'reference' => 'PAY-' . uniqid(),
            'owner_type' => Affiliate::class,
            'owner_id' => $this->affiliate->id,
            'status' => PayoutStatus::Pending,
            'total_minor' => 50000,
            'conversion_count' => 5,
            'currency' => 'USD',
        ]);

        expect($payout->events())->toBeInstanceOf(HasMany::class);
    });

    it('returns affiliate accessor when owner is affiliate', function (): void {
        $payout = AffiliatePayout::create([
            'reference' => 'PAY-' . uniqid(),
            'owner_type' => Affiliate::class,
            'owner_id' => $this->affiliate->id,
            'status' => PayoutStatus::Pending,
            'total_minor' => 50000,
            'conversion_count' => 5,
            'currency' => 'USD',
        ]);

        expect($payout->affiliate)->toBeInstanceOf(Affiliate::class)
            ->and($payout->affiliate->id)->toBe($this->affiliate->id);
    });

    it('returns amount_minor accessor aliasing total_minor', function (): void {
        $payout = AffiliatePayout::create([
            'reference' => 'PAY-' . uniqid(),
            'owner_type' => Affiliate::class,
            'owner_id' => $this->affiliate->id,
            'status' => PayoutStatus::Pending,
            'total_minor' => 12345,
            'conversion_count' => 1,
            'currency' => 'USD',
        ]);

        expect($payout->amount_minor)->toBe(12345);
    });

    it('returns external_reference from metadata', function (): void {
        $payout = AffiliatePayout::create([
            'reference' => 'PAY-' . uniqid(),
            'owner_type' => Affiliate::class,
            'owner_id' => $this->affiliate->id,
            'status' => PayoutStatus::Pending,
            'total_minor' => 50000,
            'conversion_count' => 5,
            'currency' => 'USD',
            'metadata' => ['external_reference' => 'EXT-REF-123'],
        ]);

        expect($payout->external_reference)->toBe('EXT-REF-123');
    });

    it('returns null external_reference when not in metadata', function (): void {
        $payout = AffiliatePayout::create([
            'reference' => 'PAY-' . uniqid(),
            'owner_type' => Affiliate::class,
            'owner_id' => $this->affiliate->id,
            'status' => PayoutStatus::Pending,
            'total_minor' => 50000,
            'conversion_count' => 5,
            'currency' => 'USD',
        ]);

        expect($payout->external_reference)->toBeNull();
    });

    it('returns notes from metadata', function (): void {
        $payout = AffiliatePayout::create([
            'reference' => 'PAY-' . uniqid(),
            'owner_type' => Affiliate::class,
            'owner_id' => $this->affiliate->id,
            'status' => PayoutStatus::Pending,
            'total_minor' => 50000,
            'conversion_count' => 5,
            'currency' => 'USD',
            'metadata' => ['notes' => 'Monthly payout for December'],
        ]);

        expect($payout->notes)->toBe('Monthly payout for December');
    });

    it('returns null notes when not in metadata', function (): void {
        $payout = AffiliatePayout::create([
            'reference' => 'PAY-' . uniqid(),
            'owner_type' => Affiliate::class,
            'owner_id' => $this->affiliate->id,
            'status' => PayoutStatus::Pending,
            'total_minor' => 50000,
            'conversion_count' => 5,
            'currency' => 'USD',
        ]);

        expect($payout->notes)->toBeNull();
    });

    it('casts metadata as array', function (): void {
        $payout = AffiliatePayout::create([
            'reference' => 'PAY-' . uniqid(),
            'owner_type' => Affiliate::class,
            'owner_id' => $this->affiliate->id,
            'status' => PayoutStatus::Pending,
            'total_minor' => 50000,
            'conversion_count' => 5,
            'currency' => 'USD',
            'metadata' => ['key' => 'value'],
        ]);

        expect($payout->metadata)->toBeArray()
            ->and($payout->metadata['key'])->toBe('value');
    });

    it('casts scheduled_at as datetime', function (): void {
        $scheduledAt = Carbon::now()->addDay();
        $payout = AffiliatePayout::create([
            'reference' => 'PAY-' . uniqid(),
            'owner_type' => Affiliate::class,
            'owner_id' => $this->affiliate->id,
            'status' => PayoutStatus::Pending,
            'total_minor' => 50000,
            'conversion_count' => 5,
            'currency' => 'USD',
            'scheduled_at' => $scheduledAt,
        ]);

        expect($payout->scheduled_at)->toBeInstanceOf(Carbon::class);
    });

    it('casts paid_at as datetime', function (): void {
        $paidAt = Carbon::now();
        $payout = AffiliatePayout::create([
            'reference' => 'PAY-' . uniqid(),
            'owner_type' => Affiliate::class,
            'owner_id' => $this->affiliate->id,
            'status' => PayoutStatus::Completed,
            'total_minor' => 50000,
            'conversion_count' => 5,
            'currency' => 'USD',
            'paid_at' => $paidAt,
        ]);

        expect($payout->paid_at)->toBeInstanceOf(Carbon::class);
    });

    it('cascade deletes events on delete', function (): void {
        $payout = AffiliatePayout::create([
            'reference' => 'PAY-' . uniqid(),
            'owner_type' => Affiliate::class,
            'owner_id' => $this->affiliate->id,
            'status' => PayoutStatus::Pending,
            'total_minor' => 50000,
            'conversion_count' => 5,
            'currency' => 'USD',
        ]);

        AffiliatePayoutEvent::create([
            'affiliate_payout_id' => $payout->id,
            'from_status' => null,
            'to_status' => 'pending',
        ]);

        $payout->delete();

        expect(AffiliatePayoutEvent::where('affiliate_payout_id', $payout->id)->count())->toBe(0);
    });

    it('nulls affiliate_payout_id on conversions when deleted', function (): void {
        $payout = AffiliatePayout::create([
            'reference' => 'PAY-' . uniqid(),
            'owner_type' => Affiliate::class,
            'owner_id' => $this->affiliate->id,
            'status' => PayoutStatus::Pending,
            'total_minor' => 50000,
            'conversion_count' => 5,
            'currency' => 'USD',
        ]);

        $conversion = AffiliateConversion::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'affiliate_payout_id' => $payout->id,
            'order_reference' => 'ORDER-001',
            'total_minor' => 10000,
            'commission_minor' => 1000,
            'commission_currency' => 'USD',
            'status' => ConversionStatus::Paid,
        ]);

        $payout->delete();
        $conversion->refresh();

        expect($conversion->affiliate_payout_id)->toBeNull();
    });

    it('uses correct table name from config', function (): void {
        $payout = new AffiliatePayout;

        expect($payout->getTable())->toBe('affiliate_payouts');
    });
});
