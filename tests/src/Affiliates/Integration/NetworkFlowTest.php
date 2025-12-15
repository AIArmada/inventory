<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Events\AffiliateRankChanged;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateNetwork;
use AIArmada\Affiliates\Models\AffiliateRank;
use AIArmada\Affiliates\Services\NetworkService;
use AIArmada\Affiliates\Services\RankQualificationService;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    config(['affiliates.network.enabled' => true]);

    // Create rank hierarchy with proper schema fields
    $this->bronzeRank = AffiliateRank::create([
        'name' => 'Bronze',
        'slug' => 'bronze',
        'level' => 1,
        'min_personal_sales' => 0,
        'min_team_sales' => 0,
        'min_active_downlines' => 0,
        'commission_rate_basis_points' => 500,
    ]);

    $this->silverRank = AffiliateRank::create([
        'name' => 'Silver',
        'slug' => 'silver',
        'level' => 2,
        'min_personal_sales' => 10000,
        'min_team_sales' => 20000,
        'min_active_downlines' => 2,
        'commission_rate_basis_points' => 750,
    ]);

    $this->goldRank = AffiliateRank::create([
        'name' => 'Gold',
        'slug' => 'gold',
        'level' => 3,
        'min_personal_sales' => 25000,
        'min_team_sales' => 100000,
        'min_active_downlines' => 5,
        'commission_rate_basis_points' => 1000,
    ]);
});

test('affiliate can be added to network without a sponsor', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'ROOT-AFF',
        'name' => 'Root Affiliate',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);

    $networkService = app(NetworkService::class);
    $networkService->addToNetwork($affiliate);

    // Should have self-referencing entry
    expect(
        AffiliateNetwork::query()
            ->where('ancestor_id', $affiliate->id)
            ->where('descendant_id', $affiliate->id)
            ->where('depth', 0)
            ->exists()
    )->toBeTrue();
});

