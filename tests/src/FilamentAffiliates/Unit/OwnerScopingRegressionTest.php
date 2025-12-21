<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\FraudSeverity;
use AIArmada\Affiliates\Enums\FraudSignalStatus;
use AIArmada\Affiliates\Enums\PayoutStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateFraudSignal;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentAffiliates\Pages\FraudReviewPage;
use AIArmada\FilamentAffiliates\Pages\PayoutBatchPage;
use AIArmada\FilamentAuthz\Models\Permission;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config()->set('affiliates.owner.enabled', true);
    config()->set('affiliates.owner.include_global', false);

    AffiliateFraudSignal::query()->delete();
    AffiliatePayout::query()->delete();
    Affiliate::query()->delete();

    OwnerContext::clearOverride();
});

afterEach(function (): void {
    OwnerContext::clearOverride();
});

it('prevents cross-tenant reads and writes on admin payout and fraud pages', function (): void {
    $ownerA = User::create([
        'name' => 'Owner A',
        'email' => 'owner-a@example.com',
        'password' => 'secret',
    ]);

    OwnerContext::withOwner($ownerA, function () use ($ownerA): void {
        Permission::firstOrCreate(['name' => 'affiliates.payout.update', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'affiliates.fraud.update', 'guard_name' => 'web']);

        $ownerA->givePermissionTo('affiliates.payout.update');
        $ownerA->givePermissionTo('affiliates.fraud.update');
    });

    $this->actingAs($ownerA);

    $ownerB = User::create([
        'name' => 'Owner B',
        'email' => 'owner-b@example.com',
        'password' => 'secret',
    ]);

    [$affiliateA, $payoutA, $signalA] = OwnerContext::withOwner($ownerA, function (): array {
        $affiliate = Affiliate::create([
            'code' => 'A-' . Str::uuid(),
            'name' => 'Affiliate A',
            'status' => AffiliateStatus::Active,
            'commission_type' => 'percentage',
            'commission_rate' => 500,
            'currency' => 'USD',
        ]);

        $payout = AffiliatePayout::create([
            'reference' => 'PAY-A-' . Str::uuid(),
            'status' => PayoutStatus::Pending,
            'total_minor' => 5000,
            'currency' => 'USD',
            'affiliate_id' => $affiliate->getKey(),
            'payee_type' => $affiliate->getMorphClass(),
            'payee_id' => $affiliate->getKey(),
        ]);

        $signal = AffiliateFraudSignal::create([
            'affiliate_id' => $affiliate->getKey(),
            'rule_code' => 'velocity',
            'risk_points' => 80,
            'severity' => FraudSeverity::Critical,
            'description' => 'Velocity abuse detected',
            'status' => FraudSignalStatus::Detected,
            'detected_at' => now(),
        ]);

        return [$affiliate, $payout, $signal];
    });

    [$affiliateB, $payoutB, $signalB] = OwnerContext::withOwner($ownerB, function (): array {
        $affiliate = Affiliate::create([
            'code' => 'B-' . Str::uuid(),
            'name' => 'Affiliate B',
            'status' => AffiliateStatus::Active,
            'commission_type' => 'percentage',
            'commission_rate' => 500,
            'currency' => 'USD',
        ]);

        $payout = AffiliatePayout::create([
            'reference' => 'PAY-B-' . Str::uuid(),
            'status' => PayoutStatus::Pending,
            'total_minor' => 7000,
            'currency' => 'USD',
            'affiliate_id' => $affiliate->getKey(),
            'payee_type' => $affiliate->getMorphClass(),
            'payee_id' => $affiliate->getKey(),
        ]);

        $signal = AffiliateFraudSignal::create([
            'affiliate_id' => $affiliate->getKey(),
            'rule_code' => 'pattern',
            'risk_points' => 40,
            'severity' => FraudSeverity::Medium,
            'description' => 'Suspicious pattern detected',
            'status' => FraudSignalStatus::Detected,
            'detected_at' => now(),
        ]);

        return [$affiliate, $payout, $signal];
    });

    OwnerContext::withOwner($ownerA, function () use ($payoutA, $signalA, $payoutB, $signalB): void {
        $payoutPage = new PayoutBatchPage;
        $payoutData = $payoutPage->getViewData();

        expect($payoutData['pendingCount'])->toBe(1);

        $fraudPage = new FraudReviewPage;
        $fraudData = $fraudPage->getViewData();

        expect($fraudData['pendingCount'])->toBe(1)
            ->and($fraudData['criticalCount'])->toBe(1);

        $payoutTable = $payoutPage->table(Table::make($payoutPage));
        $reject = $payoutTable->getAction('reject');
        expect($reject)->not->toBeNull();

        expect(fn () => $reject?->call([
            'record' => $payoutB,
            'data' => ['reason' => 'Cross-tenant attempt'],
        ]))->toThrow(ModelNotFoundException::class);

        $fraudTable = $fraudPage->table(Table::make($fraudPage));
        $approve = $fraudTable->getAction('approve');
        expect($approve)->not->toBeNull();

        expect(fn () => $approve?->call(['record' => $signalB]))
            ->toThrow(ModelNotFoundException::class);

        // Sanity: same-tenant writes still possible.
        $reject?->call([
            'record' => $payoutA,
            'data' => ['reason' => 'Legitimate reject'],
        ]);

        $approve?->call(['record' => $signalA]);
    });
});
