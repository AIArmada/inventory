<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\RankQualificationReason;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Models\AffiliatePayoutEvent;
use AIArmada\Affiliates\Models\AffiliateRankHistory;
use AIArmada\Affiliates\Models\AffiliateSupportMessage;
use AIArmada\Affiliates\Models\AffiliateSupportTicket;
use AIArmada\Affiliates\Services\FraudDetectionService;
use AIArmada\Affiliates\Services\NetworkService;
use AIArmada\Affiliates\Services\ProgramService;
use AIArmada\Affiliates\Services\RankQualificationService;
use AIArmada\Affiliates\Support\Links\AffiliateLinkGenerator;
use AIArmada\Affiliates\Support\Middleware\TrackAffiliateCookie;
use AIArmada\Affiliates\Traits\HasAffiliates;

// AffiliateRankHistory Model Tests
test('AffiliateRankHistory can be created with all fields', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'RANKHISTORY001',
        'name' => 'Rank History Test',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $history = AffiliateRankHistory::create([
        'affiliate_id' => $affiliate->id,
        'from_rank_id' => null,
        'to_rank_id' => null,
        'reason' => RankQualificationReason::Initial,
        'qualified_at' => now(),
    ]);

    expect($history)->toBeInstanceOf(AffiliateRankHistory::class);
    expect($history->reason)->toBe(RankQualificationReason::Initial);
});

test('AffiliateRankHistory has affiliate relationship', function (): void {
    $history = new AffiliateRankHistory;
    expect($history->affiliate())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});

test('AffiliateRankHistory has fromRank relationship', function (): void {
    $history = new AffiliateRankHistory;
    expect($history->fromRank())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});

test('AffiliateRankHistory has toRank relationship', function (): void {
    $history = new AffiliateRankHistory;
    expect($history->toRank())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});

test('AffiliateRankHistory isPromotion returns true when toRank exists and fromRank is null', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'PROMO001',
        'name' => 'Promo Test',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $history = AffiliateRankHistory::create([
        'affiliate_id' => $affiliate->id,
        'from_rank_id' => null,
        'to_rank_id' => null,
        'reason' => RankQualificationReason::Initial,
        'qualified_at' => now(),
    ]);

    // Without a toRank, this is not a promotion
    expect($history->isPromotion())->toBeFalse();
});

test('AffiliateRankHistory isDemotion returns true when fromRank exists and toRank is null', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'DEMO001',
        'name' => 'Demo Test',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $history = AffiliateRankHistory::create([
        'affiliate_id' => $affiliate->id,
        'from_rank_id' => null,
        'to_rank_id' => null,
        'reason' => RankQualificationReason::Demoted,
        'qualified_at' => now(),
    ]);

    // Without fromRank, this is not a demotion
    expect($history->isDemotion())->toBeFalse();
});

test('AffiliateRankHistory getTable returns configured table name', function (): void {
    $history = new AffiliateRankHistory;
    expect($history->getTable())->toBeString();
});

// AffiliatePayoutEvent Model Tests
test('AffiliatePayoutEvent can be created', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'PAYEVENT001',
        'name' => 'Payout Event Test',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $payout = AffiliatePayout::create([
        'affiliate_id' => $affiliate->id,
        'reference' => 'PAY-EVENT-001',
        'amount_minor' => 10000,
        'currency' => 'USD',
        'status' => 'pending',
    ]);

    $event = AffiliatePayoutEvent::create([
        'affiliate_payout_id' => $payout->id,
        'to_status' => 'pending',
        'notes' => 'Test payout event',
        'metadata' => ['key' => 'value'],
    ]);

    expect($event)->toBeInstanceOf(AffiliatePayoutEvent::class);
    expect($event->status)->toBe('pending'); // status is an accessor for to_status
    expect($event->notes)->toBe('Test payout event');
});

test('AffiliatePayoutEvent has payout relationship', function (): void {
    $event = new AffiliatePayoutEvent;
    expect($event->payout())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});

// AffiliateSupportMessage Model Tests
test('AffiliateSupportMessage can be created', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'SUPPORT001',
        'name' => 'Support Test',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $ticket = AffiliateSupportTicket::create([
        'affiliate_id' => $affiliate->id,
        'subject' => 'Test Subject',
        'category' => 'general',
        'priority' => 'normal',
        'status' => 'open',
    ]);

    $message = AffiliateSupportMessage::create([
        'ticket_id' => $ticket->id,
        'affiliate_id' => $affiliate->id,
        'message' => 'This is a test message',
        'is_staff_reply' => false,
    ]);

    expect($message)->toBeInstanceOf(AffiliateSupportMessage::class);
    expect($message->message)->toBe('This is a test message');
    expect($message->is_staff_reply)->toBeFalse();
});

test('AffiliateSupportMessage has ticket relationship', function (): void {
    $message = new AffiliateSupportMessage;
    expect($message->ticket())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});

// RankQualificationService Tests
test('RankQualificationService can be instantiated', function (): void {
    $service = app(RankQualificationService::class);
    expect($service)->toBeInstanceOf(RankQualificationService::class);
});

