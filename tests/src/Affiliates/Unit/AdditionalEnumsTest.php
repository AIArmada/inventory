<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\CommissionRuleType;
use AIArmada\Affiliates\Enums\FraudSeverity;
use AIArmada\Affiliates\Enums\FraudSignalStatus;
use AIArmada\Affiliates\Enums\MembershipStatus;
use AIArmada\Affiliates\Enums\PayoutMethodType;
use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Enums\RankQualificationReason;
use AIArmada\Affiliates\Enums\RegistrationApprovalMode;

// CommissionRuleType Tests
test('CommissionRuleType enum has all expected cases', function (): void {
    expect(CommissionRuleType::cases())->toHaveCount(8);

    expect(CommissionRuleType::Product->value)->toBe('product');
    expect(CommissionRuleType::Category->value)->toBe('category');
    expect(CommissionRuleType::Affiliate->value)->toBe('affiliate');
    expect(CommissionRuleType::Program->value)->toBe('program');
    expect(CommissionRuleType::Volume->value)->toBe('volume');
    expect(CommissionRuleType::Promotion->value)->toBe('promotion');
    expect(CommissionRuleType::FirstPurchase->value)->toBe('first_purchase');
    expect(CommissionRuleType::Recurring->value)->toBe('recurring');
});

test('CommissionRuleType label returns correct labels', function (): void {
    expect(CommissionRuleType::Product->label())->toBe('Product-specific');
    expect(CommissionRuleType::Category->label())->toBe('Category-based');
    expect(CommissionRuleType::Affiliate->label())->toBe('Affiliate-specific');
    expect(CommissionRuleType::Program->label())->toBe('Program-wide');
    expect(CommissionRuleType::Volume->label())->toBe('Volume-based');
    expect(CommissionRuleType::Promotion->label())->toBe('Promotional');
    expect(CommissionRuleType::FirstPurchase->label())->toBe('First Purchase Bonus');
    expect(CommissionRuleType::Recurring->label())->toBe('Recurring Commission');
});

test('CommissionRuleType priority returns correct priority values', function (): void {
    expect(CommissionRuleType::Promotion->priority())->toBe(100);
    expect(CommissionRuleType::Product->priority())->toBe(90);
    expect(CommissionRuleType::Category->priority())->toBe(80);
    expect(CommissionRuleType::Volume->priority())->toBe(70);
    expect(CommissionRuleType::Affiliate->priority())->toBe(60);
    expect(CommissionRuleType::FirstPurchase->priority())->toBe(50);
    expect(CommissionRuleType::Recurring->priority())->toBe(40);
    expect(CommissionRuleType::Program->priority())->toBe(10);
});

// FraudSignalStatus Tests
test('FraudSignalStatus enum has all expected cases', function (): void {
    expect(FraudSignalStatus::cases())->toHaveCount(4);

    expect(FraudSignalStatus::Detected->value)->toBe('detected');
    expect(FraudSignalStatus::Reviewed->value)->toBe('reviewed');
    expect(FraudSignalStatus::Dismissed->value)->toBe('dismissed');
    expect(FraudSignalStatus::Confirmed->value)->toBe('confirmed');
});

test('FraudSignalStatus label returns correct labels', function (): void {
    expect(FraudSignalStatus::Detected->label())->toBe('Detected');
    expect(FraudSignalStatus::Reviewed->label())->toBe('Reviewed');
    expect(FraudSignalStatus::Dismissed->label())->toBe('Dismissed');
    expect(FraudSignalStatus::Confirmed->label())->toBe('Confirmed');
});

test('FraudSignalStatus color returns correct colors', function (): void {
    expect(FraudSignalStatus::Detected->color())->toBe('warning');
    expect(FraudSignalStatus::Reviewed->color())->toBe('info');
    expect(FraudSignalStatus::Dismissed->color())->toBe('gray');
    expect(FraudSignalStatus::Confirmed->color())->toBe('danger');
});

// MembershipStatus Tests
test('MembershipStatus enum has all expected cases', function (): void {
    expect(MembershipStatus::cases())->toHaveCount(4);

    expect(MembershipStatus::Pending->value)->toBe('pending');
    expect(MembershipStatus::Approved->value)->toBe('approved');
    expect(MembershipStatus::Rejected->value)->toBe('rejected');
    expect(MembershipStatus::Suspended->value)->toBe('suspended');
});

