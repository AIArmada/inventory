<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\CommissionRuleType;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\PayoutMethodType;
use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateCommissionRule;
use AIArmada\Affiliates\Models\AffiliateDailyStat;
use AIArmada\Affiliates\Models\AffiliatePayoutHold;
use AIArmada\Affiliates\Models\AffiliatePayoutMethod;
use AIArmada\Affiliates\Models\AffiliateProgram;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// AffiliateCommissionRule Tests
test('AffiliateCommissionRule can be created with required fields', function (): void {
    $program = AffiliateProgram::create([
        'name' => 'Commission Rule Program',
        'slug' => 'commission-rule-program',
        'status' => ProgramStatus::Active,
        'commission_type' => 'percentage',
        'default_commission_rate_basis_points' => 1000,
        'cookie_lifetime_days' => 30,
    ]);

    $rule = AffiliateCommissionRule::create([
        'program_id' => $program->id,
        'name' => 'Product Bonus',
        'rule_type' => CommissionRuleType::Product,
        'priority' => 100,
        'conditions' => ['product_id' => 'PROD_123'],
        'commission_type' => CommissionType::Percentage,
        'commission_value' => 1500,
        'is_active' => true,
    ]);

    expect($rule)->toBeInstanceOf(AffiliateCommissionRule::class);
    expect($rule->name)->toBe('Product Bonus');
    expect($rule->priority)->toBe(100);
});

test('AffiliateCommissionRule has program relationship', function (): void {
    $rule = new AffiliateCommissionRule;

    expect($rule->program())->toBeInstanceOf(BelongsTo::class);
});

test('AffiliateCommissionRule isActive returns true when all conditions met', function (): void {
    $rule = new AffiliateCommissionRule([
        'is_active' => true,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
    ]);

    expect($rule->isActive())->toBeTrue();
});

test('AffiliateCommissionRule isActive returns false when is_active is false', function (): void {
    $rule = new AffiliateCommissionRule([
        'is_active' => false,
    ]);

    expect($rule->isActive())->toBeFalse();
});

test('AffiliateCommissionRule isActive returns false when starts_at is in future', function (): void {
    $rule = new AffiliateCommissionRule([
        'is_active' => true,
        'starts_at' => now()->addDay(),
    ]);

    expect($rule->isActive())->toBeFalse();
});

test('AffiliateCommissionRule isActive returns false when ends_at is in past', function (): void {
    $rule = new AffiliateCommissionRule([
        'is_active' => true,
        'ends_at' => now()->subDay(),
    ]);

    expect($rule->isActive())->toBeFalse();
});

test('AffiliateCommissionRule matches returns true when conditions are met', function (): void {
    $rule = new AffiliateCommissionRule([
        'is_active' => true,
        'conditions' => ['product_id' => 'PROD_123'],
    ]);

    expect($rule->matches(['product_id' => 'PROD_123']))->toBeTrue();
});

test('AffiliateCommissionRule matches returns false when conditions not met', function (): void {
    $rule = new AffiliateCommissionRule([
        'is_active' => true,
        'conditions' => ['product_id' => 'PROD_123'],
    ]);

    expect($rule->matches(['product_id' => 'PROD_456']))->toBeFalse();
});

test('AffiliateCommissionRule matches handles in condition', function (): void {
    $rule = new AffiliateCommissionRule([
        'is_active' => true,
        'conditions' => ['category' => ['in' => ['electronics', 'computers']]],
    ]);

    expect($rule->matches(['category' => 'electronics']))->toBeTrue();
    expect($rule->matches(['category' => 'clothing']))->toBeFalse();
});

test('AffiliateCommissionRule matches handles not_in condition', function (): void {
    $rule = new AffiliateCommissionRule([
        'is_active' => true,
        'conditions' => ['country' => ['not_in' => ['US', 'CA']]],
    ]);

    expect($rule->matches(['country' => 'UK']))->toBeTrue();
    expect($rule->matches(['country' => 'US']))->toBeFalse();
});

test('AffiliateCommissionRule matches handles min condition', function (): void {
    $rule = new AffiliateCommissionRule([
        'is_active' => true,
        'conditions' => ['amount' => ['min' => 10000]],
    ]);

    expect($rule->matches(['amount' => 15000]))->toBeTrue();
    expect($rule->matches(['amount' => 5000]))->toBeFalse();
});

test('AffiliateCommissionRule matches handles max condition', function (): void {
    $rule = new AffiliateCommissionRule([
        'is_active' => true,
        'conditions' => ['quantity' => ['max' => 10]],
    ]);

    expect($rule->matches(['quantity' => 5]))->toBeTrue();
    expect($rule->matches(['quantity' => 15]))->toBeFalse();
});

