<?php

declare(strict_types=1);

use AIArmada\Affiliates\Data\AffiliateAttributionData;
use AIArmada\Affiliates\Data\AffiliateData;
use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use Carbon\Carbon;

describe('AffiliateAttributionData', function (): void {
    describe('construction', function (): void {
        test('creates instance with required fields only', function (): void {
            $data = new AffiliateAttributionData(
                id: 'attr-123',
                affiliateId: 'aff-456',
                affiliateCode: 'CODE123',
            );

            expect($data->id)->toBe('attr-123');
            expect($data->affiliateId)->toBe('aff-456');
            expect($data->affiliateCode)->toBe('CODE123');
            expect($data->cartInstance)->toBe('default');
        });

        test('creates instance with all fields', function (): void {
            $expiresAt = Carbon::now()->addDays(30);
            $metadata = ['key' => 'value'];

            $data = new AffiliateAttributionData(
                id: 'attr-123',
                affiliateId: 'aff-456',
                affiliateCode: 'CODE123',
                cartIdentifier: 'cart-789',
                cartInstance: 'wishlist',
                cookieValue: 'cookie-abc',
                voucherCode: 'VOUCHER50',
                source: 'google',
                medium: 'cpc',
                campaign: 'summer_sale',
                expiresAt: $expiresAt,
                metadata: $metadata,
            );

            expect($data->cartIdentifier)->toBe('cart-789');
            expect($data->cartInstance)->toBe('wishlist');
            expect($data->cookieValue)->toBe('cookie-abc');
            expect($data->voucherCode)->toBe('VOUCHER50');
            expect($data->source)->toBe('google');
            expect($data->medium)->toBe('cpc');
            expect($data->campaign)->toBe('summer_sale');
            expect($data->expiresAt)->toBe($expiresAt);
            expect($data->metadata)->toBe($metadata);
        });
    });

    describe('fromModel', function (): void {
        test('creates data from attribution model', function (): void {
            $affiliate = Affiliate::create([
                'code' => 'MODEL-' . uniqid(),
                'name' => 'Test Affiliate',
                'contact_email' => 'test@example.com',
                'status' => AffiliateStatus::Active,
                'commission_type' => CommissionType::Percentage,
                'commission_rate' => 1000,
                'currency' => 'USD',
            ]);

            $attribution = AffiliateAttribution::create([
                'affiliate_id' => $affiliate->id,
                'affiliate_code' => $affiliate->code,
                'cart_identifier' => 'cart-test',
                'cart_instance' => 'default',
                'source' => 'facebook',
                'medium' => 'social',
            ]);

            $data = AffiliateAttributionData::fromModel($attribution);

            expect($data)->toBeInstanceOf(AffiliateAttributionData::class);
            expect($data->id)->toBe($attribution->id);
            expect($data->affiliateId)->toBe($affiliate->id);
            expect($data->affiliateCode)->toBe($affiliate->code);
            expect($data->source)->toBe('facebook');
            expect($data->medium)->toBe('social');
        });
    });

    describe('isExpired', function (): void {
        test('returns false when expiresAt is null', function (): void {
            $data = new AffiliateAttributionData(
                id: 'attr-123',
                affiliateId: 'aff-456',
                affiliateCode: 'CODE123',
                expiresAt: null,
            );

            expect($data->isExpired())->toBeFalse();
        });

        test('returns true when expiresAt is in the past', function (): void {
            $data = new AffiliateAttributionData(
                id: 'attr-123',
                affiliateId: 'aff-456',
                affiliateCode: 'CODE123',
                expiresAt: Carbon::now()->subDay(),
            );

            expect($data->isExpired())->toBeTrue();
        });

        test('returns false when expiresAt is in the future', function (): void {
            $data = new AffiliateAttributionData(
                id: 'attr-123',
                affiliateId: 'aff-456',
                affiliateCode: 'CODE123',
                expiresAt: Carbon::now()->addDay(),
            );

            expect($data->isExpired())->toBeFalse();
        });
    });

    describe('hasUtmParameters', function (): void {
        test('returns false when no UTM parameters set', function (): void {
            $data = new AffiliateAttributionData(
                id: 'attr-123',
                affiliateId: 'aff-456',
                affiliateCode: 'CODE123',
            );

            expect($data->hasUtmParameters())->toBeFalse();
        });

        test('returns true when source is set', function (): void {
            $data = new AffiliateAttributionData(
                id: 'attr-123',
                affiliateId: 'aff-456',
                affiliateCode: 'CODE123',
                source: 'google',
            );

            expect($data->hasUtmParameters())->toBeTrue();
        });

        test('returns true when medium is set', function (): void {
            $data = new AffiliateAttributionData(
                id: 'attr-123',
                affiliateId: 'aff-456',
                affiliateCode: 'CODE123',
                medium: 'cpc',
            );

            expect($data->hasUtmParameters())->toBeTrue();
        });

        test('returns true when campaign is set', function (): void {
            $data = new AffiliateAttributionData(
                id: 'attr-123',
                affiliateId: 'aff-456',
                affiliateCode: 'CODE123',
                campaign: 'summer_sale',
            );

            expect($data->hasUtmParameters())->toBeTrue();
        });
    });

    describe('getUtmString', function (): void {
        test('returns null when no UTM parameters', function (): void {
            $data = new AffiliateAttributionData(
                id: 'attr-123',
                affiliateId: 'aff-456',
                affiliateCode: 'CODE123',
            );

            expect($data->getUtmString())->toBeNull();
        });

        test('returns formatted string with source only', function (): void {
            $data = new AffiliateAttributionData(
                id: 'attr-123',
                affiliateId: 'aff-456',
                affiliateCode: 'CODE123',
                source: 'google',
            );

            expect($data->getUtmString())->toBe('utm_source=google');
        });

        test('returns formatted string with all parameters', function (): void {
            $data = new AffiliateAttributionData(
                id: 'attr-123',
                affiliateId: 'aff-456',
                affiliateCode: 'CODE123',
                source: 'google',
                medium: 'cpc',
                campaign: 'summer_sale',
            );

            expect($data->getUtmString())->toBe('utm_source=google&utm_medium=cpc&utm_campaign=summer_sale');
        });

        test('excludes null parameters from string', function (): void {
            $data = new AffiliateAttributionData(
                id: 'attr-123',
                affiliateId: 'aff-456',
                affiliateCode: 'CODE123',
                source: 'google',
                campaign: 'summer_sale',
            );

            expect($data->getUtmString())->toBe('utm_source=google&utm_campaign=summer_sale');
        });
    });
});