test('MembershipStatus label returns correct labels', function (): void {
    expect(MembershipStatus::Pending->label())->toBe('Pending');
    expect(MembershipStatus::Approved->label())->toBe('Approved');
    expect(MembershipStatus::Rejected->label())->toBe('Rejected');
    expect(MembershipStatus::Suspended->label())->toBe('Suspended');
});

test('MembershipStatus color returns correct colors', function (): void {
    expect(MembershipStatus::Pending->color())->toBe('warning');
    expect(MembershipStatus::Approved->color())->toBe('success');
    expect(MembershipStatus::Rejected->color())->toBe('danger');
    expect(MembershipStatus::Suspended->color())->toBe('gray');
});

// PayoutMethodType Tests
test('PayoutMethodType enum has all expected cases', function (): void {
    expect(PayoutMethodType::cases())->toHaveCount(8);

    expect(PayoutMethodType::BankTransfer->value)->toBe('bank_transfer');
    expect(PayoutMethodType::PayPal->value)->toBe('paypal');
    expect(PayoutMethodType::StripeConnect->value)->toBe('stripe_connect');
    expect(PayoutMethodType::Wise->value)->toBe('wise');
    expect(PayoutMethodType::Payoneer->value)->toBe('payoneer');
    expect(PayoutMethodType::Check->value)->toBe('check');
    expect(PayoutMethodType::Wire->value)->toBe('wire');
    expect(PayoutMethodType::Crypto->value)->toBe('crypto');
});

test('PayoutMethodType label returns correct labels', function (): void {
    expect(PayoutMethodType::BankTransfer->label())->toBe('Bank Transfer');
    expect(PayoutMethodType::PayPal->label())->toBe('PayPal');
    expect(PayoutMethodType::StripeConnect->label())->toBe('Stripe Connect');
    expect(PayoutMethodType::Wise->label())->toBe('Wise');
    expect(PayoutMethodType::Payoneer->label())->toBe('Payoneer');
    expect(PayoutMethodType::Check->label())->toBe('Check');
    expect(PayoutMethodType::Wire->label())->toBe('Wire Transfer');
    expect(PayoutMethodType::Crypto->label())->toBe('Cryptocurrency');
});

test('PayoutMethodType icon returns correct icons', function (): void {
    expect(PayoutMethodType::BankTransfer->icon())->toBe('heroicon-o-building-library');
    expect(PayoutMethodType::PayPal->icon())->toBe('heroicon-o-credit-card');
    expect(PayoutMethodType::StripeConnect->icon())->toBe('heroicon-o-credit-card');
    expect(PayoutMethodType::Wise->icon())->toBe('heroicon-o-globe-alt');
    expect(PayoutMethodType::Payoneer->icon())->toBe('heroicon-o-globe-alt');
    expect(PayoutMethodType::Check->icon())->toBe('heroicon-o-document-text');
    expect(PayoutMethodType::Wire->icon())->toBe('heroicon-o-arrows-right-left');
    expect(PayoutMethodType::Crypto->icon())->toBe('heroicon-o-currency-dollar');
});

// ProgramStatus Tests
test('ProgramStatus enum has all expected cases', function (): void {
    expect(ProgramStatus::cases())->toHaveCount(4);

    expect(ProgramStatus::Draft->value)->toBe('draft');
    expect(ProgramStatus::Active->value)->toBe('active');
    expect(ProgramStatus::Paused->value)->toBe('paused');
    expect(ProgramStatus::Archived->value)->toBe('archived');
});

test('ProgramStatus label returns correct labels', function (): void {
    expect(ProgramStatus::Draft->label())->toBe('Draft');
    expect(ProgramStatus::Active->label())->toBe('Active');
    expect(ProgramStatus::Paused->label())->toBe('Paused');
    expect(ProgramStatus::Archived->label())->toBe('Archived');
});

