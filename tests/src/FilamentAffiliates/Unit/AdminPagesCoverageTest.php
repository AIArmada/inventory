<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\FraudSeverity;
use AIArmada\Affiliates\Enums\FraudSignalStatus;
use AIArmada\Affiliates\Enums\PayoutStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateFraudSignal;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Services\AffiliateReportService;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAffiliates\Pages\FraudReviewPage;
use AIArmada\FilamentAffiliates\Pages\PayoutBatchPage;
use AIArmada\FilamentAffiliates\Pages\ReportsPage;
use Filament\Tables\Table;
use Illuminate\Support\Str;

beforeEach(function (): void {
    AffiliateFraudSignal::query()->delete();
    AffiliatePayout::query()->delete();
    Affiliate::query()->delete();
});

it('FraudReviewPage configures its table', function (): void {
    $table = Mockery::mock(Table::class);
    $table->shouldReceive('query')->once()->andReturnSelf();
    $table->shouldReceive('columns')->once()->andReturnSelf();
    $table->shouldReceive('filters')->once()->andReturnSelf();
    $table->shouldReceive('actions')->once()->andReturnSelf();
    $table->shouldReceive('bulkActions')->once()->andReturnSelf();

    $page = new FraudReviewPage;
    $page->table($table);

    expect(true)->toBeTrue();
});

it('FraudReviewPage view data reflects detected signals', function (): void {
    $user = User::create([
        'name' => 'Fraud Reviewer',
        'email' => 'fraud-reviewer@example.com',
        'password' => 'secret',
    ]);

    $this->actingAs($user);

    $affiliate = Affiliate::create([
        'code' => 'AFF-' . Str::uuid(),
        'name' => 'Fraud Affiliate',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);

    AffiliateFraudSignal::create([
        'affiliate_id' => $affiliate->getKey(),
        'rule_code' => 'velocity',
        'risk_points' => 80,
        'severity' => FraudSeverity::Critical,
        'description' => 'Velocity abuse detected',
        'status' => FraudSignalStatus::Detected,
        'detected_at' => now(),
    ]);

    AffiliateFraudSignal::create([
        'affiliate_id' => $affiliate->getKey(),
        'rule_code' => 'pattern',
        'risk_points' => 40,
        'severity' => FraudSeverity::Medium,
        'description' => 'Suspicious pattern detected',
        'status' => FraudSignalStatus::Detected,
        'detected_at' => now(),
    ]);

    $page = new FraudReviewPage;
    $data = $page->getViewData();

    expect($data['pendingCount'])->toBe(2)
        ->and($data['criticalCount'])->toBe(1);
});

it('PayoutBatchPage configures its table', function (): void {
    $table = Mockery::mock(Table::class);
    $table->shouldReceive('query')->once()->andReturnSelf();
    $table->shouldReceive('columns')->once()->andReturnSelf();
    $table->shouldReceive('filters')->once()->andReturnSelf();
    $table->shouldReceive('actions')->once()->andReturnSelf();
    $table->shouldReceive('bulkActions')->once()->andReturnSelf();

    $page = new PayoutBatchPage;
    $page->table($table);

    expect(true)->toBeTrue();
});

it('PayoutBatchPage view data aggregates pending payouts', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'AFF-' . Str::uuid(),
        'name' => 'Payout Affiliate',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);

    AffiliatePayout::create([
        'reference' => 'PAY-' . Str::uuid(),
        'status' => PayoutStatus::Pending,
        'total_minor' => 5000,
        'currency' => 'USD',
        'owner_type' => $affiliate->getMorphClass(),
        'owner_id' => $affiliate->getKey(),
    ]);

    AffiliatePayout::create([
        'reference' => 'PAY-' . Str::uuid(),
        'status' => PayoutStatus::Pending,
        'total_minor' => 7000,
        'currency' => 'USD',
        'owner_type' => $affiliate->getMorphClass(),
        'owner_id' => $affiliate->getKey(),
    ]);

    $page = new PayoutBatchPage;
    $data = $page->getViewData();

    expect($data['pendingCount'])->toBe(2)
        ->and($data['pendingTotal'])->toBe(12000)
        ->and($data['pendingByCurrency'])->toHaveCount(1);
});

it('ReportsPage generates report data via the report service', function (): void {
    $this->app->instance(AffiliateReportService::class, new class
    {
        public function getSummary($start, $end): array
        {
            return ['total_conversions' => 1];
        }

        public function getTopAffiliates($start, $end, int $limit): array
        {
            return [['code' => 'AFF-001', 'total' => 123]];
        }

        public function getConversionTrend($start, $end): array
        {
            return [['date' => '2025-01-01', 'count' => 1]];
        }

        public function getTrafficSources($start, $end): array
        {
            return [['source' => 'direct', 'count' => 1]];
        }
    });

    $page = new ReportsPage;
    $page->period = 'month';
    $page->generateReport();

    $viewData = $page->getViewData();

    expect($viewData)->toHaveKey('reportData')
        ->and($viewData['reportData'])->toHaveKey('summary')
        ->and($viewData['reportData'])->toHaveKey('top_affiliates')
        ->and($viewData['reportData'])->toHaveKey('conversion_trend')
        ->and($viewData['reportData'])->toHaveKey('traffic_sources');
});
