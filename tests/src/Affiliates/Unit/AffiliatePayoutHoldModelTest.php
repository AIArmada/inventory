<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliatePayoutHold;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

describe('AffiliatePayoutHold Model', function (): void {
    it('can be created with required fields', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'HOLD-TEST-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $hold = AffiliatePayoutHold::create([
            'affiliate_id' => $affiliate->id,
            'reason' => 'fraud_investigation',
            'notes' => 'Under review for suspicious activity',
            'placed_by' => 'admin@example.com',
        ]);

        expect($hold)->toBeInstanceOf(AffiliatePayoutHold::class)
            ->and($hold->reason)->toBe('fraud_investigation')
            ->and($hold->notes)->toBe('Under review for suspicious activity')
            ->and($hold->placed_by)->toBe('admin@example.com');
    });

    it('belongs to an affiliate', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'HOLD-AFF-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $hold = AffiliatePayoutHold::create([
            'affiliate_id' => $affiliate->id,
            'reason' => 'verification_needed',
        ]);

        expect($hold->affiliate())->toBeInstanceOf(BelongsTo::class)
            ->and($hold->affiliate->id)->toBe($affiliate->id);
    });

    it('is active when not released and not expired', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'HOLD-ACTIVE-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $hold = AffiliatePayoutHold::create([
            'affiliate_id' => $affiliate->id,
            'reason' => 'active_hold',
            'expires_at' => now()->addDays(30),
        ]);

        expect($hold->isActive())->toBeTrue();
    });

    it('is not active when released', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'HOLD-REL-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $hold = AffiliatePayoutHold::create([
            'affiliate_id' => $affiliate->id,
            'reason' => 'released_hold',
            'released_at' => now(),
        ]);

        expect($hold->isActive())->toBeFalse();
    });

    it('is not active when expired', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'HOLD-EXP-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $hold = AffiliatePayoutHold::create([
            'affiliate_id' => $affiliate->id,
            'reason' => 'expired_hold',
            'expires_at' => now()->subDays(1),
        ]);

        expect($hold->isActive())->toBeFalse();
    });

    it('is active when no expiry set', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'HOLD-NOEXP-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $hold = AffiliatePayoutHold::create([
            'affiliate_id' => $affiliate->id,
            'reason' => 'indefinite_hold',
            'expires_at' => null,
        ]);

        expect($hold->isActive())->toBeTrue();
    });

    it('can release hold', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'HOLD-RELEASE-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $hold = AffiliatePayoutHold::create([
            'affiliate_id' => $affiliate->id,
            'reason' => 'to_be_released',
        ]);

        expect($hold->isActive())->toBeTrue();

        $hold->release();

        $hold->refresh();
        expect($hold->isActive())->toBeFalse()
            ->and($hold->released_at)->not->toBeNull();
    });

    it('casts dates correctly', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'HOLD-DATE-' . uniqid(),
            'name' => 'Test Affiliate',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $hold = AffiliatePayoutHold::create([
            'affiliate_id' => $affiliate->id,
            'reason' => 'date_test',
            'expires_at' => '2024-12-31 23:59:59',
        ]);

        expect($hold->expires_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class)
            ->and($hold->expires_at->format('Y-m-d'))->toBe('2024-12-31');
    });

    it('uses correct table name from config', function (): void {
        $hold = new AffiliatePayoutHold;
        expect($hold->getTable())->toBe('affiliate_payout_holds');
    });
});
