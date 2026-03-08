<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Services;

use AIArmada\Inventory\Models\InventoryBatch;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Illuminate\Database\Eloquent\Collection;

final class ExpiryMonitorService
{
    public function __construct(
        private BatchService $batchService
    ) {}

    /**
     * Get all batches expiring within the given days.
     *
     * @return Collection<int, InventoryBatch>
     */
    public function getExpiringBatches(int $days = 30): Collection
    {
        return $this->batchService->getExpiringBatches($days);
    }

    /**
     * Get expiry summary grouped by expiry date.
     *
     * @return array<string, array{count: int, total_quantity: int, batches: Collection<int, InventoryBatch>}>
     */
    public function getExpirySummaryByDate(int $days = 90): array
    {
        $query = InventoryBatch::query()
            ->active()
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->addDays($days)])
            ->orderBy('expires_at');

        InventoryOwnerScope::applyToQueryByLocationRelation($query, 'location');

        $batches = $query->get();

        $summary = [];

        foreach ($batches as $batch) {
            $date = $batch->expires_at?->toDateString() ?? 'unknown';

            if (! isset($summary[$date])) {
                $summary[$date] = [
                    'count' => 0,
                    'total_quantity' => 0,
                    'batches' => new Collection,
                ];
            }

            $summary[$date]['count']++;
            $summary[$date]['total_quantity'] += $batch->quantity_on_hand;
            $summary[$date]['batches']->push($batch);
        }

        return $summary;
    }

    /**
     * Get expiry risk assessment.
     *
     * @return array{
     *     critical: Collection<int, InventoryBatch>,
     *     warning: Collection<int, InventoryBatch>,
     *     attention: Collection<int, InventoryBatch>,
     *     total_at_risk_value: int
     * }
     */
    public function getExpiryRiskAssessment(): array
    {
        $criticalQuery = InventoryBatch::query()
            ->active()
            ->expiringSoon(7);

        InventoryOwnerScope::applyToQueryByLocationRelation($criticalQuery, 'location');

        $critical = $criticalQuery->get();

        $warningQuery = InventoryBatch::query()
            ->active()
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now()->addDays(8), now()->addDays(30)]);

        InventoryOwnerScope::applyToQueryByLocationRelation($warningQuery, 'location');

        $warning = $warningQuery->get();

        $attentionQuery = InventoryBatch::query()
            ->active()
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now()->addDays(31), now()->addDays(90)]);

        InventoryOwnerScope::applyToQueryByLocationRelation($attentionQuery, 'location');

        $attention = $attentionQuery->get();

        $totalAtRisk = $critical->merge($warning)->sum(function (InventoryBatch $batch): int {
            return $batch->quantity_on_hand * ($batch->unit_cost_minor ?? 0);
        });

        return [
            'critical' => $critical,
            'warning' => $warning,
            'attention' => $attention,
            'total_at_risk_value' => $totalAtRisk,
        ];
    }

    /**
     * Get batches that will expire before they can be sold (based on sales velocity).
     *
     * @return Collection<int, InventoryBatch>
     */
    public function getSlowMovingExpiringBatches(int $averageDailySales = 1): Collection
    {
        $query = InventoryBatch::query()
            ->active()
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now());

        InventoryOwnerScope::applyToQueryByLocationRelation($query, 'location');

        return $query->get()
            ->filter(function (InventoryBatch $batch) use ($averageDailySales): bool {
                $daysUntilExpiry = $batch->days_until_expiry ?? 0;
                $quantityCanSell = $daysUntilExpiry * $averageDailySales;

                return $batch->quantity_on_hand > $quantityCanSell;
            });
    }

    /**
     * Process and mark expired batches.
     */
    public function processExpiredBatches(): int
    {
        return $this->batchService->processExpiredBatches();
    }

    /**
     * Get disposal candidates (expired or near-expired with no value).
     *
     * @return Collection<int, InventoryBatch>
     */
    public function getDisposalCandidates(): Collection
    {
        $query = InventoryBatch::query()
            ->where(function ($query): void {
                $query->expired()
                    ->orWhere(function ($q): void {
                        $q->expiringSoon(3)
                            ->where('quantity_on_hand', '>', 0);
                    });
            })
            ->where('quantity_reserved', 0);

        InventoryOwnerScope::applyToQueryByLocationRelation($query, 'location');

        return $query->get();
    }

    /**
     * Calculate potential write-off value.
     *
     * @return array{count: int, total_quantity: int, total_value_minor: int}
     */
    public function calculateWriteOffValue(): array
    {
        $disposalCandidates = $this->getDisposalCandidates();

        $totalValue = $disposalCandidates->sum(function (InventoryBatch $batch): int {
            return $batch->quantity_on_hand * ($batch->unit_cost_minor ?? 0);
        });

        return [
            'count' => $disposalCandidates->count(),
            'total_quantity' => $disposalCandidates->sum('quantity_on_hand'),
            'total_value_minor' => $totalValue,
        ];
    }

    /**
     * Generate expiry alert notifications.
     *
     * @return array<array{batch_id: string, batch_number: string, days_until_expiry: int|null, quantity: int, severity: string}>
     */
    public function generateExpiryAlerts(): array
    {
        $alerts = [];

        // Critical - expiring in 7 days
        $criticalQuery = InventoryBatch::query()->active()->expiringSoon(7);
        InventoryOwnerScope::applyToQueryByLocationRelation($criticalQuery, 'location');
        $critical = $criticalQuery->get();

        foreach ($critical as $batch) {
            $alerts[] = [
                'batch_id' => $batch->id,
                'batch_number' => $batch->batch_number,
                'days_until_expiry' => $batch->days_until_expiry,
                'quantity' => $batch->quantity_on_hand,
                'severity' => 'critical',
            ];
        }

        // Warning - expiring in 30 days
        $warningQuery = InventoryBatch::query()
            ->active()
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now()->addDays(8), now()->addDays(30)]);

        InventoryOwnerScope::applyToQueryByLocationRelation($warningQuery, 'location');

        $warning = $warningQuery->get();

        foreach ($warning as $batch) {
            $alerts[] = [
                'batch_id' => $batch->id,
                'batch_number' => $batch->batch_number,
                'days_until_expiry' => $batch->days_until_expiry,
                'quantity' => $batch->quantity_on_hand,
                'severity' => 'warning',
            ];
        }

        return $alerts;
    }
}
