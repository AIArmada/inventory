<?php

declare(strict_types=1);

use AIArmada\Affiliates\Services\AffiliateReportService;
use AIArmada\FilamentAffiliates\Pages\ReportsPage;
use Carbon\CarbonImmutable;

it('generates a report for predefined periods', function (): void {
    $captured = ['start' => null, 'end' => null];

    app()->instance(AffiliateReportService::class, new class($captured)
    {
        /** @var array{start:mixed, end:mixed} */
        public array $captured;

        public function __construct(array &$captured)
        {
            $this->captured = &$captured;
        }

        public function getSummary($startDate, $endDate): array
        {
            $this->captured['start'] = $startDate;
            $this->captured['end'] = $endDate;

            return ['ok' => true];
        }

        public function getTopAffiliates($startDate, $endDate, int $limit): array
        {
            return [['limit' => $limit]];
        }

        public function getConversionTrend($startDate, $endDate): array
        {
            return [['date' => '2025-01-01', 'count' => 1]];
        }

        public function getTrafficSources($startDate, $endDate): array
        {
            return [['source' => 'direct', 'count' => 1]];
        }
    });

    $page = new ReportsPage;

    $page->period = 'week';
    $page->generateReport();

    $page->period = 'month';
    $page->generateReport();

    $page->period = 'quarter';
    $page->generateReport();

    $page->period = 'year';
    $page->generateReport();

    $page->period = 'unknown';
    $page->generateReport();

    expect($page->reportData)->toHaveKeys(['summary', 'top_affiliates', 'conversion_trend', 'traffic_sources'])
        ->and($page->reportData['summary']['ok'])->toBeTrue()
        ->and($page->reportData['top_affiliates'][0]['limit'])->toBe(10)
        ->and($captured['start'])->not->toBeNull()
        ->and($captured['end'])->not->toBeNull();
});

it('generates a report for a custom range with parsed dates', function (): void {
    $captured = ['start' => null, 'end' => null];

    app()->instance(AffiliateReportService::class, new class($captured)
    {
        /** @var array{start:mixed, end:mixed} */
        public array $captured;

        public function __construct(array &$captured)
        {
            $this->captured = &$captured;
        }

        public function getSummary($startDate, $endDate): array
        {
            $this->captured['start'] = $startDate;
            $this->captured['end'] = $endDate;

            return ['range' => [$startDate->toDateString(), $endDate->toDateString()]];
        }

        public function getTopAffiliates($startDate, $endDate, int $limit): array
        {
            return [];
        }

        public function getConversionTrend($startDate, $endDate): array
        {
            return [];
        }

        public function getTrafficSources($startDate, $endDate): array
        {
            return [];
        }
    });

    $page = new ReportsPage;

    $page->period = 'custom';
    $page->startDate = CarbonImmutable::parse('2025-01-10')->toDateString();
    $page->endDate = CarbonImmutable::parse('2025-01-20')->toDateString();

    $page->generateReport();

    expect($captured['start'])->not->toBeNull()
        ->and($captured['end'])->not->toBeNull();

    /** @var \Carbon\CarbonInterface $start */
    $start = $captured['start'];
    /** @var \Carbon\CarbonInterface $end */
    $end = $captured['end'];

    expect($start->toDateString())->toBe('2025-01-10')
        ->and($end->toDateString())->toBe('2025-01-20')
        ->and($page->getViewData())->toBe(['reportData' => $page->reportData]);
});

it('generates a report for a custom range with missing dates using sane defaults', function (): void {
    $captured = ['start' => null, 'end' => null];

    app()->instance(AffiliateReportService::class, new class($captured)
    {
        /** @var array{start:mixed, end:mixed} */
        public array $captured;

        public function __construct(array &$captured)
        {
            $this->captured = &$captured;
        }

        public function getSummary($startDate, $endDate): array
        {
            $this->captured['start'] = $startDate;
            $this->captured['end'] = $endDate;

            return [];
        }

        public function getTopAffiliates($startDate, $endDate, int $limit): array
        {
            return [];
        }

        public function getConversionTrend($startDate, $endDate): array
        {
            return [];
        }

        public function getTrafficSources($startDate, $endDate): array
        {
            return [];
        }
    });

    $page = new ReportsPage;
    $page->period = 'custom';
    $page->startDate = null;
    $page->endDate = null;

    $page->generateReport();

    expect($captured['start'])->not->toBeNull()
        ->and($captured['end'])->not->toBeNull();
});
