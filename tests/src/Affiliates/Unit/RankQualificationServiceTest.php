<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\ConversionStatus;
use AIArmada\Affiliates\Enums\RankQualificationReason;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateRank;
use AIArmada\Affiliates\Models\AffiliateRankHistory;
use AIArmada\Affiliates\Services\NetworkService;
use AIArmada\Affiliates\Services\RankQualificationService;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->service = app(RankQualificationService::class);
    $this->networkService = app(NetworkService::class);

    $this->affiliate = Affiliate::create([
        'code' => 'RANK-' . uniqid(),
        'name' => 'Rank Test Affiliate',
        'contact_email' => 'rank@example.com',
        'status' => AffiliateStatus::Active,
        'commission_type' => CommissionType::Percentage,
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);
});

describe('RankQualificationService', function (): void {
    describe('evaluate', function (): void {
        test('returns null when no ranks exist', function (): void {
            $result = $this->service->evaluate($this->affiliate);

            expect($result)->toBeNull();
        });

        test('returns null when affiliate does not qualify', function (): void {
            AffiliateRank::create([
                'name' => 'Gold',
                'slug' => 'gold',
                'level' => 2,
                'min_personal_sales' => 100000,
                'min_team_sales' => 500000,
                'commission_rate_basis_points' => 0,
                'commission_rate_basis_points' => 0,
                'min_active_downlines' => 10,
            ]);

            $result = $this->service->evaluate($this->affiliate);

            expect($result)->toBeNull();
        });

        test('returns highest qualifying rank', function (): void {
            // Create Bronze rank (easy to qualify)
            $bronzeRank = AffiliateRank::create([
                'name' => 'Bronze',
                'slug' => 'bronze',
                'level' => 1,
                'min_personal_sales' => 0,
                'min_team_sales' => 0,
                'commission_rate_basis_points' => 0,
                'commission_rate_basis_points' => 0,
                'min_active_downlines' => 0,
            ]);

            // Create Silver rank (harder to qualify)
            AffiliateRank::create([
                'name' => 'Silver',
                'slug' => 'silver',
                'level' => 2,
                'min_personal_sales' => 100000,
                'min_team_sales' => 500000,
                'commission_rate_basis_points' => 0,
                'commission_rate_basis_points' => 0,
                'min_active_downlines' => 5,
            ]);

            $result = $this->service->evaluate($this->affiliate);

            expect($result)->not->toBeNull();
            expect($result->id)->toBe($bronzeRank->id);
            expect($result->name)->toBe('Bronze');
        });

        test('evaluates based on personal sales', function (): void {
            $rank = AffiliateRank::create([
                'name' => 'Sales Rank',
                'slug' => 'sales-rank',
                'level' => 1,
                'min_personal_sales' => 5000,
                'min_team_sales' => 0,
                'commission_rate_basis_points' => 0,
                'commission_rate_basis_points' => 0,
                'min_active_downlines' => 0,
            ]);

            // Create conversions to meet threshold
            AffiliateConversion::create([
                'affiliate_id' => $this->affiliate->id,
                'affiliate_code' => $this->affiliate->code,
                'order_reference' => 'SALES-001',
                'subtotal_minor' => 6000,
                'total_minor' => 6000,
                'commission_minor' => 600,
                'status' => ConversionStatus::Approved,
                'occurred_at' => now()->subDays(5),
            ]);

            $result = $this->service->evaluate($this->affiliate);

            expect($result)->not->toBeNull();
            expect($result->id)->toBe($rank->id);
        });
    });

    describe('processRankChange', function (): void {
        test('assigns rank when affiliate qualifies', function (): void {
            $rank = AffiliateRank::create([
                'name' => 'Starter',
                'slug' => 'starter',
                'level' => 1,
                'min_personal_sales' => 0,
                'min_team_sales' => 0,
                'commission_rate_basis_points' => 0,
                'min_active_downlines' => 0,
            ]);

            $this->service->processRankChange($this->affiliate);

            $this->affiliate->refresh();
            expect($this->affiliate->rank_id)->toBe($rank->id);

            // Verify history record was created (proves the processRankChange completed)
            $history = AffiliateRankHistory::where('affiliate_id', $this->affiliate->id)->first();
            expect($history)->not->toBeNull();
            expect($history->to_rank_id)->toBe($rank->id);
        });

        test('does not change when rank stays same', function (): void {
            $rank = AffiliateRank::create([
                'name' => 'Starter',
                'slug' => 'starter',
                'level' => 1,
                'min_personal_sales' => 0,
                'min_team_sales' => 0,
                'commission_rate_basis_points' => 0,
                'min_active_downlines' => 0,
            ]);

            $this->affiliate->update(['rank_id' => $rank->id]);

            $historyCountBefore = AffiliateRankHistory::where('affiliate_id', $this->affiliate->id)->count();

            $this->service->processRankChange($this->affiliate);

            // No new history record should be created when rank stays the same
            $historyCountAfter = AffiliateRankHistory::where('affiliate_id', $this->affiliate->id)->count();
            expect($historyCountAfter)->toBe($historyCountBefore);
        });

        test('creates rank history record', function (): void {
            $rank = AffiliateRank::create([
                'name' => 'Starter',
                'slug' => 'starter',
                'level' => 1,
                'min_personal_sales' => 0,
                'min_team_sales' => 0,
                'commission_rate_basis_points' => 0,
                'min_active_downlines' => 0,
            ]);

            $this->service->processRankChange($this->affiliate);

            $history = AffiliateRankHistory::where('affiliate_id', $this->affiliate->id)->first();

            expect($history)->not->toBeNull();
            expect($history->to_rank_id)->toBe($rank->id);
            expect($history->reason)->toBe(RankQualificationReason::Qualified);
        });
    });

    describe('processAllRankUpgrades', function (): void {
        test('returns count of upgraded affiliates', function (): void {
            AffiliateRank::create([
                'name' => 'Entry',
                'slug' => 'entry',
                'level' => 1,
                'min_personal_sales' => 0,
                'min_team_sales' => 0,
                'commission_rate_basis_points' => 0,
                'min_active_downlines' => 0,
            ]);

            $count = $this->service->processAllRankUpgrades();

            expect($count)->toBe(1);
        });

        test('processes all affiliates', function (): void {
            $rank = AffiliateRank::create([
                'name' => 'Entry',
                'slug' => 'entry',
                'level' => 1,
                'min_personal_sales' => 0,
                'min_team_sales' => 0,
                'commission_rate_basis_points' => 0,
                'min_active_downlines' => 0,
            ]);

            // Create additional affiliates
            Affiliate::create([
                'code' => 'ALL-1-' . uniqid(),
                'name' => 'All Test 1',
                'contact_email' => 'all1@example.com',
                'status' => AffiliateStatus::Active,
                'commission_type' => CommissionType::Percentage,
                'commission_rate' => 1000,
                'currency' => 'USD',
            ]);

            Affiliate::create([
                'code' => 'ALL-2-' . uniqid(),
                'name' => 'All Test 2',
                'contact_email' => 'all2@example.com',
                'status' => AffiliateStatus::Active,
                'commission_type' => CommissionType::Percentage,
                'commission_rate' => 1000,
                'currency' => 'USD',
            ]);

            $count = $this->service->processAllRankUpgrades();

            expect($count)->toBe(3);

            // Verify all 3 affiliates got the rank
            $affiliatesWithRank = Affiliate::whereNotNull('rank_id')
                ->where('rank_id', $rank->id)
                ->count();
            expect($affiliatesWithRank)->toBe(3);
        });
    });

    describe('processBatch', function (): void {
        test('processes multiple affiliates', function (): void {
            $rank = AffiliateRank::create([
                'name' => 'Batch Rank',
                'slug' => 'batch-rank',
                'level' => 1,
                'min_personal_sales' => 0,
                'min_team_sales' => 0,
                'commission_rate_basis_points' => 0,
                'min_active_downlines' => 0,
            ]);

            $affiliate2 = Affiliate::create([
                'code' => 'BATCH-' . uniqid(),
                'name' => 'Batch Affiliate',
                'contact_email' => 'batch@example.com',
                'status' => AffiliateStatus::Active,
                'commission_type' => CommissionType::Percentage,
                'commission_rate' => 1000,
                'currency' => 'USD',
            ]);

            $this->service->processBatch([$this->affiliate, $affiliate2]);

            // Verify both affiliates were assigned the rank
            $this->affiliate->refresh();
            $affiliate2->refresh();

            expect($this->affiliate->rank_id)->toBe($rank->id);
            expect($affiliate2->rank_id)->toBe($rank->id);

            // Verify history records created for both
            $historyCount = AffiliateRankHistory::whereIn('affiliate_id', [
                $this->affiliate->id,
                $affiliate2->id,
            ])->count();
            expect($historyCount)->toBe(2);
        });
    });

    describe('assignRank', function (): void {
        test('manually assigns rank', function (): void {
            $rank = AffiliateRank::create([
                'name' => 'Manual Rank',
                'slug' => 'manual-rank',
                'level' => 5,
                'min_personal_sales' => 1000000,
                'min_team_sales' => 1000000,
                'commission_rate_basis_points' => 0,
                'min_active_downlines' => 100,
            ]);

            $this->service->assignRank($this->affiliate, $rank);

            $this->affiliate->refresh();
            expect($this->affiliate->rank_id)->toBe($rank->id);

            // Verify history record was created with the assignment
            $history = AffiliateRankHistory::where('affiliate_id', $this->affiliate->id)->first();
            expect($history)->not->toBeNull();
            expect($history->to_rank_id)->toBe($rank->id);
        });

        test('creates manual reason in history', function (): void {
            $rank = AffiliateRank::create([
                'name' => 'Manual Rank',
                'slug' => 'manual-rank',
                'level' => 1,
                'min_personal_sales' => 0,
                'min_team_sales' => 0,
                'commission_rate_basis_points' => 0,
                'min_active_downlines' => 0,
            ]);

            $this->service->assignRank($this->affiliate, $rank);

            $history = AffiliateRankHistory::where('affiliate_id', $this->affiliate->id)->first();

            expect($history->reason)->toBe(RankQualificationReason::Manual);
        });

        test('can remove rank by assigning null', function (): void {
            $rank = AffiliateRank::create([
                'name' => 'Temp Rank',
                'slug' => 'temp-rank',
                'level' => 1,
                'min_personal_sales' => 0,
                'min_team_sales' => 0,
                'commission_rate_basis_points' => 0,
                'min_active_downlines' => 0,
            ]);

            $this->affiliate->update(['rank_id' => $rank->id]);

            $this->service->assignRank($this->affiliate, null);

            $this->affiliate->refresh();
            expect($this->affiliate->rank_id)->toBeNull();
        });
    });

    describe('calculateMetrics', function (): void {
        test('returns metrics array', function (): void {
            $metrics = $this->service->calculateMetrics($this->affiliate);

            expect($metrics)->toHaveKeys([
                'personal_sales',
                'team_sales',
                'active_downlines',
                'lifetime_value',
            ]);
        });

        test('calculates personal sales from conversions', function (): void {
            AffiliateConversion::create([
                'affiliate_id' => $this->affiliate->id,
                'affiliate_code' => $this->affiliate->code,
                'order_reference' => 'METRIC-001',
                'subtotal_minor' => 5000,
                'total_minor' => 5500,
                'commission_minor' => 550,
                'status' => ConversionStatus::Approved,
                'occurred_at' => now()->subDays(5),
            ]);

            AffiliateConversion::create([
                'affiliate_id' => $this->affiliate->id,
                'affiliate_code' => $this->affiliate->code,
                'order_reference' => 'METRIC-002',
                'subtotal_minor' => 3000,
                'total_minor' => 3300,
                'commission_minor' => 330,
                'status' => ConversionStatus::Approved,
                'occurred_at' => now()->subDays(10),
            ]);

            $metrics = $this->service->calculateMetrics($this->affiliate);

            expect($metrics['personal_sales'])->toBe(8800);
        });

        test('respects date range for personal sales', function (): void {
            // Recent sale
            AffiliateConversion::create([
                'affiliate_id' => $this->affiliate->id,
                'affiliate_code' => $this->affiliate->code,
                'order_reference' => 'RECENT',
                'subtotal_minor' => 5000,
                'total_minor' => 5000,
                'commission_minor' => 500,
                'status' => ConversionStatus::Approved,
                'occurred_at' => now()->subDays(5),
            ]);

            // Old sale (outside 30 day default)
            AffiliateConversion::create([
                'affiliate_id' => $this->affiliate->id,
                'affiliate_code' => $this->affiliate->code,
                'order_reference' => 'OLD',
                'subtotal_minor' => 10000,
                'total_minor' => 10000,
                'commission_minor' => 1000,
                'status' => ConversionStatus::Approved,
                'occurred_at' => now()->subDays(45),
            ]);

            $metrics = $this->service->calculateMetrics($this->affiliate);

            expect($metrics['personal_sales'])->toBe(5000);
            expect($metrics['lifetime_value'])->toBe(15000); // All time
        });

        test('caches metrics for same affiliate and date', function (): void {
            // First call
            $metrics1 = $this->service->calculateMetrics($this->affiliate);

            // Add a conversion
            AffiliateConversion::create([
                'affiliate_id' => $this->affiliate->id,
                'affiliate_code' => $this->affiliate->code,
                'order_reference' => 'CACHED',
                'subtotal_minor' => 5000,
                'total_minor' => 5000,
                'commission_minor' => 500,
                'status' => ConversionStatus::Approved,
                'occurred_at' => now()->subDays(5),
            ]);

            // Second call should return cached result
            $metrics2 = $this->service->calculateMetrics($this->affiliate);

            expect($metrics1)->toBe($metrics2);
        });

        test('uses custom date range', function (): void {
            AffiliateConversion::create([
                'affiliate_id' => $this->affiliate->id,
                'affiliate_code' => $this->affiliate->code,
                'order_reference' => 'CUSTOM',
                'subtotal_minor' => 5000,
                'total_minor' => 5000,
                'commission_minor' => 500,
                'status' => ConversionStatus::Approved,
                'occurred_at' => now()->subDays(60),
            ]);

            // With 90 day lookback
            $metrics = $this->service->calculateMetrics($this->affiliate, Carbon::now()->subDays(90));

            expect($metrics['personal_sales'])->toBe(5000);
        });
    });

    describe('clearCache', function (): void {
        test('clears cached metrics', function (): void {
            // First call
            $metrics1 = $this->service->calculateMetrics($this->affiliate);

            // Clear cache
            $this->service->clearCache();

            // Add a conversion
            AffiliateConversion::create([
                'affiliate_id' => $this->affiliate->id,
                'affiliate_code' => $this->affiliate->code,
                'order_reference' => 'NEW-CACHE',
                'subtotal_minor' => 5000,
                'total_minor' => 5000,
                'commission_minor' => 500,
                'status' => ConversionStatus::Approved,
                'occurred_at' => now()->subDays(5),
            ]);

            // Second call should recalculate
            $metrics2 = $this->service->calculateMetrics($this->affiliate);

            expect($metrics2['personal_sales'])->toBeGreaterThan($metrics1['personal_sales']);
        });
    });
});

describe('RankQualificationService class structure', function (): void {
    test('can be instantiated', function (): void {
        $service = app(RankQualificationService::class);
        expect($service)->toBeInstanceOf(RankQualificationService::class);
    });

    test('is declared as final', function (): void {
        $reflection = new ReflectionClass(RankQualificationService::class);
        expect($reflection->isFinal())->toBeTrue();
    });

    test('has required public methods', function (): void {
        $reflection = new ReflectionClass(RankQualificationService::class);

        expect($reflection->hasMethod('evaluate'))->toBeTrue();
        expect($reflection->hasMethod('processRankChange'))->toBeTrue();
        expect($reflection->hasMethod('processAllRankUpgrades'))->toBeTrue();
        expect($reflection->hasMethod('processBatch'))->toBeTrue();
        expect($reflection->hasMethod('assignRank'))->toBeTrue();
        expect($reflection->hasMethod('calculateMetrics'))->toBeTrue();
        expect($reflection->hasMethod('clearCache'))->toBeTrue();
    });
});
