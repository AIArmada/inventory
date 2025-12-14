<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateDailyStat;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

describe('AffiliateDailyStat Model', function (): void {
    it('can be created with required fields', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'STAT-TEST-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $stat = AffiliateDailyStat::create([
            'affiliate_id' => $affiliate->id,
            'date' => '2024-01-15',
            'clicks' => 100,
            'unique_clicks' => 80,
            'attributions' => 10,
            'conversions' => 5,
            'revenue_cents' => 50000,
            'commission_cents' => 5000,
            'refunds' => 1,
            'refund_amount_cents' => 1000,
            'conversion_rate' => 5.0,
            'epc_cents' => 50.0,
        ]);

        expect($stat)->toBeInstanceOf(AffiliateDailyStat::class)
            ->and($stat->clicks)->toBe(100)
            ->and($stat->unique_clicks)->toBe(80)
            ->and($stat->conversions)->toBe(5)
            ->and($stat->revenue_cents)->toBe(50000);
    });

    it('belongs to an affiliate', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'STAT-AFF-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $stat = AffiliateDailyStat::create([
            'affiliate_id' => $affiliate->id,
            'date' => '2024-01-16',
            'clicks' => 50,
            'unique_clicks' => 40,
            'conversions' => 2,
            'revenue_cents' => 20000,
            'commission_cents' => 2000,
        ]);

        expect($stat->affiliate())->toBeInstanceOf(BelongsTo::class)
            ->and($stat->affiliate->id)->toBe($affiliate->id);
    });

    it('has revenue_minor accessor', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'STAT-REV-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $stat = AffiliateDailyStat::create([
            'affiliate_id' => $affiliate->id,
            'date' => '2024-01-17',
            'clicks' => 75,
            'unique_clicks' => 60,
            'conversions' => 3,
            'revenue_cents' => 35000,
            'commission_cents' => 3500,
        ]);

        expect($stat->revenue_minor)->toBe(35000)
            ->and($stat->revenue_minor)->toBe($stat->revenue_cents);
    });

    it('has commission_minor accessor', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'STAT-COMM-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $stat = AffiliateDailyStat::create([
            'affiliate_id' => $affiliate->id,
            'date' => '2024-01-18',
            'clicks' => 200,
            'unique_clicks' => 150,
            'conversions' => 10,
            'revenue_cents' => 100000,
            'commission_cents' => 10000,
        ]);

        expect($stat->commission_minor)->toBe(10000)
            ->and($stat->commission_minor)->toBe($stat->commission_cents);
    });

    it('casts date as carbon date', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'STAT-DATE-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $stat = AffiliateDailyStat::create([
            'affiliate_id' => $affiliate->id,
            'date' => '2024-06-15',
            'clicks' => 50,
            'conversions' => 2,
            'revenue_cents' => 10000,
            'commission_cents' => 1000,
        ]);

        expect($stat->date)->toBeInstanceOf(Illuminate\Support\Carbon::class)
            ->and($stat->date->format('Y-m-d'))->toBe('2024-06-15');
    });

    it('casts numeric fields as integers', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'STAT-INT-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $stat = AffiliateDailyStat::create([
            'affiliate_id' => $affiliate->id,
            'date' => '2024-01-20',
            'clicks' => '100',
            'unique_clicks' => '80',
            'attributions' => '10',
            'conversions' => '5',
            'revenue_cents' => '50000',
            'commission_cents' => '5000',
            'refunds' => '1',
            'refund_amount_cents' => '1000',
        ]);

        expect($stat->clicks)->toBeInt()
            ->and($stat->unique_clicks)->toBeInt()
            ->and($stat->attributions)->toBeInt()
            ->and($stat->conversions)->toBeInt()
            ->and($stat->revenue_cents)->toBeInt()
            ->and($stat->commission_cents)->toBeInt();
    });

    it('casts conversion_rate and epc_cents as floats', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'STAT-FLOAT-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $stat = AffiliateDailyStat::create([
            'affiliate_id' => $affiliate->id,
            'date' => '2024-01-21',
            'clicks' => 100,
            'conversions' => 5,
            'revenue_cents' => 50000,
            'commission_cents' => 5000,
            'conversion_rate' => 5.5,
            'epc_cents' => 50.25,
        ]);

        expect($stat->conversion_rate)->toBeFloat()
            ->and($stat->epc_cents)->toBeFloat();
    });

    it('stores breakdown as array', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'STAT-BREAK-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $stat = AffiliateDailyStat::create([
            'affiliate_id' => $affiliate->id,
            'date' => '2024-01-22',
            'clicks' => 100,
            'conversions' => 5,
            'revenue_cents' => 50000,
            'commission_cents' => 5000,
            'breakdown' => [
                'by_product' => ['SKU001' => 3, 'SKU002' => 2],
                'by_country' => ['US' => 4, 'UK' => 1],
            ],
        ]);

        expect($stat->breakdown)->toBeArray()
            ->and($stat->breakdown['by_product'])->toBeArray()
            ->and($stat->breakdown['by_product']['SKU001'])->toBe(3);
    });

    it('uses correct table name from config', function (): void {
        $stat = new AffiliateDailyStat;
        expect($stat->getTable())->toBe('affiliate_daily_stats');
    });
});