test('ProgramStatus color returns correct colors', function (): void {
    expect(ProgramStatus::Draft->color())->toBe('gray');
    expect(ProgramStatus::Active->color())->toBe('success');
    expect(ProgramStatus::Paused->color())->toBe('warning');
    expect(ProgramStatus::Archived->color())->toBe('danger');
});

// RankQualificationReason Tests
test('RankQualificationReason enum has all expected cases', function (): void {
    expect(RankQualificationReason::cases())->toHaveCount(4);

    expect(RankQualificationReason::Qualified->value)->toBe('qualified');
    expect(RankQualificationReason::Demoted->value)->toBe('demoted');
    expect(RankQualificationReason::Manual->value)->toBe('manual');
    expect(RankQualificationReason::Initial->value)->toBe('initial');
});

test('RankQualificationReason label returns correct labels', function (): void {
    expect(RankQualificationReason::Qualified->label())->toBe('Qualified');
    expect(RankQualificationReason::Demoted->label())->toBe('Demoted');
    expect(RankQualificationReason::Manual->label())->toBe('Manual Assignment');
    expect(RankQualificationReason::Initial->label())->toBe('Initial Rank');
});

// RegistrationApprovalMode Tests
test('RegistrationApprovalMode enum has all expected cases', function (): void {
    expect(RegistrationApprovalMode::cases())->toHaveCount(3);

    expect(RegistrationApprovalMode::Auto->value)->toBe('auto');
    expect(RegistrationApprovalMode::Open->value)->toBe('open');
    expect(RegistrationApprovalMode::Admin->value)->toBe('admin');
});

test('RegistrationApprovalMode label returns correct labels', function (): void {
    expect(RegistrationApprovalMode::Auto->label())->toBe('Auto Approve');
    expect(RegistrationApprovalMode::Open->label())->toBe('Open Registration');
    expect(RegistrationApprovalMode::Admin->label())->toBe('Admin Approval Required');
});

test('RegistrationApprovalMode description returns correct descriptions', function (): void {
    expect(RegistrationApprovalMode::Auto->description())->toBe('Affiliates are automatically approved and activated upon registration.');
    expect(RegistrationApprovalMode::Open->description())->toBe('Affiliates can register freely but start in pending status.');
    expect(RegistrationApprovalMode::Admin->description())->toBe('Affiliates must be manually approved by an administrator.');
});

test('RegistrationApprovalMode defaultStatus returns correct statuses', function (): void {
    expect(RegistrationApprovalMode::Auto->defaultStatus())->toBe(AffiliateStatus::Active);
    expect(RegistrationApprovalMode::Open->defaultStatus())->toBe(AffiliateStatus::Pending);
    expect(RegistrationApprovalMode::Admin->defaultStatus())->toBe(AffiliateStatus::Pending);
});

// FraudSeverity Tests (already partially covered, adding full coverage)
test('FraudSeverity enum has all expected cases', function (): void {
    expect(FraudSeverity::cases())->toHaveCount(4);

    expect(FraudSeverity::Low->value)->toBe('low');
    expect(FraudSeverity::Medium->value)->toBe('medium');
    expect(FraudSeverity::High->value)->toBe('high');
    expect(FraudSeverity::Critical->value)->toBe('critical');
});

test('FraudSeverity label returns correct labels', function (): void {
    expect(FraudSeverity::Low->label())->toBe('Low');
    expect(FraudSeverity::Medium->label())->toBe('Medium');
    expect(FraudSeverity::High->label())->toBe('High');
    expect(FraudSeverity::Critical->label())->toBe('Critical');
});

test('FraudSeverity color returns correct colors', function (): void {
    expect(FraudSeverity::Low->color())->toBe('gray');
    expect(FraudSeverity::Medium->color())->toBe('warning');
    expect(FraudSeverity::High->color())->toBe('danger');
    expect(FraudSeverity::Critical->color())->toBe('danger');
});

test('FraudSeverity riskThreshold returns correct values', function (): void {
    expect(FraudSeverity::Low->riskThreshold())->toBe(20);
    expect(FraudSeverity::Medium->riskThreshold())->toBe(50);
    expect(FraudSeverity::High->riskThreshold())->toBe(80);
    expect(FraudSeverity::Critical->riskThreshold())->toBe(100);
});
