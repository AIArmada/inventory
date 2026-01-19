<?php

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\FraudSeverity;
use AIArmada\Affiliates\Enums\FraudSignalStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateFraudSignal;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentAffiliates\Resources\AffiliateFraudSignalResource;
use AIArmada\FilamentPricing\Resources\PromotionResource;
use AIArmada\Promotions\Enums\PromotionType;
use AIArmada\Promotions\Models\Promotion;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->owner = User::factory()->create([
        'name' => 'Sarah Chen',
        'email' => 'admin@commerce.demo',
    ]);
});

test('pricing and affiliates navigation badges work for the single tenant', function () {
    config()->set('promotions.features.owner.enabled', true);
    config()->set('promotions.features.owner.include_global', false);

    config()->set('affiliates.owner.enabled', true);
    config()->set('affiliates.owner.include_global', false);

    OwnerContext::withOwner($this->owner, function (): void {
        Promotion::create([
            'name' => 'Promo 1',
            'type' => PromotionType::Percentage,
            'discount_value' => 10,
            'is_active' => true,
        ]);

        Promotion::create([
            'name' => 'Promo 2',
            'type' => PromotionType::Fixed,
            'discount_value' => 500,
            'is_active' => true,
        ]);

        $affiliate = Affiliate::create([
            'code' => 'AFF1',
            'name' => 'Affiliate 1',
            'status' => AffiliateStatus::Active,
            'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000,
            'currency' => 'MYR',
        ]);

        AffiliateFraudSignal::create([
            'affiliate_id' => $affiliate->id,
            'rule_code' => 'velocity',
            'risk_points' => 80,
            'severity' => FraudSeverity::High,
            'description' => 'High velocity detected',
            'status' => FraudSignalStatus::Detected,
            'detected_at' => now(),
        ]);

        AffiliateFraudSignal::create([
            'affiliate_id' => $affiliate->id,
            'rule_code' => 'geo_anomaly',
            'risk_points' => 70,
            'severity' => FraudSeverity::Medium,
            'description' => 'Geo anomaly',
            'status' => FraudSignalStatus::Detected,
            'detected_at' => now(),
        ]);
    });

    OwnerContext::withOwner($this->owner, function (): void {
        expect(PromotionResource::getNavigationBadge())->toBe('2');
        expect(AffiliateFraudSignalResource::getNavigationBadge())->toBe('2');
    });
});
