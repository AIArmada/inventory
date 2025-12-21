<?php

declare(strict_types=1);

use AIArmada\Affiliates\Actions\Conversions\MatureConversion;
use AIArmada\Affiliates\Actions\Conversions\ProcessConversionMaturity;
use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\ConversionStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateTrainingModule;
use AIArmada\Affiliates\Services\Commissions\CommissionCalculationResult;
use AIArmada\Affiliates\Services\Commissions\CommissionRuleEngine;
use AIArmada\Affiliates\Services\PerformanceBonusService;

// MatureConversion Action Tests
test('MatureConversion can be instantiated', function (): void {
    $action = app(MatureConversion::class);

    expect($action)->toBeInstanceOf(MatureConversion::class);
});

test('MatureConversion returns false for non-qualified conversion', function (): void {
    $action = app(MatureConversion::class);

    $affiliate = Affiliate::create([
        'code' => 'MATURE001',
        'name' => 'Mature Test',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $conversion = AffiliateConversion::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'order_reference' => 'ORD-MATURE-001',
        'total_minor' => 50000,
        'commission_minor' => 5000,
        'commission_currency' => 'USD',
        'status' => ConversionStatus::Pending,
        'occurred_at' => now()->subDays(60),
    ]);

    $result = $action->handle($conversion);

    expect($result)->toBeFalse();
});

test('MatureConversion returns false for conversion with future maturity date', function (): void {
    $action = app(MatureConversion::class);

    $affiliate = Affiliate::create([
        'code' => 'MATURE002',
        'name' => 'Future Mature Test',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $conversion = AffiliateConversion::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'order_reference' => 'ORD-MATURE-002',
        'total_minor' => 50000,
        'commission_minor' => 5000,
        'commission_currency' => 'USD',
        'status' => 'qualified',
        'occurred_at' => now(), // Just occurred, not mature yet
    ]);

    $result = $action->handle($conversion);

    expect($result)->toBeFalse();
});

// ProcessConversionMaturity Action Tests
test('ProcessConversionMaturity can be instantiated', function (): void {
    $action = app(ProcessConversionMaturity::class);

    expect($action)->toBeInstanceOf(ProcessConversionMaturity::class);
});

test('ProcessConversionMaturity processes qualified conversions', function (): void {
    $action = app(ProcessConversionMaturity::class);

    $result = $action->handle();

    expect($result)->toBeInt();
    expect($result)->toBeGreaterThanOrEqual(0);
});

// AffiliateTrainingModule Tests
test('AffiliateTrainingModule can be created with all fields', function (): void {
    $module = AffiliateTrainingModule::create([
        'title' => 'Getting Started with Affiliate Marketing',
        'description' => 'Learn the basics',
        'content' => 'This is the full content of the training module.',
        'type' => 'video',
        'video_url' => 'https://youtube.com/watch?v=abc123',
        'resources' => ['doc1.pdf', 'doc2.pdf'],
        'quiz' => [
            ['question' => 'What is affiliate marketing?', 'options' => ['A', 'B', 'C'], 'answer' => 'A'],
        ],
        'passing_score' => 70,
        'duration_minutes' => 30,
        'sort_order' => 1,
        'is_required' => true,
        'is_active' => true,
    ]);

    expect($module)->toBeInstanceOf(AffiliateTrainingModule::class);
    expect($module->title)->toBe('Getting Started with Affiliate Marketing');
    expect($module->is_required)->toBeTrue();
    expect($module->quiz)->toBeArray();
});

test('AffiliateTrainingModule has progress relationship', function (): void {
    $module = new AffiliateTrainingModule;

    expect($module->progress())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\HasMany::class);
});

test('AffiliateTrainingModule casts are correct', function (): void {
    $module = new AffiliateTrainingModule([
        'resources' => ['file1.pdf'],
        'quiz' => [['question' => 'Q1']],
        'passing_score' => 80,
        'duration_minutes' => 45,
        'sort_order' => 2,
        'is_required' => true,
        'is_active' => false,
    ]);

    expect($module->resources)->toBeArray();
    expect($module->quiz)->toBeArray();
    expect($module->passing_score)->toBeInt();
    expect($module->is_required)->toBeBool();
    expect($module->is_active)->toBeBool();
});