describe('AffiliateData', function (): void {
    describe('construction', function (): void {
        test('creates instance with required fields', function (): void {
            $data = new AffiliateData(
                id: 'aff-123',
                code: 'CODE456',
                name: 'Test Affiliate',
                status: AffiliateStatus::Active,
                commissionType: CommissionType::Percentage,
                commissionRate: 1000,
                currency: 'USD',
            );

            expect($data->id)->toBe('aff-123');
            expect($data->code)->toBe('CODE456');
            expect($data->name)->toBe('Test Affiliate');
            expect($data->status)->toBe(AffiliateStatus::Active);
            expect($data->commissionType)->toBe(CommissionType::Percentage);
            expect($data->commissionRate)->toBe(1000);
            expect($data->currency)->toBe('USD');
        });

        test('creates instance with optional fields', function (): void {
            $metadata = ['tier' => 'gold'];

            $data = new AffiliateData(
                id: 'aff-123',
                code: 'CODE456',
                name: 'Test Affiliate',
                status: AffiliateStatus::Active,
                commissionType: CommissionType::Percentage,
                commissionRate: 1000,
                currency: 'USD',
                defaultVoucherCode: 'VOUCHER10',
                metadata: $metadata,
            );

            expect($data->defaultVoucherCode)->toBe('VOUCHER10');
            expect($data->metadata)->toBe($metadata);
        });
    });

    describe('fromModel', function (): void {
        test('creates data from affiliate model', function (): void {
            $affiliate = Affiliate::create([
                'code' => 'DATA-' . uniqid(),
                'name' => 'Data Test Affiliate',
                'contact_email' => 'data@example.com',
                'status' => AffiliateStatus::Active,
                'commission_type' => CommissionType::Fixed,
                'commission_rate' => 500,
                'currency' => 'EUR',
                'default_voucher_code' => 'DISCOUNT20',
                'metadata' => ['key' => 'value'],
            ]);

            $data = AffiliateData::fromModel($affiliate);

            expect($data)->toBeInstanceOf(AffiliateData::class);
            expect($data->id)->toBe($affiliate->id);
            expect($data->code)->toBe($affiliate->code);
            expect($data->name)->toBe('Data Test Affiliate');
            expect($data->status)->toBe(AffiliateStatus::Active);
            expect($data->commissionType)->toBe(CommissionType::Fixed);
            expect($data->commissionRate)->toBe(500);
            expect($data->currency)->toBe('EUR');
            expect($data->defaultVoucherCode)->toBe('DISCOUNT20');
        });
    });

    describe('isActive', function (): void {
        test('returns true when status is Active', function (): void {
            $data = new AffiliateData(
                id: 'aff-123',
                code: 'CODE456',
                name: 'Test',
                status: AffiliateStatus::Active,
                commissionType: CommissionType::Percentage,
                commissionRate: 1000,
                currency: 'USD',
            );

            expect($data->isActive())->toBeTrue();
        });

        test('returns false when status is Draft', function (): void {
            $data = new AffiliateData(
                id: 'aff-123',
                code: 'CODE456',
                name: 'Test',
                status: AffiliateStatus::Draft,
                commissionType: CommissionType::Percentage,
                commissionRate: 1000,
                currency: 'USD',
            );

            expect($data->isActive())->toBeFalse();
        });

        test('returns false when status is Pending', function (): void {
            $data = new AffiliateData(
                id: 'aff-123',
                code: 'CODE456',
                name: 'Test',
                status: AffiliateStatus::Pending,
                commissionType: CommissionType::Percentage,
                commissionRate: 1000,
                currency: 'USD',
            );

            expect($data->isActive())->toBeFalse();
        });
    });

    describe('isPercentageCommission', function (): void {
        test('returns true for percentage commission', function (): void {
            $data = new AffiliateData(
                id: 'aff-123',
                code: 'CODE456',
                name: 'Test',
                status: AffiliateStatus::Active,
                commissionType: CommissionType::Percentage,
                commissionRate: 1000,
                currency: 'USD',
            );

            expect($data->isPercentageCommission())->toBeTrue();
        });

        test('returns false for fixed amount commission', function (): void {
            $data = new AffiliateData(
                id: 'aff-123',
                code: 'CODE456',
                name: 'Test',
                status: AffiliateStatus::Active,
                commissionType: CommissionType::Fixed,
                commissionRate: 500,
                currency: 'USD',
            );

            expect($data->isPercentageCommission())->toBeFalse();
        });
    });

    describe('getFormattedCommissionRate', function (): void {
        test('formats percentage commission correctly', function (): void {
            $data = new AffiliateData(
                id: 'aff-123',
                code: 'CODE456',
                name: 'Test',
                status: AffiliateStatus::Active,
                commissionType: CommissionType::Percentage,
                commissionRate: 1000, // 10.00%
                currency: 'USD',
            );

            expect($data->getFormattedCommissionRate())->toBe('10.00%');
        });

        test('formats fixed amount commission correctly', function (): void {
            $data = new AffiliateData(
                id: 'aff-123',
                code: 'CODE456',
                name: 'Test',
                status: AffiliateStatus::Active,
                commissionType: CommissionType::Fixed,
                commissionRate: 500, // 5.00 USD
                currency: 'USD',
            );

            expect($data->getFormattedCommissionRate())->toBe('5.00 USD');
        });

        test('formats EUR currency correctly', function (): void {
            $data = new AffiliateData(
                id: 'aff-123',
                code: 'CODE456',
                name: 'Test',
                status: AffiliateStatus::Active,
                commissionType: CommissionType::Fixed,
                commissionRate: 1234,
                currency: 'EUR',
            );

            expect($data->getFormattedCommissionRate())->toBe('12.34 EUR');
        });
    });
});

describe('Data classes structure', function (): void {
    test('AffiliateAttributionData extends Data', function (): void {
        expect(is_subclass_of(AffiliateAttributionData::class, Spatie\LaravelData\Data::class))->toBeTrue();
    });

    test('AffiliateData extends Data', function (): void {
        expect(is_subclass_of(AffiliateData::class, Spatie\LaravelData\Data::class))->toBeTrue();
    });

    test('AffiliateAttributionData uses SnakeCaseMapper', function (): void {
        $reflection = new ReflectionClass(AffiliateAttributionData::class);
        $attributes = $reflection->getAttributes();

        $mapperAttributes = array_filter($attributes, fn ($attr) => str_contains($attr->getName(), 'MapInputName'));
        expect(count($mapperAttributes))->toBeGreaterThan(0);
    });

    test('AffiliateData uses SnakeCaseMapper', function (): void {
        $reflection = new ReflectionClass(AffiliateData::class);
        $attributes = $reflection->getAttributes();

        $mapperAttributes = array_filter($attributes, fn ($attr) => str_contains($attr->getName(), 'MapInputName'));
        expect(count($mapperAttributes))->toBeGreaterThan(0);
    });
});
