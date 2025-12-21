<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\PayoutMethodType;
use AIArmada\Affiliates\Enums\PayoutStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Models\AffiliatePayoutMethod;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAffiliates\Pages\PayoutBatchPage;
use AIArmada\FilamentAuthz\Models\Permission;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

beforeEach(function (): void {
    AffiliatePayout::query()->delete();
    AffiliatePayoutMethod::query()->delete();
    Affiliate::query()->delete();
});

it('processes a payout via the record action', function (): void {
    $user = User::create([
        'name' => 'Payout Processor',
        'email' => 'payout-processor@example.com',
        'password' => 'secret',
    ]);

    Permission::create(['name' => 'affiliates.payout.update', 'guard_name' => 'web']);

    $user->givePermissionTo('affiliates.payout.update');
    $this->actingAs($user);

    $affiliate = Affiliate::create([
        'code' => 'AFF-' . Str::uuid(),
        'name' => 'Payout Affiliate',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);

    AffiliatePayoutMethod::create([
        'affiliate_id' => $affiliate->getKey(),
        'type' => PayoutMethodType::BankTransfer,
        'details' => ['bank_name' => 'Test Bank', 'account_number' => '12345678'],
        'is_verified' => true,
        'is_default' => true,
    ]);

    $payout = AffiliatePayout::create([
        'reference' => 'PAY-' . Str::uuid(),
        'status' => PayoutStatus::Pending,
        'total_minor' => 5000,
        'currency' => 'USD',
        'payee_type' => $affiliate->getMorphClass(),
        'payee_id' => $affiliate->getKey(),
    ]);

    $page = new PayoutBatchPage;
    $table = $page->table(Table::make($page));

    $process = $table->getAction('process');
    expect($process)->not->toBeNull();

    $process?->call(['record' => $payout]);

    $payout->refresh();

    expect($payout->status)->toBe(PayoutStatus::Completed)
        ->and($payout->paid_at)->not->toBeNull()
        ->and($payout->external_reference)->not->toBeNull();

    expect($payout->events()->count())->toBeGreaterThanOrEqual(1);
});

it('marks payout as failed when no default method exists', function (): void {
    $user = User::create([
        'name' => 'Payout Processor (No Method)',
        'email' => 'payout-processor-no-method@example.com',
        'password' => 'secret',
    ]);

    Permission::create(['name' => 'affiliates.payout.update', 'guard_name' => 'web']);

    $user->givePermissionTo('affiliates.payout.update');
    $this->actingAs($user);

    $affiliate = Affiliate::create([
        'code' => 'AFF-' . Str::uuid(),
        'name' => 'No Method Affiliate',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);

    $payout = AffiliatePayout::create([
        'reference' => 'PAY-' . Str::uuid(),
        'status' => PayoutStatus::Pending,
        'total_minor' => 5000,
        'currency' => 'USD',
        'payee_type' => $affiliate->getMorphClass(),
        'payee_id' => $affiliate->getKey(),
    ]);

    $page = new PayoutBatchPage;
    $table = $page->table(Table::make($page));

    $process = $table->getAction('process');
    $process?->call(['record' => $payout]);

    $payout->refresh();

    expect($payout->status)->toBe(PayoutStatus::Failed);
    expect($payout->events()->count())->toBeGreaterThanOrEqual(1);
});

it('rejects a payout and stores notes in metadata', function (): void {
    $user = User::create([
        'name' => 'Payout Rejector',
        'email' => 'payout-rejector@example.com',
        'password' => 'secret',
    ]);

    Permission::create(['name' => 'affiliates.payout.update', 'guard_name' => 'web']);

    $user->givePermissionTo('affiliates.payout.update');
    $this->actingAs($user);

    $affiliate = Affiliate::create([
        'code' => 'AFF-' . Str::uuid(),
        'name' => 'Reject Affiliate',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);

    $payout = AffiliatePayout::create([
        'reference' => 'PAY-' . Str::uuid(),
        'status' => PayoutStatus::Pending,
        'total_minor' => 5000,
        'currency' => 'USD',
        'payee_type' => $affiliate->getMorphClass(),
        'payee_id' => $affiliate->getKey(),
    ]);

    $page = new PayoutBatchPage;
    $table = $page->table(Table::make($page));

    $reject = $table->getAction('reject');
    expect($reject)->not->toBeNull();

    $reject?->call([
        'record' => $payout,
        'data' => ['reason' => 'Insufficient verification'],
    ]);

    $payout->refresh();

    expect($payout->status)->toBe(PayoutStatus::Failed)
        ->and($payout->metadata)->toBeArray()
        ->and($payout->metadata['notes'])->toBe('Insufficient verification');

    expect($payout->events()->count())->toBeGreaterThanOrEqual(1);
});

it('executes batch processing bulk action', function (): void {
    $user = User::create([
        'name' => 'Payout Bulk Processor',
        'email' => 'payout-bulk-processor@example.com',
        'password' => 'secret',
    ]);

    Permission::create(['name' => 'affiliates.payout.update', 'guard_name' => 'web']);

    $user->givePermissionTo('affiliates.payout.update');
    $this->actingAs($user);

    $affiliateA = Affiliate::create([
        'code' => 'AFF-' . Str::uuid(),
        'name' => 'Batch Affiliate A',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);

    $affiliateB = Affiliate::create([
        'code' => 'AFF-' . Str::uuid(),
        'name' => 'Batch Affiliate B',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);

    AffiliatePayoutMethod::create([
        'affiliate_id' => $affiliateA->getKey(),
        'type' => PayoutMethodType::BankTransfer,
        'details' => ['bank_name' => 'Test Bank', 'account_number' => '12345678'],
        'is_verified' => true,
        'is_default' => true,
    ]);

    $payoutA = AffiliatePayout::create([
        'reference' => 'PAY-' . Str::uuid(),
        'status' => PayoutStatus::Pending,
        'total_minor' => 5000,
        'currency' => 'USD',
        'payee_type' => $affiliateA->getMorphClass(),
        'payee_id' => $affiliateA->getKey(),
    ]);

    $payoutB = AffiliatePayout::create([
        'reference' => 'PAY-' . Str::uuid(),
        'status' => PayoutStatus::Pending,
        'total_minor' => 7000,
        'currency' => 'USD',
        'payee_type' => $affiliateB->getMorphClass(),
        'payee_id' => $affiliateB->getKey(),
    ]);

    $page = new PayoutBatchPage;
    $table = $page->table(Table::make($page));

    $bulk = $table->getBulkAction('batch_process');
    expect($bulk)->not->toBeNull();

    $bulk?->call(['records' => new Collection([$payoutA, $payoutB])]);

    $payoutA->refresh();
    $payoutB->refresh();

    expect($payoutA->status)->toBe(PayoutStatus::Completed)
        ->and($payoutB->status)->toBe(PayoutStatus::Failed);
});