test('AffiliateCommissionRule matches handles equals condition', function (): void {
    $rule = new AffiliateCommissionRule([
        'is_active' => true,
        'conditions' => ['tier' => ['equals' => 'gold']],
    ]);

    expect($rule->matches(['tier' => 'gold']))->toBeTrue();
    expect($rule->matches(['tier' => 'silver']))->toBeFalse();
});

test('AffiliateCommissionRule calculateCommission for percentage type', function (): void {
    $rule = new AffiliateCommissionRule([
        'commission_type' => CommissionType::Percentage,
        'commission_value' => 1000, // 10% in basis points
    ]);

    // 10000 cents * 10% = 1000 cents
    expect($rule->calculateCommission(10000))->toBe(1000);
});

test('AffiliateCommissionRule calculateCommission for fixed type', function (): void {
    $rule = new AffiliateCommissionRule([
        'commission_type' => CommissionType::Fixed,
        'commission_value' => 500, // 500 cents fixed
    ]);

    expect($rule->calculateCommission(10000))->toBe(500);
});

test('AffiliateCommissionRule scopeActive filters correctly', function (): void {
    $program = AffiliateProgram::create([
        'name' => 'Active Rule Test',
        'slug' => 'active-rule-test',
        'status' => ProgramStatus::Active,
        'commission_type' => 'percentage',
        'default_commission_rate_basis_points' => 1000,
        'cookie_lifetime_days' => 30,
    ]);

    AffiliateCommissionRule::create([
        'program_id' => $program->id,
        'name' => 'Active Rule',
        'rule_type' => CommissionRuleType::Product,
        'priority' => 100,
        'conditions' => [],
        'commission_type' => CommissionType::Percentage,
        'commission_value' => 1000,
        'is_active' => true,
    ]);

    AffiliateCommissionRule::create([
        'program_id' => $program->id,
        'name' => 'Inactive Rule',
        'rule_type' => CommissionRuleType::Product,
        'priority' => 50,
        'conditions' => [],
        'commission_type' => CommissionType::Percentage,
        'commission_value' => 500,
        'is_active' => false,
    ]);

    $activeRules = AffiliateCommissionRule::active()->pluck('name');

    expect($activeRules)->toContain('Active Rule');
    expect($activeRules)->not->toContain('Inactive Rule');
});

test('AffiliateCommissionRule scopeOrdered orders by priority descending', function (): void {
    $program = AffiliateProgram::create([
        'name' => 'Ordered Rule Test',
        'slug' => 'ordered-rule-test',
        'status' => ProgramStatus::Active,
        'commission_type' => 'percentage',
        'default_commission_rate_basis_points' => 1000,
        'cookie_lifetime_days' => 30,
    ]);

    AffiliateCommissionRule::create([
        'program_id' => $program->id,
        'name' => 'Low Priority',
        'rule_type' => CommissionRuleType::Product,
        'priority' => 10,
        'conditions' => [],
        'commission_type' => CommissionType::Percentage,
        'commission_value' => 500,
        'is_active' => true,
    ]);

    AffiliateCommissionRule::create([
        'program_id' => $program->id,
        'name' => 'High Priority',
        'rule_type' => CommissionRuleType::Promotion,
        'priority' => 100,
        'conditions' => [],
        'commission_type' => CommissionType::Percentage,
        'commission_value' => 2000,
        'is_active' => true,
    ]);

    $orderedRules = AffiliateCommissionRule::ordered()->pluck('name');

    expect($orderedRules->first())->toBe('High Priority');
    expect($orderedRules->last())->toBe('Low Priority');
});

// AffiliateDailyStat Tests
test('AffiliateDailyStat can be created with required fields', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'STAT001',
        'name' => 'Stats Test Affiliate',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $stat = AffiliateDailyStat::create([
        'affiliate_id' => $affiliate->id,
        'date' => now()->toDateString(),
        'clicks' => 100,
        'unique_clicks' => 80,
        'attributions' => 50,
        'conversions' => 10,
        'revenue_cents' => 50000,
        'commission_cents' => 5000,
        'refunds' => 1,
        'refund_amount_cents' => 2500,
        'conversion_rate' => 10.0,
        'epc_cents' => 50.0,
    ]);

    expect($stat)->toBeInstanceOf(AffiliateDailyStat::class);
    expect($stat->clicks)->toBe(100);
    expect($stat->conversions)->toBe(10);
});

test('AffiliateDailyStat has affiliate relationship', function (): void {
    $stat = new AffiliateDailyStat;

    expect($stat->affiliate())->toBeInstanceOf(BelongsTo::class);
});

test('AffiliateDailyStat revenue_minor alias works', function (): void {
    $stat = new AffiliateDailyStat([
        'revenue_cents' => 50000,
    ]);

    expect($stat->revenue_minor)->toBe(50000);
});

test('AffiliateDailyStat commission_minor alias works', function (): void {
    $stat = new AffiliateDailyStat([
        'commission_cents' => 5000,
    ]);

    expect($stat->commission_minor)->toBe(5000);
});

