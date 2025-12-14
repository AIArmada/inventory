<?php

declare(strict_types=1);

use AIArmada\Affiliates\Actions\Conversions\MatureConversion;
use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\ConversionStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateBalance;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Services\CommissionMaturityService;

describe('MatureConversion Action', function (): void {
    beforeEach(function (): void {
        $this->action = app(MatureConversion::class);

        $this->affiliate = Affiliate::create([
            'code' => 'MATURE' . uniqid(),
            'name' => 'Maturity Test',
            'status' => AffiliateStatus::Active,
            'commission_type' => 'percentage',
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);
    });

    test('matures qualified conversion after maturity period', function (): void {
        $balance = AffiliateBalance::create([
            'affiliate_id' => $this->affiliate->id,
            'available_minor' => 0,
            'holding_minor' => 5000,
            'lifetime_earnings_minor' => 5000,
            'minimum_payout_minor' => 5000,
            'currency' => 'USD',
        ]);

        $conversion = AffiliateConversion::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'order_reference' => 'ORD-MAT-001',
            'total_minor' => 50000,
            'commission_minor' => 5000,
            'commission_currency' => 'USD',
            'status' => ConversionStatus::Qualified,
            'occurred_at' => now()->subDays(35), // Past maturity
        ]);

        $result = $this->action->handle($conversion);

        expect($result)->toBeTrue();

        $conversion->refresh();
        $balance->refresh();

        expect($conversion->status)->toBe(ConversionStatus::Approved);
        expect($balance->available_minor)->toBe(5000);
        expect($balance->holding_minor)->toBe(0);
    });

    test('does not mature pending conversion', function (): void {
        $conversion = AffiliateConversion::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'order_reference' => 'ORD-PEND-001',
            'total_minor' => 50000,
            'commission_minor' => 5000,
            'commission_currency' => 'USD',
            'status' => ConversionStatus::Pending,
            'occurred_at' => now()->subDays(35),
        ]);

        $result = $this->action->handle($conversion);

        expect($result)->toBeFalse();
    });

    test('does not mature conversion before maturity date', function (): void {
        $conversion = AffiliateConversion::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'order_reference' => 'ORD-EARLY-001',
            'total_minor' => 50000,
            'commission_minor' => 5000,
            'commission_currency' => 'USD',
            'status' => ConversionStatus::Qualified,
            'occurred_at' => now()->subDays(15), // Not yet mature
        ]);

        $result = $this->action->handle($conversion);

        expect($result)->toBeFalse();
    });

    test('creates balance if affiliate has none', function (): void {
        $conversion = AffiliateConversion::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'order_reference' => 'ORD-NEWBAL-001',
            'total_minor' => 50000,
            'commission_minor' => 5000,
            'commission_currency' => 'USD',
            'status' => ConversionStatus::Qualified,
            'occurred_at' => now()->subDays(35),
        ]);

        expect($this->affiliate->balance)->toBeNull();

        $result = $this->action->handle($conversion);

        expect($result)->toBeTrue();

        $this->affiliate->refresh();
        expect($this->affiliate->balance)->not->toBeNull();
        expect($this->affiliate->balance->available_minor)->toBe(5000);
    });

    test('adds matured_at to metadata', function (): void {
        AffiliateBalance::create([
            'affiliate_id' => $this->affiliate->id,
            'available_minor' => 0,
            'holding_minor' => 5000,
            'lifetime_earnings_minor' => 5000,
            'minimum_payout_minor' => 5000,
            'currency' => 'USD',
        ]);

        $conversion = AffiliateConversion::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'order_reference' => 'ORD-META-001',
            'total_minor' => 50000,
            'commission_minor' => 5000,
            'commission_currency' => 'USD',
            'status' => ConversionStatus::Qualified,
            'occurred_at' => now()->subDays(35),
        ]);

        $this->action->handle($conversion);

        $conversion->refresh();
        expect($conversion->metadata)->toHaveKey('matured_at');
    });
});

