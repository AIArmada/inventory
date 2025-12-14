<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateCommissionRule;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Models\AffiliateProgramMembership;
use AIArmada\Affiliates\Enums\MembershipStatus;
use AIArmada\Affiliates\Services\ProgramService;
use AIArmada\Affiliates\Support\CartWithAffiliates;
use AIArmada\Affiliates\Support\CartManagerWithAffiliates;
use AIArmada\Affiliates\Support\Integrations\CartIntegrationRegistrar;
use AIArmada\Affiliates\Support\Integrations\VoucherIntegrationRegistrar;
use AIArmada\Affiliates\Support\Webhooks\WebhookDispatcher;
use AIArmada\Affiliates\Services\AffiliateReportService;
use AIArmada\Affiliates\Services\CommissionMaturityService;
use AIArmada\Affiliates\Actions\Conversions\MatureConversion;
use AIArmada\Affiliates\Actions\Conversions\ProcessConversionMaturity;
use Illuminate\Support\Carbon;

// CartIntegrationRegistrar Tests
test('CartIntegrationRegistrar can be instantiated', function (): void {
    $registrar = app(CartIntegrationRegistrar::class);
    expect($registrar)->toBeInstanceOf(CartIntegrationRegistrar::class);
});

// VoucherIntegrationRegistrar Tests
test('VoucherIntegrationRegistrar can be instantiated', function (): void {
    $registrar = app(VoucherIntegrationRegistrar::class);
    expect($registrar)->toBeInstanceOf(VoucherIntegrationRegistrar::class);
});

// WebhookDispatcher Tests
test('WebhookDispatcher can be instantiated', function (): void {
    $dispatcher = app(WebhookDispatcher::class);
    expect($dispatcher)->toBeInstanceOf(WebhookDispatcher::class);
});

test('WebhookDispatcher dispatch returns void without errors', function (): void {
    $dispatcher = app(WebhookDispatcher::class);

    $affiliate = Affiliate::create([
        'code' => 'WEBHOOK001',
        'name' => 'Webhook Test',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    // Just verify no exception
    $dispatcher->dispatch('affiliate.created', ['affiliate_id' => $affiliate->id]);

    expect(true)->toBeTrue();
});

// ProgramService Tests
test('ProgramService can get available programs', function (): void {
    $service = app(ProgramService::class);

    // getAvailablePrograms() takes no arguments
    $programs = $service->getAvailablePrograms();

    expect($programs)->toBeInstanceOf(\Illuminate\Support\Collection::class);
});

test('ProgramService isMember returns false for non-member', function (): void {
    $service = app(ProgramService::class);

    $affiliate = Affiliate::create([
        'code' => 'MEMBER001',
        'name' => 'Member Test',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $program = AffiliateProgram::create([
        'name' => 'Test Program',
        'slug' => 'test-program',
        'description' => 'Test program description',
        'status' => 'active',
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
    ]);

    $isMember = $service->isMember($affiliate, $program);

    expect($isMember)->toBeFalse();
});

test('ProgramService joinProgram creates membership', function (): void {
    $service = app(ProgramService::class);

    $affiliate = Affiliate::create([
        'code' => 'JOIN001',
        'name' => 'Join Test',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $program = AffiliateProgram::create([
        'name' => 'Join Program',
        'slug' => 'join-program',
        'description' => 'Join program description',
        'status' => 'active',
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
    ]);

    $membership = $service->joinProgram($affiliate, $program);

    expect($membership)->toBeInstanceOf(AffiliateProgramMembership::class);
    expect($service->isMember($affiliate, $program))->toBeTrue();
});

test('ProgramService leaveProgram removes membership', function (): void {
    $service = app(ProgramService::class);

    $affiliate = Affiliate::create([
        'code' => 'LEAVE001',
        'name' => 'Leave Test',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $program = AffiliateProgram::create([
        'name' => 'Leave Program',
        'slug' => 'leave-program',
        'description' => 'Leave program description',
        'status' => 'active',
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
    ]);

    // First join
    $service->joinProgram($affiliate, $program);
    expect($service->isMember($affiliate, $program))->toBeTrue();

    // Then leave
    $service->leaveProgram($affiliate, $program);
    expect($service->isMember($affiliate->fresh(), $program))->toBeFalse();
});

// AffiliateReportService Tests
test('AffiliateReportService can be instantiated', function (): void {
    $service = app(AffiliateReportService::class);
    expect($service)->toBeInstanceOf(AffiliateReportService::class);
});

test('AffiliateReportService affiliateSummary returns array', function (): void {
    $service = app(AffiliateReportService::class);

    $affiliate = Affiliate::create([
        'code' => 'REPORT001',
        'name' => 'Report Test',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $summary = $service->affiliateSummary($affiliate->id);

    expect($summary)->toBeArray();
});

// CommissionMaturityService Tests
test('CommissionMaturityService can be instantiated', function (): void {
    $service = app(CommissionMaturityService::class);
    expect($service)->toBeInstanceOf(CommissionMaturityService::class);
});

// MatureConversion Action Tests
test('MatureConversion action can be resolved', function (): void {
    $action = app(MatureConversion::class);
    expect($action)->toBeInstanceOf(MatureConversion::class);
});

// ProcessConversionMaturity Action Tests
test('ProcessConversionMaturity action can be resolved', function (): void {
    $action = app(ProcessConversionMaturity::class);
    expect($action)->toBeInstanceOf(ProcessConversionMaturity::class);
});

// AffiliateCommissionRule Model Tests
test('AffiliateCommissionRule can be created', function (): void {
    $rule = AffiliateCommissionRule::create([
        'name' => 'Test Rule',
        'rule_type' => 'product',  // valid CommissionRuleType value
        'commission_type' => 'percentage',
        'commission_value' => 1000,
        'is_active' => true,
        'priority' => 1,
        'conditions' => ['min_amount' => 5000],
    ]);

    expect($rule)->toBeInstanceOf(AffiliateCommissionRule::class);
    expect($rule->name)->toBe('Test Rule');
    expect($rule->is_active)->toBeTrue();
});

test('AffiliateCommissionRule getTable returns configured table', function (): void {
    $rule = new AffiliateCommissionRule;
    expect($rule->getTable())->toBeString();
});

// Carbon date usage in services
test('Date ranges work correctly with Carbon', function (): void {
    $from = Carbon::now()->subMonth()->startOfMonth();
    $to = Carbon::now()->endOfMonth();

    expect($from->isBefore($to))->toBeTrue();
    expect($from->diffInDays($to))->toBeGreaterThan(0);
});