test('affiliate can be added to network with a sponsor', function (): void {
    $sponsor = Affiliate::create([
        'code' => 'SPONSOR-001',
        'name' => 'Sponsor',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);

    $networkService = app(NetworkService::class);
    $networkService->addToNetwork($sponsor);

    $recruit = Affiliate::create([
        'code' => 'RECRUIT-001',
        'name' => 'Recruit',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);

    $networkService->addToNetwork($recruit, $sponsor);

    // Recruit should have sponsor as ancestor at depth 1
    expect(
        AffiliateNetwork::query()
            ->where('ancestor_id', $sponsor->id)
            ->where('descendant_id', $recruit->id)
            ->where('depth', 1)
            ->exists()
    )->toBeTrue();

    // Sponsor's direct count should be updated
    $sponsor->refresh();
    expect($sponsor->direct_downline_count)->toBe(1);
});

test('multi-level network is correctly created', function (): void {
    $networkService = app(NetworkService::class);

    // Create 3-level network: Root -> Level1 -> Level2
    $root = Affiliate::create([
        'code' => 'ROOT',
        'name' => 'Root',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);
    $networkService->addToNetwork($root);

    $level1 = Affiliate::create([
        'code' => 'LEVEL1',
        'name' => 'Level 1',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);
    $networkService->addToNetwork($level1, $root);

    $level2 = Affiliate::create([
        'code' => 'LEVEL2',
        'name' => 'Level 2',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);
    $networkService->addToNetwork($level2, $level1);

    // Get upline for level2
    $upline = $networkService->getUpline($level2);
    expect($upline)->toHaveCount(2);
    expect($upline->pluck('id')->toArray())->toEqual([$level1->id, $root->id]);

    // Get downline for root (via closure table traversal)
    $downline = $networkService->getDownline($root);
    expect($downline)->toHaveCount(2);

    // Root has 1 direct (level1), counts don't propagate automatically through entire upline
    $root->refresh();
    expect($root->direct_downline_count)->toBe(1);
    // Network count reflects direct children only since we don't have recursive count update
});

test('team sales are correctly calculated across network', function (): void {
    $networkService = app(NetworkService::class);

    $root = Affiliate::create([
        'code' => 'LEADER',
        'name' => 'Leader',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);
    $networkService->addToNetwork($root);

    $member1 = Affiliate::create([
        'code' => 'MEMBER1',
        'name' => 'Member 1',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);
    $networkService->addToNetwork($member1, $root);

    $member2 = Affiliate::create([
        'code' => 'MEMBER2',
        'name' => 'Member 2',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);
    $networkService->addToNetwork($member2, $root);

    // Create conversions for team members
    AffiliateConversion::create([
        'affiliate_id' => $member1->id,
        'affiliate_code' => $member1->code,
        'order_reference' => 'ORDER-001',
        'subtotal_minor' => 5000,
        'total_minor' => 5000,
        'commission_minor' => 250,
        'status' => 'approved',
        'occurred_at' => now(),
    ]);

    AffiliateConversion::create([
        'affiliate_id' => $member2->id,
        'affiliate_code' => $member2->code,
        'order_reference' => 'ORDER-002',
        'subtotal_minor' => 7500,
        'total_minor' => 7500,
        'commission_minor' => 375,
        'status' => 'approved',
        'occurred_at' => now(),
    ]);

    $teamSales = $networkService->getTeamSales($root);
    expect($teamSales)->toBe(12500);
});

test('network tree is correctly built for visualization', function (): void {
    $networkService = app(NetworkService::class);

    $root = Affiliate::create([
        'code' => 'TREE-ROOT',
        'name' => 'Tree Root',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
        'rank_id' => $this->goldRank->id,
    ]);
    $networkService->addToNetwork($root);

    $child1 = Affiliate::create([
        'code' => 'CHILD-1',
        'name' => 'Child 1',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
        'rank_id' => $this->silverRank->id,
    ]);
    $networkService->addToNetwork($child1, $root);

    $tree = $networkService->buildTree($root);

    expect($tree)->toHaveKeys(['id', 'name', 'code', 'rank', 'status', 'stats', 'children']);
    expect($tree['code'])->toBe('TREE-ROOT');
    expect($tree['rank'])->toBe('Gold');
    expect($tree['children'])->toHaveCount(1);
    expect($tree['children'][0]['code'])->toBe('CHILD-1');
});

test('rank qualification evaluates correctly', function (): void {
    Event::fake([AffiliateRankChanged::class]);

    $networkService = app(NetworkService::class);
    $rankService = app(RankQualificationService::class);

    $affiliate = Affiliate::create([
        'code' => 'QUALIFY-TEST',
        'name' => 'Qualification Test',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
        'rank_id' => $this->bronzeRank->id,
    ]);
    $networkService->addToNetwork($affiliate);

    // Add recruits to meet silver requirements
    for ($i = 1; $i <= 3; $i++) {
        $recruit = Affiliate::create([
            'code' => "RECRUIT-{$i}",
            'name' => "Recruit {$i}",
            'status' => AffiliateStatus::Active,
            'commission_type' => 'percentage',
            'commission_rate' => 500,
            'currency' => 'USD',
        ]);
        $networkService->addToNetwork($recruit, $affiliate);

        // Add team volume via conversions
        AffiliateConversion::create([
            'affiliate_id' => $recruit->id,
            'affiliate_code' => $recruit->code,
            'order_reference' => "ORDER-{$i}",
            'subtotal_minor' => 10000,
            'total_minor' => 10000,
            'commission_minor' => 500,
            'status' => 'approved',
            'occurred_at' => now(),
        ]);
    }

    // Add personal sales
    AffiliateConversion::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'order_reference' => 'PERSONAL-001',
        'subtotal_minor' => 15000,
        'total_minor' => 15000,
        'commission_minor' => 750,
        'status' => 'approved',
        'occurred_at' => now(),
    ]);

    // Test calculateMetrics returns proper values
    $metrics = $rankService->calculateMetrics($affiliate);

    expect($metrics['active_downlines'])->toBe(3);
    expect($metrics['personal_sales'])->toBe(15000);
    expect($metrics['team_sales'])->toBe(30000);
});

test('rank upgrades are processed correctly', function (): void {
    Event::fake([AffiliateRankChanged::class]);

    $networkService = app(NetworkService::class);
    $rankService = app(RankQualificationService::class);

    $affiliate = Affiliate::create([
        'code' => 'UPGRADE-TEST',
        'name' => 'Upgrade Test',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
        'rank_id' => $this->bronzeRank->id,
    ]);
    $networkService->addToNetwork($affiliate);

    // Add enough recruits and volume to qualify for Silver
    for ($i = 1; $i <= 2; $i++) {
        $recruit = Affiliate::create([
            'code' => "UPGRADE-RECRUIT-{$i}",
            'name' => "Recruit {$i}",
            'status' => AffiliateStatus::Active,
            'commission_type' => 'percentage',
            'commission_rate' => 500,
            'currency' => 'USD',
        ]);
        $networkService->addToNetwork($recruit, $affiliate);

        AffiliateConversion::create([
            'affiliate_id' => $recruit->id,
            'affiliate_code' => $recruit->code,
            'order_reference' => "UPGRADE-ORDER-{$i}",
            'subtotal_minor' => 12000,
            'total_minor' => 12000,
            'commission_minor' => 600,
            'status' => 'approved',
            'occurred_at' => now(),
        ]);
    }

    // Personal sales to meet requirement
    AffiliateConversion::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'order_reference' => 'UPGRADE-PERSONAL',
        'subtotal_minor' => 12000,
        'total_minor' => 12000,
        'commission_minor' => 600,
        'status' => 'approved',
        'occurred_at' => now(),
    ]);

    $processedCount = $rankService->processAllRankUpgrades();

    expect($processedCount)->toBeGreaterThanOrEqual(1);

    $affiliate->refresh();
    expect($affiliate->rank_id)->toBe($this->silverRank->id);

    Event::assertDispatched(AffiliateRankChanged::class);
});

test('active downline count is correctly calculated', function (): void {
    $networkService = app(NetworkService::class);

    $root = Affiliate::create([
        'code' => 'ACTIVE-ROOT',
        'name' => 'Active Root',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);
    $networkService->addToNetwork($root);

    // Add mix of active and inactive downlines
    $active1 = Affiliate::create([
        'code' => 'ACTIVE-1',
        'name' => 'Active 1',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);
    $networkService->addToNetwork($active1, $root);

    $inactive = Affiliate::create([
        'code' => 'INACTIVE-1',
        'name' => 'Inactive 1',
        'status' => AffiliateStatus::Paused,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);
    $networkService->addToNetwork($inactive, $root);

    $active2 = Affiliate::create([
        'code' => 'ACTIVE-2',
        'name' => 'Active 2',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);
    $networkService->addToNetwork($active2, $root);

    $activeCount = $networkService->getActiveDownlineCount($root);
    expect($activeCount)->toBe(2);
});
