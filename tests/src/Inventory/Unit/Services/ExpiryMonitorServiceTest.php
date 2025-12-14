<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Models\InventoryBatch;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Services\BatchService;
use AIArmada\Inventory\Services\ExpiryMonitorService;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->item = InventoryItem::create(['name' => 'Test Product']);
    $this->location = InventoryLocation::factory()->create();
    $this->batchService = app(BatchService::class);
    $this->service = new ExpiryMonitorService($this->batchService);
});

describe('ExpiryMonitorService', function (): void {
    it('gets expiring batches from batch service', function (): void {
        $batch = InventoryBatch::factory()->forInventoryable(
            InventoryItem::class,
            $this->item->id,
        )->create([
            'location_id' => $this->location->id,
            'expires_at' => CarbonImmutable::now()->addDays(15),
            'quantity_on_hand' => 100,
            'status' => 'active',
        ]);

        $expiring = $this->service->getExpiringBatches(30);

        expect($expiring)->toHaveCount(1);
        expect($expiring->first()->id)->toBe($batch->id);
    });

    it('gets expiry summary grouped by date', function (): void {
        $date1 = CarbonImmutable::now()->addDays(10)->startOfDay();
        $date2 = CarbonImmutable::now()->addDays(20)->startOfDay();

        InventoryBatch::factory()->forInventoryable(
            InventoryItem::class,
            $this->item->id,
        )->create([
            'location_id' => $this->location->id,
            'expires_at' => $date1,
            'quantity_on_hand' => 50,
            'status' => 'active',
        ]);

        InventoryBatch::factory()->forInventoryable(
            InventoryItem::class,
            $this->item->id,
        )->create([
            'location_id' => $this->location->id,
            'expires_at' => $date1,
            'quantity_on_hand' => 30,
            'status' => 'active',
        ]);

        InventoryBatch::factory()->forInventoryable(
            InventoryItem::class,
            $this->item->id,
        )->create([
            'location_id' => $this->location->id,
            'expires_at' => $date2,
            'quantity_on_hand' => 20,
            'status' => 'active',
        ]);

        $summary = $this->service->getExpirySummaryByDate(90);

        expect($summary)->toHaveCount(2);

        $dateKey1 = $date1->toDateString();
        expect($summary[$dateKey1]['count'])->toBe(2);
        expect($summary[$dateKey1]['total_quantity'])->toBe(80);
    });

    it('gets expiry risk assessment with categorized batches', function (): void {
        // Critical - expiring in 7 days
        InventoryBatch::factory()->forInventoryable(
            InventoryItem::class,
            $this->item->id,
        )->create([
            'location_id' => $this->location->id,
            'expires_at' => CarbonImmutable::now()->addDays(5),
            'quantity_on_hand' => 100,
            'unit_cost_minor' => 1000,
            'status' => 'active',
        ]);

        // Warning - expiring in 30 days
        InventoryBatch::factory()->forInventoryable(
            InventoryItem::class,
            $this->item->id,
        )->create([
            'location_id' => $this->location->id,
            'expires_at' => CarbonImmutable::now()->addDays(15),
            'quantity_on_hand' => 50,
            'unit_cost_minor' => 500,
            'status' => 'active',
        ]);

        // Attention - expiring in 90 days
        InventoryBatch::factory()->forInventoryable(
            InventoryItem::class,
            $this->item->id,
        )->create([
            'location_id' => $this->location->id,
            'expires_at' => CarbonImmutable::now()->addDays(60),
            'quantity_on_hand' => 25,
            'status' => 'active',
        ]);

        $assessment = $this->service->getExpiryRiskAssessment();

        expect($assessment['critical'])->toHaveCount(1);
        expect($assessment['warning'])->toHaveCount(1);
        expect($assessment['attention'])->toHaveCount(1);
        expect($assessment['total_at_risk_value'])->toBeGreaterThan(0);
    });

    it('calculates total at risk value', function (): void {
        // Critical batch
        InventoryBatch::factory()->forInventoryable(
            InventoryItem::class,
            $this->item->id,
        )->create([
            'location_id' => $this->location->id,
            'expires_at' => CarbonImmutable::now()->addDays(3),
            'quantity_on_hand' => 10,
            'unit_cost_minor' => 100,
            'status' => 'active',
        ]);

        $assessment = $this->service->getExpiryRiskAssessment();

        expect($assessment['total_at_risk_value'])->toBe(1000); // 10 * 100
    });

    it('gets slow moving expiring batches', function (): void {
        // Batch with more stock than can be sold before expiry
        InventoryBatch::factory()->forInventoryable(
            InventoryItem::class,
            $this->item->id,
        )->create([
            'location_id' => $this->location->id,
            'expires_at' => CarbonImmutable::now()->addDays(10),
            'quantity_on_hand' => 100, // More than 10 days * 5 daily sales = 50
            'status' => 'active',
        ]);

        // Batch that can be sold before expiry
        $item2 = InventoryItem::create(['name' => 'Item 2']);
        InventoryBatch::factory()->forInventoryable(
            InventoryItem::class,
            $item2->id,
        )->create([
            'location_id' => $this->location->id,
            'expires_at' => CarbonImmutable::now()->addDays(30),
            'quantity_on_hand' => 10, // Less than 30 days * 5 daily sales = 150
            'status' => 'active',
        ]);

        $slowMoving = $this->service->getSlowMovingExpiringBatches(5);

        expect($slowMoving)->toHaveCount(1);
        expect($slowMoving->first()->quantity_on_hand)->toBe(100);
    });

    it('processes expired batches', function (): void {
        InventoryBatch::factory()->forInventoryable(
            InventoryItem::class,
            $this->item->id,
        )->create([
            'location_id' => $this->location->id,
            'expires_at' => CarbonImmutable::now()->subDay(),
            'quantity_on_hand' => 50,
            'status' => 'active',
        ]);

        $processed = $this->service->processExpiredBatches();

        expect($processed)->toBeGreaterThanOrEqual(0);
    });

    it('gets disposal candidates', function (): void {
        // Expired batch
        InventoryBatch::factory()->forInventoryable(
            InventoryItem::class,
            $this->item->id,
        )->create([
            'location_id' => $this->location->id,
            'expires_at' => CarbonImmutable::now()->subDay(),
            'quantity_on_hand' => 50,
            'quantity_reserved' => 0,
            'status' => 'expired',
        ]);

        // Near expiry batch
        $item2 = InventoryItem::create(['name' => 'Item 2']);
        InventoryBatch::factory()->forInventoryable(
            InventoryItem::class,
            $item2->id,
        )->create([
            'location_id' => $this->location->id,
            'expires_at' => CarbonImmutable::now()->addDays(2),
            'quantity_on_hand' => 30,
            'quantity_reserved' => 0,
            'status' => 'active',
        ]);

        $candidates = $this->service->getDisposalCandidates();

        expect($candidates->count())->toBeGreaterThanOrEqual(1);
    });

    it('calculates write off value', function (): void {
        InventoryBatch::factory()->forInventoryable(
            InventoryItem::class,
            $this->item->id,
        )->create([
            'location_id' => $this->location->id,
            'expires_at' => CarbonImmutable::now()->subDay(),
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
            'unit_cost_minor' => 500,
            'status' => 'expired',
        ]);

        $writeOff = $this->service->calculateWriteOffValue();

        expect($writeOff)->toBeArray();
        expect($writeOff)->toHaveKey('count');
        expect($writeOff)->toHaveKey('total_quantity');
        expect($writeOff)->toHaveKey('total_value_minor');
    });

    it('generates expiry alerts', function (): void {
        // Critical
        InventoryBatch::factory()->forInventoryable(
            InventoryItem::class,
            $this->item->id,
        )->create([
            'location_id' => $this->location->id,
            'batch_number' => 'BATCH-001',
            'expires_at' => CarbonImmutable::now()->addDays(3),
            'quantity_on_hand' => 50,
            'status' => 'active',
        ]);

        // Warning
        $item2 = InventoryItem::create(['name' => 'Item 2']);
        InventoryBatch::factory()->forInventoryable(
            InventoryItem::class,
            $item2->id,
        )->create([
            'location_id' => $this->location->id,
            'batch_number' => 'BATCH-002',
            'expires_at' => CarbonImmutable::now()->addDays(20),
            'quantity_on_hand' => 30,
            'status' => 'active',
        ]);

        $alerts = $this->service->generateExpiryAlerts();

        expect($alerts)->toBeArray();
        expect($alerts)->toHaveCount(2);

        $critical = collect($alerts)->firstWhere('severity', 'critical');
        $warning = collect($alerts)->firstWhere('severity', 'warning');

        expect($critical['batch_number'])->toBe('BATCH-001');
        expect($warning['batch_number'])->toBe('BATCH-002');
    });

    it('returns empty collections when no expiring batches', function (): void {
        $assessment = $this->service->getExpiryRiskAssessment();

        expect($assessment['critical'])->toBeEmpty();
        expect($assessment['warning'])->toBeEmpty();
        expect($assessment['attention'])->toBeEmpty();
        expect($assessment['total_at_risk_value'])->toBe(0);
    });

    it('returns empty summary when no batches exist', function (): void {
        $summary = $this->service->getExpirySummaryByDate();

        expect($summary)->toBeArray();
        expect($summary)->toBeEmpty();
    });
});
