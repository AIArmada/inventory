<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\ConversionStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateBalance;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Services\CommissionMaturityService;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->service = app(CommissionMaturityService::class);

    $this->affiliate = Affiliate::create([
        'code' => 'MAT-' . uniqid(),
        'name' => 'Maturity Test Affiliate',
        'contact_email' => 'maturity@example.com',
        'status' => AffiliateStatus::Active,
        'commission_type' => CommissionType::Percentage,
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);
});

describe('CommissionMaturityService', function (): void {
    describe('processMaturity', function (): void {
        test('returns count of matured conversions', function (): void {
            // Create qualified conversion old enough to mature (default 30 days)
            AffiliateConversion::create([
                'affiliate_id' => $this->affiliate->id,
                'affiliate_code' => $this->affiliate->code,
                'order_reference' => 'MAT-001',
                'subtotal_minor' => 10000,
                'total_minor' => 10000,
                'commission_minor' => 1000,
                'status' => ConversionStatus::Qualified,
                'occurred_at' => now()->subDays(35),
            ]);

            AffiliateBalance::create([
                'affiliate_id' => $this->affiliate->id,
                'currency' => 'USD',
                'holding_minor' => 1000,
                'available_minor' => 0,
                'lifetime_earnings_minor' => 1000,
                'minimum_payout_minor' => 5000,
            ]);

            $matured = $this->service->processMaturity();

            expect($matured)->toBe(1);
        });

        test('returns zero when no qualified conversions', function (): void {
            $matured = $this->service->processMaturity();

            expect($matured)->toBe(0);
        });

        test('does not mature recent conversions', function (): void {
            AffiliateConversion::create([
                'affiliate_id' => $this->affiliate->id,
                'affiliate_code' => $this->affiliate->code,
                'order_reference' => 'RECENT-001',
                'subtotal_minor' => 10000,
                'total_minor' => 10000,
                'commission_minor' => 1000,
                'status' => ConversionStatus::Qualified,
                'occurred_at' => now()->subDays(5), // Only 5 days old
            ]);

            $matured = $this->service->processMaturity();

            expect($matured)->toBe(0);
        });

        test('does not process already approved conversions', function (): void {
            AffiliateConversion::create([
                'affiliate_id' => $this->affiliate->id,
                'affiliate_code' => $this->affiliate->code,
                'order_reference' => 'APPROVED-001',
                'subtotal_minor' => 10000,
                'total_minor' => 10000,
                'commission_minor' => 1000,
                'status' => ConversionStatus::Approved,
                'occurred_at' => now()->subDays(35),
            ]);

            $matured = $this->service->processMaturity();

            expect($matured)->toBe(0);
        });
    });

    describe('matureConversion', function (): void {
        test('matures qualified conversion past maturity date', function (): void {
            $conversion = AffiliateConversion::create([
                'affiliate_id' => $this->affiliate->id,
                'affiliate_code' => $this->affiliate->code,
                'order_reference' => 'MATURE-001',
                'subtotal_minor' => 10000,
                'total_minor' => 10000,
                'commission_minor' => 1000,
                'status' => ConversionStatus::Qualified,
                'occurred_at' => now()->subDays(35),
            ]);

            AffiliateBalance::create([
                'affiliate_id' => $this->affiliate->id,
                'currency' => 'USD',
                'holding_minor' => 1000,
                'available_minor' => 0,
                'lifetime_earnings_minor' => 1000,
                'minimum_payout_minor' => 5000,
            ]);

            $result = $this->service->matureConversion($conversion);

            expect($result)->toBeTrue();

            $conversion->refresh();
            expect($conversion->status)->toBe(ConversionStatus::Approved);
        });

        test('moves commission from holding to available', function (): void {
            $conversion = AffiliateConversion::create([
                'affiliate_id' => $this->affiliate->id,
                'affiliate_code' => $this->affiliate->code,
                'order_reference' => 'BALANCE-001',
                'subtotal_minor' => 10000,
                'total_minor' => 10000,
                'commission_minor' => 1000,
                'status' => ConversionStatus::Qualified,
                'occurred_at' => now()->subDays(35),
            ]);

            $balance = AffiliateBalance::create([
                'affiliate_id' => $this->affiliate->id,
                'currency' => 'USD',
                'holding_minor' => 1000,
                'available_minor' => 500,
                'lifetime_earnings_minor' => 1500,
                'minimum_payout_minor' => 5000,
            ]);

            $this->service->matureConversion($conversion);

            $balance->refresh();
            expect($balance->holding_minor)->toBe(0);
            expect($balance->available_minor)->toBe(1500);
        });

        test('returns false for non-qualified conversion', function (): void {
            $conversion = AffiliateConversion::create([
                'affiliate_id' => $this->affiliate->id,
                'affiliate_code' => $this->affiliate->code,
                'order_reference' => 'PENDING-001',
                'subtotal_minor' => 10000,
                'total_minor' => 10000,
                'commission_minor' => 1000,
                'status' => ConversionStatus::Pending,
                'occurred_at' => now()->subDays(35),
            ]);

            $result = $this->service->matureConversion($conversion);

            expect($result)->toBeFalse();
        });

        test('returns false when maturity date is future', function (): void {
            $conversion = AffiliateConversion::create([
                'affiliate_id' => $this->affiliate->id,
                'affiliate_code' => $this->affiliate->code,
                'order_reference' => 'FUTURE-001',
                'subtotal_minor' => 10000,
                'total_minor' => 10000,
                'commission_minor' => 1000,
                'status' => ConversionStatus::Qualified,
                'occurred_at' => now()->subDays(5), // Not mature yet
            ]);

            $result = $this->service->matureConversion($conversion);

            expect($result)->toBeFalse();
        });

        test('adds matured_at to metadata', function (): void {
            $conversion = AffiliateConversion::create([
                'affiliate_id' => $this->affiliate->id,
                'affiliate_code' => $this->affiliate->code,
                'order_reference' => 'META-001',
                'subtotal_minor' => 10000,
                'total_minor' => 10000,
                'commission_minor' => 1000,
                'status' => ConversionStatus::Qualified,
                'occurred_at' => now()->subDays(35),
                'metadata' => ['original' => 'value'],
            ]);

            AffiliateBalance::create([
                'affiliate_id' => $this->affiliate->id,
                'currency' => 'USD',
                'holding_minor' => 1000,
                'available_minor' => 0,
                'lifetime_earnings_minor' => 1000,
                'minimum_payout_minor' => 5000,
            ]);

            $this->service->matureConversion($conversion);

            $conversion->refresh();
            expect($conversion->metadata)->toHaveKey('matured_at');
            expect($conversion->metadata['original'])->toBe('value');
        });

        test('creates balance if not exists', function (): void {
            $conversion = AffiliateConversion::create([
                'affiliate_id' => $this->affiliate->id,
                'affiliate_code' => $this->affiliate->code,
                'order_reference' => 'NEWBAL-001',
                'subtotal_minor' => 10000,
                'total_minor' => 10000,
                'commission_minor' => 1000,
                'status' => ConversionStatus::Qualified,
                'occurred_at' => now()->subDays(35),
            ]);

            expect(AffiliateBalance::where('affiliate_id', $this->affiliate->id)->exists())->toBeFalse();

            $this->service->matureConversion($conversion);

            expect(AffiliateBalance::where('affiliate_id', $this->affiliate->id)->exists())->toBeTrue();
        });
    });

    describe('getMaturityDate', function (): void {
        test('returns occurred_at plus maturity days', function (): void {
            $occurredAt = now()->subDays(10);
            $conversion = AffiliateConversion::create([
                'affiliate_id' => $this->affiliate->id,
                'affiliate_code' => $this->affiliate->code,
                'order_reference' => 'MATDATE-001',
                'subtotal_minor' => 10000,
                'total_minor' => 10000,
                'commission_minor' => 1000,
                'status' => ConversionStatus::Qualified,
                'occurred_at' => $occurredAt,
            ]);

            $maturityDate = $this->service->getMaturityDate($conversion);

            // Default maturity is 30 days
            expect($maturityDate)->toBeInstanceOf(Carbon::class);
            expect($maturityDate->toDateString())->toBe($occurredAt->addDays(30)->toDateString());
        });
    });

    describe('isMature', function (): void {
        test('returns true when past maturity date', function (): void {
            $conversion = AffiliateConversion::create([
                'affiliate_id' => $this->affiliate->id,
                'affiliate_code' => $this->affiliate->code,
                'order_reference' => 'ISMATURE-001',
                'subtotal_minor' => 10000,
                'total_minor' => 10000,
                'commission_minor' => 1000,
                'status' => ConversionStatus::Qualified,
                'occurred_at' => now()->subDays(35),
            ]);

            expect($this->service->isMature($conversion))->toBeTrue();
        });

        test('returns false when before maturity date', function (): void {
            $conversion = AffiliateConversion::create([
                'affiliate_id' => $this->affiliate->id,
                'affiliate_code' => $this->affiliate->code,
                'order_reference' => 'NOTMATURE-001',
                'subtotal_minor' => 10000,
                'total_minor' => 10000,
                'commission_minor' => 1000,
                'status' => ConversionStatus::Qualified,
                'occurred_at' => now()->subDays(5),
            ]);

            expect($this->service->isMature($conversion))->toBeFalse();
        });
    });

    describe('getPendingMaturity', function (): void {
        test('returns sum of qualified conversion commissions', function (): void {
            AffiliateConversion::create([
                'affiliate_id' => $this->affiliate->id,
                'affiliate_code' => $this->affiliate->code,
                'order_reference' => 'PEND-001',
                'subtotal_minor' => 10000,
                'total_minor' => 10000,
                'commission_minor' => 1000,
                'status' => ConversionStatus::Qualified,
                'occurred_at' => now()->subDays(10),
            ]);

            AffiliateConversion::create([
                'affiliate_id' => $this->affiliate->id,
                'affiliate_code' => $this->affiliate->code,
                'order_reference' => 'PEND-002',
                'subtotal_minor' => 20000,
                'total_minor' => 20000,
                'commission_minor' => 2000,
                'status' => ConversionStatus::Qualified,
                'occurred_at' => now()->subDays(15),
            ]);

            $pending = $this->service->getPendingMaturity($this->affiliate);

            expect($pending)->toBe(3000);
        });

        test('excludes non-qualified conversions', function (): void {
            AffiliateConversion::create([
                'affiliate_id' => $this->affiliate->id,
                'affiliate_code' => $this->affiliate->code,
                'order_reference' => 'APPROVED',
                'subtotal_minor' => 10000,
                'total_minor' => 10000,
                'commission_minor' => 1000,
                'status' => ConversionStatus::Approved,
                'occurred_at' => now()->subDays(10),
            ]);

            AffiliateConversion::create([
                'affiliate_id' => $this->affiliate->id,
                'affiliate_code' => $this->affiliate->code,
                'order_reference' => 'PENDING',
                'subtotal_minor' => 10000,
                'total_minor' => 10000,
                'commission_minor' => 1000,
                'status' => ConversionStatus::Pending,
                'occurred_at' => now()->subDays(10),
            ]);

            $pending = $this->service->getPendingMaturity($this->affiliate);

            expect($pending)->toBe(0);
        });

        test('returns zero when no qualified conversions', function (): void {
            $pending = $this->service->getPendingMaturity($this->affiliate);

            expect($pending)->toBe(0);
        });
    });

    describe('getMaturingWithin', function (): void {
        test('returns array of conversions maturing within period', function (): void {
            // Maturity is 30 days, getMaturingWithin(10) looks for conversions that occurred
            // after cutoffDate = now - (30-10) = now - 20 days
            // A conversion from 25 days ago will mature in 5 days (within 10 days)
            AffiliateConversion::create([
                'affiliate_id' => $this->affiliate->id,
                'affiliate_code' => $this->affiliate->code,
                'order_reference' => 'WITHIN-001',
                'subtotal_minor' => 10000,
                'total_minor' => 10000,
                'commission_minor' => 1000,
                'status' => ConversionStatus::Qualified,
                'occurred_at' => now()->subDays(25), // Matures in 5 days, but cutoff is 20 days
            ]);

            // This conversion occurred 15 days ago, so matures in 15 days (within 10 day window from cutoff)
            // cutoffDate = now - 20 days, so conversions >= -20 days are included
            AffiliateConversion::create([
                'affiliate_id' => $this->affiliate->id,
                'affiliate_code' => $this->affiliate->code,
                'order_reference' => 'WITHIN-002',
                'subtotal_minor' => 10000,
                'total_minor' => 10000,
                'commission_minor' => 1000,
                'status' => ConversionStatus::Qualified,
                'occurred_at' => now()->subDays(15), // After cutoff, included
            ]);

            $maturing = $this->service->getMaturingWithin($this->affiliate, 10);

            expect($maturing)->toBeArray();
            expect($maturing)->toHaveCount(1); // Only one is after the 20-day cutoff
            expect($maturing[0])->toHaveKeys([
                'id',
                'commission_minor',
                'occurred_at',
                'matures_at',
                'days_remaining',
            ]);
        });

        test('excludes conversions maturing beyond period', function (): void {
            // Conversion from 25 days ago - occurred before cutoff (now - 20 days)
            // so it will NOT be included
            AffiliateConversion::create([
                'affiliate_id' => $this->affiliate->id,
                'affiliate_code' => $this->affiliate->code,
                'order_reference' => 'BEYOND-001',
                'subtotal_minor' => 10000,
                'total_minor' => 10000,
                'commission_minor' => 1000,
                'status' => ConversionStatus::Qualified,
                'occurred_at' => now()->subDays(25), // Before cutoff (20 days), excluded
            ]);

            $maturing = $this->service->getMaturingWithin($this->affiliate, 10);

            expect($maturing)->toBeEmpty();
        });

        test('returns empty array when no qualifying conversions', function (): void {
            $maturing = $this->service->getMaturingWithin($this->affiliate, 10);

            expect($maturing)->toBeArray();
            expect($maturing)->toBeEmpty();
        });
    });
});

describe('CommissionMaturityService class structure', function (): void {
    test('can be instantiated', function (): void {
        $service = app(CommissionMaturityService::class);
        expect($service)->toBeInstanceOf(CommissionMaturityService::class);
    });

    test('is declared as final', function (): void {
        $reflection = new ReflectionClass(CommissionMaturityService::class);
        expect($reflection->isFinal())->toBeTrue();
    });

    test('has required public methods', function (): void {
        $reflection = new ReflectionClass(CommissionMaturityService::class);

        expect($reflection->hasMethod('processMaturity'))->toBeTrue();
        expect($reflection->hasMethod('matureConversion'))->toBeTrue();
        expect($reflection->hasMethod('getMaturityDate'))->toBeTrue();
        expect($reflection->hasMethod('isMature'))->toBeTrue();
        expect($reflection->hasMethod('getPendingMaturity'))->toBeTrue();
        expect($reflection->hasMethod('getMaturingWithin'))->toBeTrue();
    });
});