// AffiliatePayoutMethod Tests
test('AffiliatePayoutMethod can be created with required fields', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'PAY001',
        'name' => 'Payout Method Test',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $method = AffiliatePayoutMethod::create([
        'affiliate_id' => $affiliate->id,
        'type' => PayoutMethodType::PayPal,
        'details' => ['email' => 'affiliate@example.com'],
        'is_verified' => false,
        'is_default' => true,
    ]);

    expect($method)->toBeInstanceOf(AffiliatePayoutMethod::class);
    expect($method->type)->toBe(PayoutMethodType::PayPal);
});

test('AffiliatePayoutMethod has affiliate relationship', function (): void {
    $method = new AffiliatePayoutMethod;

    expect($method->affiliate())->toBeInstanceOf(BelongsTo::class);
});

test('AffiliatePayoutMethod verify sets is_verified and verified_at', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'PAY002',
        'name' => 'Verify Test',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $method = AffiliatePayoutMethod::create([
        'affiliate_id' => $affiliate->id,
        'type' => PayoutMethodType::BankTransfer,
        'details' => ['bank_name' => 'Test Bank', 'account_number' => '1234567890'],
        'is_verified' => false,
        'is_default' => false,
    ]);

    $method->verify();

    expect($method->fresh()->is_verified)->toBeTrue();
    expect($method->fresh()->verified_at)->not->toBeNull();
});

test('AffiliatePayoutMethod setAsDefault updates default flag', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'PAY003',
        'name' => 'Default Test',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $method1 = AffiliatePayoutMethod::create([
        'affiliate_id' => $affiliate->id,
        'type' => PayoutMethodType::PayPal,
        'details' => ['email' => 'old@example.com'],
        'is_verified' => true,
        'is_default' => true,
    ]);

    $method2 = AffiliatePayoutMethod::create([
        'affiliate_id' => $affiliate->id,
        'type' => PayoutMethodType::BankTransfer,
        'details' => ['bank_name' => 'New Bank'],
        'is_verified' => true,
        'is_default' => false,
    ]);

    $method2->setAsDefault();

    expect($method1->fresh()->is_default)->toBeFalse();
    expect($method2->fresh()->is_default)->toBeTrue();
});

test('AffiliatePayoutMethod getMaskedDetails for bank transfer', function (): void {
    $method = new AffiliatePayoutMethod([
        'type' => PayoutMethodType::BankTransfer,
        'details' => ['bank_name' => 'Chase Bank', 'account_number' => '1234567890'],
    ]);

    $masked = $method->getMaskedDetails();

    expect($masked['bank_name'])->toBe('Chase Bank');
    expect($masked['account_last_4'])->toBe('****7890');
});

test('AffiliatePayoutMethod getMaskedDetails for paypal', function (): void {
    $method = new AffiliatePayoutMethod([
        'type' => PayoutMethodType::PayPal,
        'details' => ['email' => 'john.doe@example.com'],
    ]);

    $masked = $method->getMaskedDetails();

    expect($masked['email'])->toBe('jo******@example.com');
});

test('AffiliatePayoutMethod getMaskedDetails for stripe connect', function (): void {
    $method = new AffiliatePayoutMethod([
        'type' => PayoutMethodType::StripeConnect,
        'details' => ['stripe_account_id' => 'acct_1234567890abcdef'],
    ]);

    $masked = $method->getMaskedDetails();

    expect($masked['account_id'])->toBe('acct_123...');
});

test('AffiliatePayoutMethod label attribute for PayPal', function (): void {
    $method = new AffiliatePayoutMethod([
        'type' => PayoutMethodType::PayPal,
        'details' => ['email' => 'affiliate@example.com'],
    ]);

    expect($method->label)->toBe('affiliate@example.com');
});

test('AffiliatePayoutMethod label attribute for bank transfer', function (): void {
    $method = new AffiliatePayoutMethod([
        'type' => PayoutMethodType::BankTransfer,
        'details' => ['bank_name' => 'Wells Fargo'],
    ]);

    expect($method->label)->toBe('Wells Fargo');
});

// AffiliatePayoutHold Tests
test('AffiliatePayoutHold can be created', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'HOLD001',
        'name' => 'Hold Test Affiliate',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $hold = AffiliatePayoutHold::create([
        'affiliate_id' => $affiliate->id,
        'reason' => 'Pending verification',
        'released_at' => null,
    ]);

    expect($hold)->toBeInstanceOf(AffiliatePayoutHold::class);
    expect($hold->reason)->toBe('Pending verification');
});

test('AffiliatePayoutHold has affiliate relationship', function (): void {
    $hold = new AffiliatePayoutHold;

    expect($hold->affiliate())->toBeInstanceOf(BelongsTo::class);
});
