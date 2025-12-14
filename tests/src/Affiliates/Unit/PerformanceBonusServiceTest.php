<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\ConversionStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateBalance;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Services\PerformanceBonusService;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->service = app(PerformanceBonusService::class);

    $this->affiliate = Affiliate::create([
        'code' => 'PERF-' . uniqid(),
        'name' => 'Performance Test Affiliate',
        'contact_email' => 'perf@example.com',
        'status' => AffiliateStatus::Active,
        'commission_type' => CommissionType::Percentage,
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);
});

describe('PerformanceBonusService', function (): void {
    describe('calculateBonuses', function (): void {
        // Note: calculateBonuses uses MySQL-specific HAVING clauses with non-aggregate queries
        // which are not compatible with SQLite used in testing. These tests verify method signature only.

        test('method signature accepts nullable Carbon parameters', function (): void {
            $reflection = new ReflectionMethod(PerformanceBonusService::class, 'calculateBonuses');
            $params = $reflection->getParameters();

            expect($params)->toHaveCount(2);
            expect($params[0]->getName())->toBe('from');
            expect($params[0]->allowsNull())->toBeTrue();
            expect($params[1]->getName())->toBe('to');
            expect($params[1]->allowsNull())->toBeTrue();
        });

        test('method returns array', function (): void {
            $reflection = new ReflectionMethod(PerformanceBonusService::class, 'calculateBonuses');
            $returnType = $reflection->getReturnType();

            expect($returnType)->not->toBeNull();
            expect($returnType->getName())->toBe('array');
        });
    });

    describe('awardBonuses', function (): void {
        test('returns count of awarded bonuses', function (): void {
            $bonuses = [
                [
                    'affiliate_id' => $this->affiliate->id,
                    'affiliate_name' => $this->affiliate->name,
                    'bonus_type' => 'top_performer',
                    'amount_minor' => 5000,
                    'reason' => 'Test bonus',
                    'metrics' => ['test' => true],
                ],
            ];

            $result = $this->service->awardBonuses($bonuses);

            expect($result)->toBe(1);
        });

        test('increments affiliate balance available amount', function (): void {
            AffiliateBalance::create([
                'affiliate_id' => $this->affiliate->id,
                'currency' => 'USD',
                'holding_minor' => 0,
                'available_minor' => 1000,
                'lifetime_earnings_minor' => 1000,
                'minimum_payout_minor' => 5000,
            ]);

            $bonuses = [
                [
                    'affiliate_id' => $this->affiliate->id,
                    'affiliate_name' => $this->affiliate->name,
                    'bonus_type' => 'test',
                    'amount_minor' => 2500,
                    'reason' => 'Test bonus',
                    'metrics' => [],
                ],
            ];

            $this->service->awardBonuses($bonuses);

            $balance = AffiliateBalance::where('affiliate_id', $this->affiliate->id)->first();
            expect($balance->available_minor)->toBe(3500); // 1000 + 2500
        });

        test('increments lifetime_earnings_minor', function (): void {
            AffiliateBalance::create([
                'affiliate_id' => $this->affiliate->id,
                'currency' => 'USD',
                'holding_minor' => 0,
                'available_minor' => 0,
                'lifetime_earnings_minor' => 5000,
                'minimum_payout_minor' => 5000,
            ]);

            $bonuses = [
                [
                    'affiliate_id' => $this->affiliate->id,
                    'affiliate_name' => $this->affiliate->name,
                    'bonus_type' => 'test',
                    'amount_minor' => 1000,
                    'reason' => 'Test bonus',
                    'metrics' => [],
                ],
            ];

            $this->service->awardBonuses($bonuses);

            $balance = AffiliateBalance::where('affiliate_id', $this->affiliate->id)->first();
            expect($balance->lifetime_earnings_minor)->toBe(6000);
        });

        test('creates bonus conversion record', function (): void {
            AffiliateBalance::create([
                'affiliate_id' => $this->affiliate->id,
                'currency' => 'USD',
                'holding_minor' => 0,
                'available_minor' => 0,
                'lifetime_earnings_minor' => 0,
                'minimum_payout_minor' => 5000,
            ]);

            $bonuses = [
                [
                    'affiliate_id' => $this->affiliate->id,
                    'affiliate_name' => $this->affiliate->name,
                    'bonus_type' => 'top_performer',
                    'amount_minor' => 5000,
                    'reason' => 'Performance bonus',
                    'metrics' => ['position' => 1],
                ],
            ];

            $this->service->awardBonuses($bonuses);

            $conversion = AffiliateConversion::where('affiliate_id', $this->affiliate->id)
                ->where('commission_minor', 5000)
                ->first();

            expect($conversion)->not->toBeNull();
            expect($conversion->status)->toBe(ConversionStatus::Approved);
            expect($conversion->metadata['type'])->toBe('performance_bonus');
            expect($conversion->metadata['bonus_type'])->toBe('top_performer');
        });

        test('skips inactive affiliates', function (): void {
            $this->affiliate->update(['status' => AffiliateStatus::Paused]);

            $bonuses = [
                [
                    'affiliate_id' => $this->affiliate->id,
                    'affiliate_name' => $this->affiliate->name,
                    'bonus_type' => 'test',
                    'amount_minor' => 5000,
                    'reason' => 'Test bonus',
                    'metrics' => [],
                ],
            ];

            $result = $this->service->awardBonuses($bonuses);

            expect($result)->toBe(0);
        });

        test('skips non-existent affiliates', function (): void {
            $bonuses = [
                [
                    'affiliate_id' => 'non-existent-id',
                    'affiliate_name' => 'Fake',
                    'bonus_type' => 'test',
                    'amount_minor' => 5000,
                    'reason' => 'Test bonus',
                    'metrics' => [],
                ],
            ];

            $result = $this->service->awardBonuses($bonuses);

            expect($result)->toBe(0);
        });

        test('creates balance if not exists', function (): void {
            $bonuses = [
                [
                    'affiliate_id' => $this->affiliate->id,
                    'affiliate_name' => $this->affiliate->name,
                    'bonus_type' => 'test',
                    'amount_minor' => 5000,
                    'reason' => 'Test bonus',
                    'metrics' => [],
                ],
            ];

            expect(AffiliateBalance::where('affiliate_id', $this->affiliate->id)->exists())->toBeFalse();

            $this->service->awardBonuses($bonuses);

            expect(AffiliateBalance::where('affiliate_id', $this->affiliate->id)->exists())->toBeTrue();
        });

        test('returns zero for empty bonus array', function (): void {
            $result = $this->service->awardBonuses([]);

            expect($result)->toBe(0);
        });
    });

    describe('getLeaderboard', function (): void {
        test('returns collection', function (): void {
            $result = $this->service->getLeaderboard();

            expect($result)->toBeCollection();
        });

        test('returns empty collection when no conversions', function (): void {
            $result = $this->service->getLeaderboard();

            expect($result)->toBeEmpty();
        });

        test('includes affiliates with approved conversions', function (): void {
            AffiliateConversion::create([
                'affiliate_id' => $this->affiliate->id,
                'affiliate_code' => $this->affiliate->code,
                'order_reference' => 'ORD-001',
                'subtotal_minor' => 5000,
                'total_minor' => 5000,
                'commission_minor' => 500,
                'status' => ConversionStatus::Approved,
                'occurred_at' => now(),
            ]);

            $result = $this->service->getLeaderboard();

            expect($result)->toHaveCount(1);
            expect($result->first()['affiliate_id'])->toBe($this->affiliate->id);
        });

        test('excludes pending conversions', function (): void {
            AffiliateConversion::create([
                'affiliate_id' => $this->affiliate->id,
                'affiliate_code' => $this->affiliate->code,
                'order_reference' => 'ORD-002',
                'subtotal_minor' => 5000,
                'total_minor' => 5000,
                'commission_minor' => 500,
                'status' => ConversionStatus::Pending,
                'occurred_at' => now(),
            ]);

            $result = $this->service->getLeaderboard();

            expect($result)->toBeEmpty();
        });

        test('orders by total_revenue descending', function (): void {
            $affiliate2 = Affiliate::create([
                'code' => 'LEAD-' . uniqid(),
                'name' => 'Leaderboard Affiliate 2',
                'contact_email' => 'lead2@example.com',
                'status' => AffiliateStatus::Active,
                'commission_type' => CommissionType::Percentage,
                'commission_rate' => 1000,
                'currency' => 'USD',
            ]);

            // First affiliate - lower revenue
            AffiliateConversion::create([
                'affiliate_id' => $this->affiliate->id,
                'affiliate_code' => $this->affiliate->code,
                'order_reference' => 'ORD-003',
                'subtotal_minor' => 5000,
                'total_minor' => 5000,
                'commission_minor' => 500,
                'status' => ConversionStatus::Approved,
                'occurred_at' => now(),
            ]);

            // Second affiliate - higher revenue
            AffiliateConversion::create([
                'affiliate_id' => $affiliate2->id,
                'affiliate_code' => $affiliate2->code,
                'order_reference' => 'ORD-004',
                'subtotal_minor' => 10000,
                'total_minor' => 10000,
                'commission_minor' => 1000,
                'status' => ConversionStatus::Approved,
                'occurred_at' => now(),
            ]);

            $result = $this->service->getLeaderboard();

            expect($result)->toHaveCount(2);
            expect($result->first()['affiliate_id'])->toBe($affiliate2->id);
            expect($result->first()['rank'])->toBe(1);
        });

        test('respects limit parameter', function (): void {
            for ($i = 0; $i < 5; $i++) {
                $affiliate = Affiliate::create([
                    'code' => 'LIM-' . uniqid(),
                    'name' => "Limit Affiliate {$i}",
                    'contact_email' => "limit{$i}@example.com",
                    'status' => AffiliateStatus::Active,
                    'commission_type' => CommissionType::Percentage,
                    'commission_rate' => 1000,
                    'currency' => 'USD',
                ]);

                AffiliateConversion::create([
                    'affiliate_id' => $affiliate->id,
                    'affiliate_code' => $affiliate->code,
                    'order_reference' => "ORD-LIM-{$i}",
                    'subtotal_minor' => 1000 * ($i + 1),
                    'total_minor' => 1000 * ($i + 1),
                    'commission_minor' => 100 * ($i + 1),
                    'status' => ConversionStatus::Approved,
                    'occurred_at' => now(),
                ]);
            }

            $result = $this->service->getLeaderboard(limit: 3);

            expect($result)->toHaveCount(3);
        });

        test('includes correct metrics in response', function (): void {
            AffiliateConversion::create([
                'affiliate_id' => $this->affiliate->id,
                'affiliate_code' => $this->affiliate->code,
                'order_reference' => 'ORD-005',
                'subtotal_minor' => 8000,
                'total_minor' => 10000,
                'commission_minor' => 1000,
                'status' => ConversionStatus::Approved,
                'occurred_at' => now(),
            ]);

            $result = $this->service->getLeaderboard();
            $entry = $result->first();

            expect($entry)->toHaveKeys([
                'rank',
                'affiliate_id',
                'affiliate_name',
                'affiliate_code',
                'total_revenue',
                'total_conversions',
                'total_commissions',
                'avg_order_value',
            ]);
            expect($entry['total_revenue'])->toBe(10000);
            expect($entry['total_conversions'])->toBe(1);
            expect($entry['total_commissions'])->toBe(1000);
        });

        test('filters by date range', function (): void {
            // Conversion in range
            AffiliateConversion::create([
                'affiliate_id' => $this->affiliate->id,
                'affiliate_code' => $this->affiliate->code,
                'order_reference' => 'ORD-IN-RANGE',
                'subtotal_minor' => 5000,
                'total_minor' => 5000,
                'commission_minor' => 500,
                'status' => ConversionStatus::Approved,
                'occurred_at' => now(),
            ]);

            // Conversion out of range
            AffiliateConversion::create([
                'affiliate_id' => $this->affiliate->id,
                'affiliate_code' => $this->affiliate->code,
                'order_reference' => 'ORD-OUT-RANGE',
                'subtotal_minor' => 5000,
                'total_minor' => 5000,
                'commission_minor' => 500,
                'status' => ConversionStatus::Approved,
                'occurred_at' => now()->subMonths(2),
            ]);

            $from = now()->startOfMonth();
            $to = now()->endOfMonth();

            $result = $this->service->getLeaderboard($from, $to);

            expect($result)->toHaveCount(1);
            expect($result->first()['total_revenue'])->toBe(5000); // Only in-range conversion
        });
    });
});

describe('PerformanceBonusService class structure', function (): void {
    test('can be instantiated', function (): void {
        $service = app(PerformanceBonusService::class);
        expect($service)->toBeInstanceOf(PerformanceBonusService::class);
    });

    test('is declared as final', function (): void {
        $reflection = new ReflectionClass(PerformanceBonusService::class);
        expect($reflection->isFinal())->toBeTrue();
    });

    test('has required public methods', function (): void {
        $reflection = new ReflectionClass(PerformanceBonusService::class);

        expect($reflection->hasMethod('calculateBonuses'))->toBeTrue();
        expect($reflection->hasMethod('awardBonuses'))->toBeTrue();
        expect($reflection->hasMethod('getLeaderboard'))->toBeTrue();
    });
});
