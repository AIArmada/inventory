<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\ConversionStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliatePayout;

describe('AffiliateConversion Model', function (): void {
    beforeEach(function (): void {
        $this->affiliate = Affiliate::create([
            'code' => 'CONV' . uniqid(),
            'name' => 'Conversion Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => 'percentage',
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);
    });

    test('can be created with required fields', function (): void {
        $conversion = AffiliateConversion::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'order_reference' => 'ORD-' . uniqid(),
            'subtotal_minor' => 50000,
            'total_minor' => 55000,
            'commission_minor' => 5500,
            'commission_currency' => 'USD',
            'status' => ConversionStatus::Pending,
            'occurred_at' => now(),
        ]);

        expect($conversion)->toBeInstanceOf(AffiliateConversion::class);
        expect($conversion->subtotal_minor)->toBe(50000);
        expect($conversion->total_minor)->toBe(55000);
        expect($conversion->commission_minor)->toBe(5500);
    });

    test('belongs to affiliate', function (): void {
        $conversion = AffiliateConversion::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'order_reference' => 'ORD-REL-001',
            'total_minor' => 30000,
            'commission_minor' => 3000,
            'commission_currency' => 'USD',
            'status' => ConversionStatus::Pending,
            'occurred_at' => now(),
        ]);

        expect($conversion->affiliate)->toBeInstanceOf(Affiliate::class);
        expect($conversion->affiliate->id)->toBe($this->affiliate->id);
    });

    test('belongs to attribution when set', function (): void {
        $attribution = AffiliateAttribution::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'visitor_fingerprint' => 'conv123',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $conversion = AffiliateConversion::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'affiliate_attribution_id' => $attribution->id,
            'order_reference' => 'ORD-ATR-001',
            'total_minor' => 40000,
            'commission_minor' => 4000,
            'commission_currency' => 'USD',
            'status' => ConversionStatus::Qualified,
            'occurred_at' => now(),
        ]);

        expect($conversion->attribution)->toBeInstanceOf(AffiliateAttribution::class);
        expect($conversion->attribution->id)->toBe($attribution->id);
    });

    test('belongs to payout when set', function (): void {
        $payout = AffiliatePayout::create([
            'reference' => 'PAY-CONV-' . uniqid(),
            'status' => 'completed',
            'total_minor' => 50000,
            'conversion_count' => 5,
            'currency' => 'USD',
            'owner_type' => Affiliate::class,
            'owner_id' => $this->affiliate->id,
        ]);

        $conversion = AffiliateConversion::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'affiliate_payout_id' => $payout->id,
            'order_reference' => 'ORD-PAY-001',
            'total_minor' => 10000,
            'commission_minor' => 1000,
            'commission_currency' => 'USD',
            'status' => ConversionStatus::Paid,
            'occurred_at' => now(),
        ]);

        expect($conversion->payout)->toBeInstanceOf(AffiliatePayout::class);
        expect($conversion->payout->id)->toBe($payout->id);
    });

    test('order_id accessor returns order_reference', function (): void {
        $conversion = AffiliateConversion::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'order_reference' => 'ORD-ALIAS-001',
            'total_minor' => 25000,
            'commission_minor' => 2500,
            'commission_currency' => 'USD',
            'status' => ConversionStatus::Approved,
            'occurred_at' => now(),
        ]);

        expect($conversion->order_id)->toBe('ORD-ALIAS-001');
    });

    test('currency accessor returns commission_currency', function (): void {
        $conversion = AffiliateConversion::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'order_reference' => 'ORD-CUR-001',
            'total_minor' => 30000,
            'commission_minor' => 3000,
            'commission_currency' => 'EUR',
            'status' => ConversionStatus::Pending,
            'occurred_at' => now(),
        ]);

        expect($conversion->currency)->toBe('EUR');
    });

    test('casts status as enum', function (): void {
        $conversion = AffiliateConversion::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'order_reference' => 'ORD-ENUM-001',
            'total_minor' => 20000,
            'commission_minor' => 2000,
            'commission_currency' => 'USD',
            'status' => ConversionStatus::Pending,
            'occurred_at' => now(),
        ]);

        expect($conversion->status)->toBeInstanceOf(ConversionStatus::class);
        expect($conversion->status)->toBe(ConversionStatus::Pending);
    });

    test('supports all conversion statuses', function (): void {
        foreach ([ConversionStatus::Pending, ConversionStatus::Qualified, ConversionStatus::Approved, ConversionStatus::Rejected, ConversionStatus::Paid] as $status) {
            $conversion = AffiliateConversion::create([
                'affiliate_id' => $this->affiliate->id,
                'affiliate_code' => $this->affiliate->code,
                'order_reference' => 'ORD-STATUS-' . $status->value,
                'total_minor' => 10000,
                'commission_minor' => 1000,
                'commission_currency' => 'USD',
                'status' => $status,
                'occurred_at' => now(),
            ]);

            expect($conversion->status)->toBe($status);
        }
    });

    test('casts metadata as array', function (): void {
        $metadata = [
            'customer_id' => 'cust-123',
            'source' => 'web',
            'items_count' => 5,
        ];

        $conversion = AffiliateConversion::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'order_reference' => 'ORD-META-001',
            'total_minor' => 35000,
            'commission_minor' => 3500,
            'commission_currency' => 'USD',
            'status' => ConversionStatus::Approved,
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);

        expect($conversion->metadata)->toBeArray();
        expect($conversion->metadata['customer_id'])->toBe('cust-123');
        expect($conversion->metadata['source'])->toBe('web');
        expect($conversion->metadata['items_count'])->toBe(5);
    });

    test('casts occurred_at as datetime', function (): void {
        $conversion = AffiliateConversion::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'order_reference' => 'ORD-DATE-001',
            'total_minor' => 15000,
            'commission_minor' => 1500,
            'commission_currency' => 'USD',
            'status' => ConversionStatus::Pending,
            'occurred_at' => '2024-06-15 14:30:00',
        ]);

        expect($conversion->occurred_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
        expect($conversion->occurred_at->format('Y-m-d'))->toBe('2024-06-15');
    });

    test('casts approved_at as datetime', function (): void {
        $conversion = AffiliateConversion::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'order_reference' => 'ORD-APPR-001',
            'total_minor' => 45000,
            'commission_minor' => 4500,
            'commission_currency' => 'USD',
            'status' => ConversionStatus::Approved,
            'occurred_at' => '2024-06-10 10:00:00',
            'approved_at' => '2024-06-11 12:00:00',
        ]);

        expect($conversion->approved_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
        expect($conversion->approved_at->format('Y-m-d'))->toBe('2024-06-11');
    });

    test('can store cart information', function (): void {
        $conversion = AffiliateConversion::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'cart_identifier' => 'cart-abc-123',
            'cart_instance' => 'shopping',
            'order_reference' => 'ORD-CART-001',
            'total_minor' => 50000,
            'commission_minor' => 5000,
            'commission_currency' => 'USD',
            'status' => ConversionStatus::Approved,
            'occurred_at' => now(),
        ]);

        expect($conversion->cart_identifier)->toBe('cart-abc-123');
        expect($conversion->cart_instance)->toBe('shopping');
    });

    test('can store voucher code', function (): void {
        $conversion = AffiliateConversion::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'voucher_code' => 'SUMMER20',
            'order_reference' => 'ORD-VOUCH-001',
            'total_minor' => 25000,
            'commission_minor' => 2500,
            'commission_currency' => 'USD',
            'status' => ConversionStatus::Qualified,
            'occurred_at' => now(),
        ]);

        expect($conversion->voucher_code)->toBe('SUMMER20');
    });

    test('can store channel', function (): void {
        $conversion = AffiliateConversion::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'order_reference' => 'ORD-CHAN-001',
            'total_minor' => 30000,
            'commission_minor' => 3000,
            'commission_currency' => 'USD',
            'status' => ConversionStatus::Pending,
            'channel' => 'mobile_app',
            'occurred_at' => now(),
        ]);

        expect($conversion->channel)->toBe('mobile_app');
    });

    test('uses correct table name from config', function (): void {
        $conversion = new AffiliateConversion;

        expect($conversion->getTable())->toBe(config('affiliates.table_names.conversions', 'affiliate_conversions'));
    });
});
