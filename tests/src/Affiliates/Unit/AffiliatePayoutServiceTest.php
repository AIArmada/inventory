<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\PayoutStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Services\AffiliatePayoutService;
use AIArmada\Affiliates\Services\AffiliateService;
use AIArmada\Cart\Facades\Cart;

beforeEach(function (): void {
    $this->affiliate = Affiliate::create([
        'code' => 'PAYOUT-1',
        'name' => 'Payout Partner',
        'status' => 'active',
        'commission_type' => 'percentage',
        'commission_rate' => 100,
        'currency' => 'USD',
    ]);

    Cart::attachAffiliate($this->affiliate->code);
});

test('payout service batches conversions into a payout', function (): void {
    app(AffiliateService::class)->recordConversion(app('cart')->getCurrentCart(), [
        'order_reference' => 'SO-1',
        'subtotal' => 1000,
    ]);

    app(AffiliateService::class)->recordConversion(app('cart')->getCurrentCart(), [
        'order_reference' => 'SO-2',
        'subtotal' => 2000,
    ]);

    $conversions = AffiliateConversion::all();

    $payout = app(AffiliatePayoutService::class)->createPayout($conversions->pluck('id')->all(), [
        'status' => PayoutStatus::Pending,
    ]);

    expect($payout->status)->toBe(PayoutStatus::Pending)
        ->and($payout->conversion_count)->toBe(2)
        ->and($payout->total_minor)->toBe(30);

    $conversions->each(fn (AffiliateConversion $conversion): mixed => expect($conversion->refresh()->affiliate_payout_id)->toBe($payout->getKey()));
});

test('multi level payouts create upline conversions', function (): void {
    config(['affiliates.payouts.multi_level.enabled' => true, 'affiliates.payouts.multi_level.levels' => [0.5]]);

    $parent = Affiliate::create([
        'code' => 'UPLINE',
        'name' => 'Parent',
        'status' => 'active',
        'commission_type' => 'percentage',
        'commission_rate' => 100,
        'currency' => 'USD',
    ]);

    $child = Affiliate::create([
        'code' => 'DOWNLINE',
        'name' => 'Child',
        'status' => 'active',
        'commission_type' => 'percentage',
        'commission_rate' => 100,
        'currency' => 'USD',
        'parent_affiliate_id' => $parent->getKey(),
    ]);

    Cart::attachAffiliate($child->code);

    app(AffiliateService::class)->recordConversion(app('cart')->getCurrentCart(), [
        'order_reference' => 'SO-MLM',
        'subtotal' => 1000,
    ]);

    $upline = AffiliateConversion::where('affiliate_id', $parent->getKey())->first();

    expect($upline)->not()->toBeNull()
        ->and($upline->commission_minor)->toBe(5);
});

test('AffiliatePayoutService updates payout status to completed sets paid_at', function (): void {
    $payout = AffiliatePayout::create([
        'reference' => 'PAY123',
        'status' => PayoutStatus::Pending,
        'total_minor' => 1000,
        'conversion_count' => 1,
        'currency' => 'USD',
    ]);

    $service = app(AffiliatePayoutService::class);
    $updated = $service->updateStatus($payout, PayoutStatus::Completed->value);

    expect($updated->status)->toBe(PayoutStatus::Completed);
    expect($updated->paid_at)->not->toBeNull();
});
