<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\ConversionStatus;
use AIArmada\Affiliates\Enums\FraudSeverity;
use AIArmada\Affiliates\Enums\FraudSignalStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateFraudSignal;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAffiliates\Pages\FraudReviewPage;
use AIArmada\FilamentAuthz\Models\Permission;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

beforeEach(function (): void {
    AffiliateFraudSignal::query()->delete();
    AffiliateConversion::query()->delete();
    Affiliate::query()->delete();
});

it('executes approve and reject record actions', function (): void {
    $user = User::create([
        'name' => 'Fraud Reviewer',
        'email' => 'fraud-reviewer-actions@example.com',
        'password' => 'secret',
    ]);

    Permission::create(['name' => 'affiliates.fraud.update', 'guard_name' => 'web']);

    $user->givePermissionTo('affiliates.fraud.update');

    $this->actingAs($user);

    $affiliate = Affiliate::create([
        'code' => 'AFF-' . Str::uuid(),
        'name' => 'Fraud Affiliate',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);

    $conversion = AffiliateConversion::create([
        'affiliate_id' => $affiliate->getKey(),
        'affiliate_code' => $affiliate->code,
        'subtotal_minor' => 10000,
        'total_minor' => 10000,
        'commission_minor' => 500,
        'commission_currency' => 'USD',
        'status' => ConversionStatus::Pending,
        'occurred_at' => now(),
    ]);

    $signalToApprove = AffiliateFraudSignal::create([
        'affiliate_id' => $affiliate->getKey(),
        'conversion_id' => $conversion->getKey(),
        'rule_code' => 'velocity',
        'risk_points' => 80,
        'severity' => FraudSeverity::Critical,
        'description' => 'Velocity abuse detected',
        'status' => FraudSignalStatus::Detected,
        'detected_at' => now(),
    ]);

    $signalToReject = AffiliateFraudSignal::create([
        'affiliate_id' => $affiliate->getKey(),
        'conversion_id' => $conversion->getKey(),
        'rule_code' => 'pattern',
        'risk_points' => 40,
        'severity' => FraudSeverity::Medium,
        'description' => 'Suspicious pattern detected',
        'status' => FraudSignalStatus::Detected,
        'detected_at' => now(),
    ]);

    $page = new FraudReviewPage;
    $table = $page->table(Table::make($page));

    $approve = $table->getAction('approve');
    expect($approve)->not->toBeNull();
    $approve?->call(['record' => $signalToApprove]);

    $signalToApprove->refresh();
    expect($signalToApprove->status)->toBe(FraudSignalStatus::Dismissed)
        ->and($signalToApprove->reviewed_by)->toBe((string) $user->getAuthIdentifier())
        ->and($signalToApprove->reviewed_at)->not->toBeNull();

    $reject = $table->getAction('reject');
    expect($reject)->not->toBeNull();
    $reject?->call([
        'record' => $signalToReject,
        'data' => ['notes' => 'Confirmed fraud by QA'],
    ]);

    $signalToReject->refresh();
    $conversion->refresh();

    expect($signalToReject->status)->toBe(FraudSignalStatus::Confirmed)
        ->and($signalToReject->evidence)->toBeArray()
        ->and($signalToReject->evidence['review_notes'])->toBe('Confirmed fraud by QA')
        ->and($conversion->status)->toBe(ConversionStatus::Rejected);
});

it('executes bulk approve and bulk reject actions', function (): void {
    $user = User::create([
        'name' => 'Fraud Bulk Reviewer',
        'email' => 'fraud-reviewer-bulk-actions@example.com',
        'password' => 'secret',
    ]);

    Permission::create(['name' => 'affiliates.fraud.update', 'guard_name' => 'web']);

    $user->givePermissionTo('affiliates.fraud.update');

    $this->actingAs($user);

    $affiliate = Affiliate::create([
        'code' => 'AFF-' . Str::uuid(),
        'name' => 'Fraud Bulk Affiliate',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);

    $conversion = AffiliateConversion::create([
        'affiliate_id' => $affiliate->getKey(),
        'affiliate_code' => $affiliate->code,
        'subtotal_minor' => 10000,
        'total_minor' => 10000,
        'commission_minor' => 500,
        'commission_currency' => 'USD',
        'status' => ConversionStatus::Pending,
        'occurred_at' => now(),
    ]);

    $bulkApproveSignalA = AffiliateFraudSignal::create([
        'affiliate_id' => $affiliate->getKey(),
        'rule_code' => 'velocity',
        'risk_points' => 80,
        'severity' => FraudSeverity::High,
        'description' => 'Velocity abuse detected',
        'status' => FraudSignalStatus::Detected,
        'detected_at' => now(),
    ]);

    $bulkApproveSignalB = AffiliateFraudSignal::create([
        'affiliate_id' => $affiliate->getKey(),
        'rule_code' => 'pattern',
        'risk_points' => 40,
        'severity' => FraudSeverity::Medium,
        'description' => 'Suspicious pattern detected',
        'status' => FraudSignalStatus::Detected,
        'detected_at' => now(),
    ]);

    $bulkRejectSignal = AffiliateFraudSignal::create([
        'affiliate_id' => $affiliate->getKey(),
        'conversion_id' => $conversion->getKey(),
        'rule_code' => 'self_referral',
        'risk_points' => 90,
        'severity' => FraudSeverity::Critical,
        'description' => 'Self referral detected',
        'status' => FraudSignalStatus::Detected,
        'detected_at' => now(),
    ]);

    $page = new FraudReviewPage;
    $table = $page->table(Table::make($page));

    $bulkApprove = $table->getBulkAction('bulk_approve');
    expect($bulkApprove)->not->toBeNull();
    $bulkApprove?->call(['records' => new Collection([$bulkApproveSignalA, $bulkApproveSignalB])]);

    $bulkApproveSignalA->refresh();
    $bulkApproveSignalB->refresh();

    expect($bulkApproveSignalA->status)->toBe(FraudSignalStatus::Dismissed)
        ->and($bulkApproveSignalB->status)->toBe(FraudSignalStatus::Dismissed);

    $bulkReject = $table->getBulkAction('bulk_reject');
    expect($bulkReject)->not->toBeNull();
    $bulkReject?->call(['records' => new Collection([$bulkRejectSignal])]);

    $bulkRejectSignal->refresh();
    $conversion->refresh();

    expect($bulkRejectSignal->status)->toBe(FraudSignalStatus::Confirmed)
        ->and($conversion->status)->toBe(ConversionStatus::Rejected);
});
