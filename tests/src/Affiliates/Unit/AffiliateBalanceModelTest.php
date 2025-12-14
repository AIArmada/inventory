<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateBalance;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

describe('AffiliateBalance Model', function (): void {
    beforeEach(function (): void {
        $this->affiliate = Affiliate::create([
            'code' => 'BAL-TEST-' . uniqid(),
            'name' => 'Balance Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);
    });

    it('can be created with required fields', function (): void {
        $balance = AffiliateBalance::create([
            'affiliate_id' => $this->affiliate->id,
            'currency' => 'USD',
            'holding_minor' => 5000,
            'available_minor' => 10000,
            'lifetime_earnings_minor' => 50000,
            'minimum_payout_minor' => 1000,
        ]);

        expect($balance)->toBeInstanceOf(AffiliateBalance::class)
            ->and($balance->holding_minor)->toBe(5000)
            ->and($balance->available_minor)->toBe(10000)
            ->and($balance->lifetime_earnings_minor)->toBe(50000)
            ->and($balance->minimum_payout_minor)->toBe(1000);
    });

    it('belongs to affiliate', function (): void {
        $balance = AffiliateBalance::create([
            'affiliate_id' => $this->affiliate->id,
            'currency' => 'USD',
            'holding_minor' => 0,
            'available_minor' => 0,
            'lifetime_earnings_minor' => 0,
            'minimum_payout_minor' => 1000,
        ]);

        expect($balance->affiliate())->toBeInstanceOf(BelongsTo::class)
            ->and($balance->affiliate->id)->toBe($this->affiliate->id);
    });

    it('calculates total balance correctly', function (): void {
        $balance = AffiliateBalance::create([
            'affiliate_id' => $this->affiliate->id,
            'currency' => 'USD',
            'holding_minor' => 5000,
            'available_minor' => 10000,
            'lifetime_earnings_minor' => 15000,
            'minimum_payout_minor' => 1000,
        ]);

        expect($balance->getTotalBalanceMinor())->toBe(15000);
    });

    it('returns true for canRequestPayout when available exceeds minimum', function (): void {
        $balance = AffiliateBalance::create([
            'affiliate_id' => $this->affiliate->id,
            'currency' => 'USD',
            'holding_minor' => 0,
            'available_minor' => 5000,
            'lifetime_earnings_minor' => 5000,
            'minimum_payout_minor' => 1000,
        ]);

        expect($balance->canRequestPayout())->toBeTrue();
    });

    it('returns false for canRequestPayout when available is below minimum', function (): void {
        $balance = AffiliateBalance::create([
            'affiliate_id' => $this->affiliate->id,
            'currency' => 'USD',
            'holding_minor' => 0,
            'available_minor' => 500,
            'lifetime_earnings_minor' => 500,
            'minimum_payout_minor' => 1000,
        ]);

        expect($balance->canRequestPayout())->toBeFalse();
    });

    it('returns true for canRequestPayout when available equals minimum', function (): void {
        $balance = AffiliateBalance::create([
            'affiliate_id' => $this->affiliate->id,
            'currency' => 'USD',
            'holding_minor' => 0,
            'available_minor' => 1000,
            'lifetime_earnings_minor' => 1000,
            'minimum_payout_minor' => 1000,
        ]);

        expect($balance->canRequestPayout())->toBeTrue();
    });

    it('adds to holding and lifetime earnings', function (): void {
        $balance = AffiliateBalance::create([
            'affiliate_id' => $this->affiliate->id,
            'currency' => 'USD',
            'holding_minor' => 0,
            'available_minor' => 0,
            'lifetime_earnings_minor' => 0,
            'minimum_payout_minor' => 1000,
        ]);

        $balance->addToHolding(5000);
        $balance->refresh();

        expect($balance->holding_minor)->toBe(5000)
            ->and($balance->lifetime_earnings_minor)->toBe(5000);
    });

    it('releases from holding to available', function (): void {
        $balance = AffiliateBalance::create([
            'affiliate_id' => $this->affiliate->id,
            'currency' => 'USD',
            'holding_minor' => 10000,
            'available_minor' => 0,
            'lifetime_earnings_minor' => 10000,
            'minimum_payout_minor' => 1000,
        ]);

        $balance->releaseFromHolding(3000);
        $balance->refresh();

        expect($balance->holding_minor)->toBe(7000)
            ->and($balance->available_minor)->toBe(3000);
    });

    it('releases only available holding amount when requested more', function (): void {
        $balance = AffiliateBalance::create([
            'affiliate_id' => $this->affiliate->id,
            'currency' => 'USD',
            'holding_minor' => 2000,
            'available_minor' => 0,
            'lifetime_earnings_minor' => 2000,
            'minimum_payout_minor' => 1000,
        ]);

        $balance->releaseFromHolding(5000);
        $balance->refresh();

        expect($balance->holding_minor)->toBe(0)
            ->and($balance->available_minor)->toBe(2000);
    });

    it('deducts from available balance', function (): void {
        $balance = AffiliateBalance::create([
            'affiliate_id' => $this->affiliate->id,
            'currency' => 'USD',
            'holding_minor' => 0,
            'available_minor' => 10000,
            'lifetime_earnings_minor' => 10000,
            'minimum_payout_minor' => 1000,
        ]);

        $balance->deductFromAvailable(3000);
        $balance->refresh();

        expect($balance->available_minor)->toBe(7000);
    });

    it('deducts only available amount when requested more', function (): void {
        $balance = AffiliateBalance::create([
            'affiliate_id' => $this->affiliate->id,
            'currency' => 'USD',
            'holding_minor' => 0,
            'available_minor' => 2000,
            'lifetime_earnings_minor' => 2000,
            'minimum_payout_minor' => 1000,
        ]);

        $balance->deductFromAvailable(5000);
        $balance->refresh();

        expect($balance->available_minor)->toBe(0);
    });

    it('formats holding amount correctly', function (): void {
        $balance = AffiliateBalance::create([
            'affiliate_id' => $this->affiliate->id,
            'currency' => 'USD',
            'holding_minor' => 12345,
            'available_minor' => 0,
            'lifetime_earnings_minor' => 12345,
            'minimum_payout_minor' => 1000,
        ]);

        expect($balance->formatHolding())->toBe('123.45');
    });

    it('formats available amount correctly', function (): void {
        $balance = AffiliateBalance::create([
            'affiliate_id' => $this->affiliate->id,
            'currency' => 'USD',
            'holding_minor' => 0,
            'available_minor' => 98765,
            'lifetime_earnings_minor' => 98765,
            'minimum_payout_minor' => 1000,
        ]);

        expect($balance->formatAvailable())->toBe('987.65');
    });

    it('formats lifetime earnings correctly', function (): void {
        $balance = AffiliateBalance::create([
            'affiliate_id' => $this->affiliate->id,
            'currency' => 'USD',
            'holding_minor' => 0,
            'available_minor' => 0,
            'lifetime_earnings_minor' => 1000000,
            'minimum_payout_minor' => 1000,
        ]);

        expect($balance->formatLifetimeEarnings())->toBe('10,000.00');
    });

    it('casts monetary fields as integers', function (): void {
        $balance = AffiliateBalance::create([
            'affiliate_id' => $this->affiliate->id,
            'currency' => 'USD',
            'holding_minor' => '5000',
            'available_minor' => '10000',
            'lifetime_earnings_minor' => '15000',
            'minimum_payout_minor' => '1000',
        ]);

        expect($balance->holding_minor)->toBeInt()
            ->and($balance->available_minor)->toBeInt()
            ->and($balance->lifetime_earnings_minor)->toBeInt()
            ->and($balance->minimum_payout_minor)->toBeInt();
    });

    it('uses correct table name from config', function (): void {
        $balance = new AffiliateBalance;

        expect($balance->getTable())->toBe('affiliate_balances');
    });
});