// CommissionCalculationResult Tests
test('CommissionCalculationResult can be created', function (): void {
    $result = new CommissionCalculationResult(
        baseCommissionMinor: 1000,
        volumeBonusMinor: 100,
        promotionBonusMinor: 50,
        finalCommissionMinor: 1150,
        appliedRules: ['rule-1', 'rule-2'],
        metadata: ['order_amount' => 10000]
    );

    expect($result)->toBeInstanceOf(CommissionCalculationResult::class);
    expect($result->baseCommissionMinor)->toBe(1000);
    expect($result->finalCommissionMinor)->toBe(1150);
});

test('CommissionCalculationResult getTotalBonusMinor calculates correctly', function (): void {
    $result = new CommissionCalculationResult(
        baseCommissionMinor: 1000,
        volumeBonusMinor: 100,
        promotionBonusMinor: 50,
        finalCommissionMinor: 1150
    );

    expect($result->getTotalBonusMinor())->toBe(150);
});

test('CommissionCalculationResult hasBonus returns true when has bonus', function (): void {
    $result = new CommissionCalculationResult(
        baseCommissionMinor: 1000,
        volumeBonusMinor: 100,
        promotionBonusMinor: 0,
        finalCommissionMinor: 1100
    );

    expect($result->hasBonus())->toBeTrue();
});

test('CommissionCalculationResult hasBonus returns false when no bonus', function (): void {
    $result = new CommissionCalculationResult(
        baseCommissionMinor: 1000,
        volumeBonusMinor: 0,
        promotionBonusMinor: 0,
        finalCommissionMinor: 1000
    );

    expect($result->hasBonus())->toBeFalse();
});

test('CommissionCalculationResult toArray returns correct structure', function (): void {
    $result = new CommissionCalculationResult(
        baseCommissionMinor: 1000,
        volumeBonusMinor: 100,
        promotionBonusMinor: 50,
        finalCommissionMinor: 1150,
        appliedRules: ['rule-1'],
        metadata: ['key' => 'value']
    );

    $array = $result->toArray();

    expect($array)->toBeArray();
    expect($array)->toHaveKey('base_commission_minor');
    expect($array)->toHaveKey('volume_bonus_minor');
    expect($array)->toHaveKey('promotion_bonus_minor');
    expect($array)->toHaveKey('final_commission_minor');
    expect($array)->toHaveKey('applied_rules');
    expect($array)->toHaveKey('metadata');
    expect($array['base_commission_minor'])->toBe(1000);
});

// CommissionRuleEngine Tests
test('CommissionRuleEngine can be instantiated', function (): void {
    $engine = app(CommissionRuleEngine::class);

    expect($engine)->toBeInstanceOf(CommissionRuleEngine::class);
});

test('CommissionRuleEngine calculate returns CommissionCalculationResult', function (): void {
    $engine = app(CommissionRuleEngine::class);

    $affiliate = Affiliate::create([
        'code' => 'ENGINE001',
        'name' => 'Engine Test',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $result = $engine->calculate($affiliate, 10000);

    expect($result)->toBeInstanceOf(CommissionCalculationResult::class);
    expect($result->baseCommissionMinor)->toBeGreaterThanOrEqual(0);
});

test('CommissionRuleEngine getApplicableRules returns collection', function (): void {
    $engine = app(CommissionRuleEngine::class);

    $affiliate = Affiliate::create([
        'code' => 'ENGINE002',
        'name' => 'Rules Test',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $rules = $engine->getApplicableRules($affiliate, []);

    expect($rules)->toBeInstanceOf(Illuminate\Support\Collection::class);
});

test('CommissionRuleEngine clearCache works', function (): void {
    $engine = app(CommissionRuleEngine::class);

    // Call clearCache and verify no errors
    $engine->clearCache();

    expect(true)->toBeTrue(); // Just verify no exception was thrown
});

// PerformanceBonusService Tests
test('PerformanceBonusService can be instantiated', function (): void {
    $service = app(PerformanceBonusService::class);

    expect($service)->toBeInstanceOf(PerformanceBonusService::class);
});

test('PerformanceBonusService awardBonuses processes empty array', function (): void {
    $service = app(PerformanceBonusService::class);

    $result = $service->awardBonuses([]);

    expect($result)->toBe(0);
});

test('PerformanceBonusService getLeaderboard returns collection', function (): void {
    $service = app(PerformanceBonusService::class);

    $leaderboard = $service->getLeaderboard();

    expect($leaderboard)->toBeInstanceOf(Illuminate\Support\Collection::class);
});

test('PerformanceBonusService getLeaderboard with custom limit', function (): void {
    $service = app(PerformanceBonusService::class);

    $leaderboard = $service->getLeaderboard(limit: 5);

    expect($leaderboard)->toBeInstanceOf(Illuminate\Support\Collection::class);
});