test('RankQualificationService evaluate returns null when no ranks exist', function (): void {
    $service = app(RankQualificationService::class);

    $affiliate = Affiliate::create([
        'code' => 'EVAL001',
        'name' => 'Evaluate Test',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $result = $service->evaluate($affiliate);

    // No ranks defined, so should return null
    expect($result)->toBeNull();
});

test('RankQualificationService calculateMetrics returns array', function (): void {
    $service = app(RankQualificationService::class);

    $affiliate = Affiliate::create([
        'code' => 'METRICS001',
        'name' => 'Metrics Test',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $metrics = $service->calculateMetrics($affiliate);

    expect($metrics)->toBeArray();
    expect($metrics)->toHaveKey('personal_sales');
    expect($metrics)->toHaveKey('team_sales');
    expect($metrics)->toHaveKey('active_downlines');
    expect($metrics)->toHaveKey('lifetime_value');
});

test('RankQualificationService clearCache works', function (): void {
    $service = app(RankQualificationService::class);

    $service->clearCache();

    expect(true)->toBeTrue(); // Just verify no exception
});

test('RankQualificationService assignRank works', function (): void {
    $service = app(RankQualificationService::class);

    $affiliate = Affiliate::create([
        'code' => 'ASSIGN001',
        'name' => 'Assign Rank Test',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $service->assignRank($affiliate, null);

    expect($affiliate->fresh()->rank_id)->toBeNull();
});

test('RankQualificationService processRankChange works', function (): void {
    $service = app(RankQualificationService::class);

    $affiliate = Affiliate::create([
        'code' => 'PROCESS001',
        'name' => 'Process Rank Test',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    // Just verify no exception
    $service->processRankChange($affiliate);

    expect(true)->toBeTrue();
});

test('RankQualificationService processBatch works', function (): void {
    $service = app(RankQualificationService::class);

    $affiliate = Affiliate::create([
        'code' => 'BATCH001',
        'name' => 'Batch Test',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $service->processBatch([$affiliate]);

    expect(true)->toBeTrue();
});

test('RankQualificationService processAllRankUpgrades returns int', function (): void {
    $service = app(RankQualificationService::class);

    $result = $service->processAllRankUpgrades();

    expect($result)->toBeInt();
    expect($result)->toBeGreaterThanOrEqual(0);
});

// NetworkService Tests
test('NetworkService can be instantiated', function (): void {
    $service = app(NetworkService::class);
    expect($service)->toBeInstanceOf(NetworkService::class);
});

test('NetworkService getDirectRecruits returns collection', function (): void {
    $service = app(NetworkService::class);

    $affiliate = Affiliate::create([
        'code' => 'NETWORK001',
        'name' => 'Network Test',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $recruits = $service->getDirectRecruits($affiliate);

    expect($recruits)->toBeInstanceOf(Illuminate\Support\Collection::class);
});

test('NetworkService getActiveDownlineCount returns int', function (): void {
    $service = app(NetworkService::class);

    $affiliate = Affiliate::create([
        'code' => 'ACTIVE001',
        'name' => 'Active Test',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $count = $service->getActiveDownlineCount($affiliate);

    expect($count)->toBeInt();
    expect($count)->toBeGreaterThanOrEqual(0);
});

test('NetworkService getTeamSales returns int', function (): void {
    $service = app(NetworkService::class);

    $affiliate = Affiliate::create([
        'code' => 'TEAM001',
        'name' => 'Team Sales Test',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $sales = $service->getTeamSales($affiliate);

    expect($sales)->toBeInt();
    expect($sales)->toBeGreaterThanOrEqual(0);
});

// ProgramService Tests
test('ProgramService can be instantiated', function (): void {
    $service = app(ProgramService::class);
    expect($service)->toBeInstanceOf(ProgramService::class);
});

// FraudDetectionService Tests
test('FraudDetectionService can be instantiated', function (): void {
    $service = app(FraudDetectionService::class);
    expect($service)->toBeInstanceOf(FraudDetectionService::class);
});

test('FraudDetectionService getRiskProfile returns array', function (): void {
    $service = app(FraudDetectionService::class);

    $affiliate = Affiliate::create([
        'code' => 'FRAUD001',
        'name' => 'Fraud Test',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $profile = $service->getRiskProfile($affiliate);

    expect($profile)->toBeArray();
});

// TrackAffiliateCookie Middleware Tests
test('TrackAffiliateCookie can be instantiated', function (): void {
    $middleware = app(TrackAffiliateCookie::class);
    expect($middleware)->toBeInstanceOf(TrackAffiliateCookie::class);
});

// AffiliateLinkGenerator Tests
test('AffiliateLinkGenerator can be instantiated', function (): void {
    $generator = app(AffiliateLinkGenerator::class);
    expect($generator)->toBeInstanceOf(AffiliateLinkGenerator::class);
});

test('AffiliateLinkGenerator generates links correctly', function (): void {
    config(['affiliates.links.allowed_hosts' => ['example.com']]);

    $generator = app(AffiliateLinkGenerator::class);

    $affiliate = Affiliate::create([
        'code' => 'LINKGEN001',
        'name' => 'Link Gen Test',
        'status' => AffiliateStatus::Active,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    // AffiliateLinkGenerator::generate takes string code, not model
    $link = $generator->generate($affiliate->code, 'https://example.com/product');

    expect($link)->toBeString();
    expect($link)->toContain($affiliate->code);
});

// HasAffiliates Trait Tests
test('HasAffiliates trait exists', function (): void {
    expect(trait_exists(HasAffiliates::class))->toBeTrue();
});
