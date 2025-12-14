<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Services;

use AIArmada\Affiliates\Enums\PayoutStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliatePayout;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Service for reconciling payouts with external payment systems.
 */
final class PayoutReconciliationService
{
    /**
     * Reconcile a payout with external system status.
     */
    public function reconcilePayout(AffiliatePayout $payout, string $externalStatus, array $externalData = []): bool
    {
        $newStatus = $this->mapExternalStatus($externalStatus);

        if ($newStatus === null || $newStatus->value === $payout->status->value) {
            return false;
        }

        DB::transaction(function () use ($payout, $newStatus, $externalData): void {
            $payout->update([
                'status' => $newStatus,
                'external_reference' => $externalData['reference'] ?? $payout->external_reference,
                'paid_at' => $newStatus === PayoutStatus::Completed ? now() : $payout->paid_at,
                'metadata' => array_merge($payout->metadata ?? [], [
                    'reconciled_at' => now()->toIso8601String(),
                    'external_data' => $externalData,
                ]),
            ]);

            // Create audit event
            $payout->events()->create([
                'to_status' => $newStatus->value,
                'notes' => 'Status updated via reconciliation',
                'metadata' => $externalData,
            ]);

            // If failed, return commission to balance
            if ($newStatus === PayoutStatus::Failed) {
                $this->returnCommissionToBalance($payout);
            }
        });

        return true;
    }

    /**
     * Get payouts needing reconciliation.
     */
    public function getPayoutsNeedingReconciliation(): Collection
    {
        return AffiliatePayout::query()
            ->whereIn('status', [PayoutStatus::Processing, PayoutStatus::Pending])
            ->whereNotNull('external_reference')
            ->where('updated_at', '<=', now()->subHours(1))
            ->get();
    }

    /**
     * Generate reconciliation report.
     */
    public function generateReport(?string $startDate = null, ?string $endDate = null): array
    {
        $query = AffiliatePayout::query();

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        $payouts = $query->get();

        $byStatus = $payouts->groupBy(fn ($p) => $p->status->value)->map->count();
        $totalAmount = $payouts->sum('amount_minor');
        $completedAmount = $payouts->where('status', PayoutStatus::Completed)->sum('amount_minor');
        $failedAmount = $payouts->where('status', PayoutStatus::Failed)->sum('amount_minor');

        return [
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'summary' => [
                'total_payouts' => $payouts->count(),
                'total_amount_minor' => $totalAmount,
                'completed_amount_minor' => $completedAmount,
                'failed_amount_minor' => $failedAmount,
                'pending_amount_minor' => $totalAmount - $completedAmount - $failedAmount,
            ],
            'by_status' => $byStatus->all(),
            'discrepancies' => $this->findDiscrepancies($payouts),
        ];
    }

    /**
     * Find balance discrepancies for an affiliate.
     */
    public function auditAffiliateBalance(Affiliate $affiliate): array
    {
        $balance = $affiliate->balance;

        // Calculate expected balance from conversions
        $approvedCommissions = $affiliate->conversions()
            ->where('status', 'approved')
            ->sum('commission_minor');

        $paidOut = $affiliate->payouts()
            ->where('status', PayoutStatus::Completed)
            ->sum('amount_minor');

        $pendingPayouts = $affiliate->payouts()
            ->whereIn('status', [PayoutStatus::Pending, PayoutStatus::Processing])
            ->sum('amount_minor');

        $expectedAvailable = $approvedCommissions - $paidOut - $pendingPayouts;
        $actualAvailable = $balance?->available_minor ?? 0;

        $discrepancy = $expectedAvailable - $actualAvailable;

        return [
            'affiliate_id' => $affiliate->id,
            'expected_available_minor' => $expectedAvailable,
            'actual_available_minor' => $actualAvailable,
            'discrepancy_minor' => $discrepancy,
            'has_discrepancy' => abs($discrepancy) > 0,
            'approved_commissions_minor' => $approvedCommissions,
            'paid_out_minor' => $paidOut,
            'pending_payouts_minor' => $pendingPayouts,
        ];
    }

    private function mapExternalStatus(string $status): ?PayoutStatus
    {
        return match (mb_strtolower($status)) {
            'completed', 'paid', 'success', 'succeeded' => PayoutStatus::Completed,
            'failed', 'declined', 'rejected', 'error' => PayoutStatus::Failed,
            'pending', 'created' => PayoutStatus::Pending,
            'processing', 'in_progress' => PayoutStatus::Processing,
            'cancelled', 'canceled' => PayoutStatus::Cancelled,
            default => null,
        };
    }

    private function returnCommissionToBalance(AffiliatePayout $payout): void
    {
        $affiliate = $payout->owner;
        $balance = $affiliate?->balance;

        if ($balance && $payout->amount_minor) {
            $balance->increment('available_minor', (int) $payout->amount_minor);
        }

        // Update linked conversions back to approved status
        $payout->conversions()->update([
            'status' => 'approved',
            'affiliate_payout_id' => null,
        ]);
    }

    private function findDiscrepancies(Collection $payouts): array
    {
        $discrepancies = [];

        foreach ($payouts as $payout) {
            $linkedAmount = $payout->conversions()->sum('commission_minor');

            if ($linkedAmount !== $payout->amount_minor) {
                $discrepancies[] = [
                    'payout_id' => $payout->id,
                    'payout_amount_minor' => $payout->amount_minor,
                    'linked_commissions_minor' => $linkedAmount,
                    'difference_minor' => $payout->amount_minor - $linkedAmount,
                ];
            }
        }

        return $discrepancies;
    }
}