describe('CommissionMaturityService', function (): void {
    beforeEach(function (): void {
        $this->service = app(CommissionMaturityService::class);

        $this->affiliate = Affiliate::create([
            'code' => 'MATSERV' . uniqid(),
            'name' => 'Maturity Service Test',
            'status' => AffiliateStatus::Active,
            'commission_type' => 'percentage',
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);
    });

    test('processMaturity processes all qualified conversions', function (): void {
        AffiliateBalance::create([
            'affiliate_id' => $this->affiliate->id,
            'available_minor' => 0,
            'holding_minor' => 15000,
            'lifetime_earnings_minor' => 15000,
            'minimum_payout_minor' => 5000,
            'currency' => 'USD',
        ]);

        // Create multiple mature conversions
        for ($i = 1; $i <= 3; $i++) {
            AffiliateConversion::create([
                'affiliate_id' => $this->affiliate->id,
                'affiliate_code' => $this->affiliate->code,
                'order_reference' => "ORD-BATCH-{$i}",
                'total_minor' => 50000,
                'commission_minor' => 5000,
                'commission_currency' => 'USD',
                'status' => ConversionStatus::Qualified,
                'occurred_at' => now()->subDays(35),
            ]);
        }

        $matured = $this->service->processMaturity();

        expect($matured)->toBe(3);
    });

    test('matureConversion works same as action', function (): void {
        $balance = AffiliateBalance::create([
            'affiliate_id' => $this->affiliate->id,
            'available_minor' => 0,
            'holding_minor' => 5000,
            'lifetime_earnings_minor' => 5000,
            'minimum_payout_minor' => 5000,
            'currency' => 'USD',
        ]);

        $conversion = AffiliateConversion::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'order_reference' => 'ORD-SERV-001',
            'total_minor' => 50000,
            'commission_minor' => 5000,
            'commission_currency' => 'USD',
            'status' => ConversionStatus::Qualified,
            'occurred_at' => now()->subDays(35),
        ]);

        $result = $this->service->matureConversion($conversion);

        expect($result)->toBeTrue();

        $balance->refresh();
        expect($balance->available_minor)->toBe(5000);
    });

    test('getMaturityDate returns correct date', function (): void {
        $conversion = AffiliateConversion::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'order_reference' => 'ORD-DATE-001',
            'total_minor' => 50000,
            'commission_minor' => 5000,
            'commission_currency' => 'USD',
            'status' => ConversionStatus::Qualified,
            'occurred_at' => now()->subDays(10),
        ]);

        $maturityDate = $this->service->getMaturityDate($conversion);

        // Default maturity is 30 days
        expect($maturityDate->format('Y-m-d'))->toBe(now()->addDays(20)->format('Y-m-d'));
    });

    test('isMature returns true for mature conversion', function (): void {
        $conversion = AffiliateConversion::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'order_reference' => 'ORD-ISMATURE-001',
            'total_minor' => 50000,
            'commission_minor' => 5000,
            'commission_currency' => 'USD',
            'status' => ConversionStatus::Qualified,
            'occurred_at' => now()->subDays(35),
        ]);

        expect($this->service->isMature($conversion))->toBeTrue();
    });

    test('isMature returns false for immature conversion', function (): void {
        $conversion = AffiliateConversion::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'order_reference' => 'ORD-NOTMATURE-001',
            'total_minor' => 50000,
            'commission_minor' => 5000,
            'commission_currency' => 'USD',
            'status' => ConversionStatus::Qualified,
            'occurred_at' => now()->subDays(10),
        ]);

        expect($this->service->isMature($conversion))->toBeFalse();
    });

    test('getPendingMaturity returns total pending amount', function (): void {
        // Create qualified conversions
        AffiliateConversion::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'order_reference' => 'ORD-PENDING-001',
            'total_minor' => 50000,
            'commission_minor' => 5000,
            'commission_currency' => 'USD',
            'status' => ConversionStatus::Qualified,
            'occurred_at' => now()->subDays(10),
        ]);

        AffiliateConversion::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'order_reference' => 'ORD-PENDING-002',
            'total_minor' => 30000,
            'commission_minor' => 3000,
            'commission_currency' => 'USD',
            'status' => ConversionStatus::Qualified,
            'occurred_at' => now()->subDays(15),
        ]);

        // Approved conversion should not count
        AffiliateConversion::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'order_reference' => 'ORD-APPROVED-001',
            'total_minor' => 100000,
            'commission_minor' => 10000,
            'commission_currency' => 'USD',
            'status' => 'approved',
            'occurred_at' => now()->subDays(40),
        ]);

        $pending = $this->service->getPendingMaturity($this->affiliate);

        expect($pending)->toBe(8000);
    });

    test('getMaturingWithin returns conversions maturing within period', function (): void {
        // Conversion maturing in 5 days
        AffiliateConversion::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'order_reference' => 'ORD-SOON-001',
            'total_minor' => 50000,
            'commission_minor' => 5000,
            'commission_currency' => 'USD',
            'status' => ConversionStatus::Qualified,
            'occurred_at' => now()->subDays(25), // Matures in 5 days
        ]);

        // Conversion maturing in 20 days
        AffiliateConversion::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'order_reference' => 'ORD-LATER-001',
            'total_minor' => 30000,
            'commission_minor' => 3000,
            'commission_currency' => 'USD',
            'status' => ConversionStatus::Qualified,
            'occurred_at' => now()->subDays(10), // Matures in 20 days
        ]);

        $maturing = $this->service->getMaturingWithin($this->affiliate, 7);

        expect($maturing)->toBeArray();

        foreach ($maturing as $item) {
            expect($item)->toHaveKey('id');
            expect($item)->toHaveKey('commission_minor');
            expect($item)->toHaveKey('matures_at');
            expect($item)->toHaveKey('days_remaining');
        }
    });

    test('processMaturity handles affiliate without balance', function (): void {
        AffiliateConversion::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'order_reference' => 'ORD-NOBAL-001',
            'total_minor' => 50000,
            'commission_minor' => 5000,
            'commission_currency' => 'USD',
            'status' => ConversionStatus::Qualified,
            'occurred_at' => now()->subDays(35),
        ]);

        $matured = $this->service->processMaturity();

        expect($matured)->toBe(1);

        $this->affiliate->refresh();
        expect($this->affiliate->balance)->not->toBeNull();
    });
});
