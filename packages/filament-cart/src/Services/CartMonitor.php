<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Services;

use AIArmada\FilamentCart\Data\LiveStats;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Real-time cart monitoring service.
 */
class CartMonitor
{
    private const STATUS_ACTIVE = 'active';

    private const STATUS_CHECKOUT = 'checkout';

    private const STATUS_ABANDONED = 'abandoned';

    /**
     * Get live statistics for the dashboard.
     */
    public function getLiveStats(): LiveStats
    {
        $snapshotsTable = $this->getSnapshotsTable();
        $alertLogsTable = $this->getAlertLogsTable();

        $abandonmentMinutes = (int) config('filament-cart.monitoring.abandonment_detection_minutes', 30);
        $highValueThreshold = (int) config('filament-cart.ai.high_value_threshold_cents', 10000);

        $abandonedBefore = now()->subMinutes($abandonmentMinutes);

        // Get cart counts
        $cartStats = DB::table($snapshotsTable)
            ->selectRaw('
                COUNT(*) as total_carts,
                SUM(CASE WHEN items_count > 0 THEN 1 ELSE 0 END) as with_items,
                SUM(CASE WHEN checkout_started_at IS NOT NULL AND checkout_abandoned_at IS NULL AND recovered_at IS NULL THEN 1 ELSE 0 END) as in_checkout,
                SUM(CASE WHEN items_count > 0 AND checkout_abandoned_at IS NULL AND recovered_at IS NULL AND COALESCE(last_activity_at, updated_at) < ? THEN 1 ELSE 0 END) as abandoned,
                SUM(total) as total_value,
                SUM(CASE WHEN total >= ? THEN 1 ELSE 0 END) as high_value
            ', [
                $abandonedBefore,
                $highValueThreshold,
            ])
            ->first();

        // Get pending alerts count
        $pendingAlerts = DB::table($alertLogsTable)
            ->where('is_read', false)
            ->count();

        // Get fraud signals (recent critical alerts)
        $fraudSignals = DB::table($alertLogsTable)
            ->where('event_type', 'fraud')
            ->where('is_read', false)
            ->where('created_at', '>=', now()->subHours(24))
            ->count();

        return new LiveStats(
            active_carts: (int) ($cartStats->total_carts ?? 0),
            carts_with_items: (int) ($cartStats->with_items ?? 0),
            checkouts_in_progress: (int) ($cartStats->in_checkout ?? 0),
            recent_abandonments: (int) ($cartStats->abandoned ?? 0),
            pending_alerts: $pendingAlerts,
            total_value_cents: (int) ($cartStats->total_value ?? 0),
            high_value_carts: (int) ($cartStats->high_value ?? 0),
            fraud_signals: $fraudSignals,
            updated_at: now(),
        );
    }

    /**
     * Get count of active carts.
     */
    public function getActiveCartsCount(): int
    {
        return DB::table($this->getSnapshotsTable())
            ->whereNull('checkout_abandoned_at')
            ->whereNull('recovered_at')
            ->count();
    }

    /**
     * Get recent abandonments within the specified minutes.
     *
     * @return Collection<int, object>
     */
    public function getRecentAbandonments(int $minutes = 30): Collection
    {
        return DB::table($this->getSnapshotsTable())
            ->where('items_count', '>', 0)
            ->whereNull('checkout_abandoned_at')
            ->whereNull('recovered_at')
            ->whereRaw('COALESCE(last_activity_at, updated_at) < ?', [now()->subMinutes($minutes)])
            ->where('updated_at', '>=', now()->subHours(24))
            ->orderByDesc('total')
            ->limit(50)
            ->get();
    }

    /**
     * Get high value carts above the threshold.
     *
     * @return Collection<int, object>
     */
    public function getHighValueCarts(?int $threshold = null): Collection
    {
        $threshold ??= (int) config('filament-cart.ai.high_value_threshold_cents', 10000);

        return DB::table($this->getSnapshotsTable())
            ->whereNull('checkout_abandoned_at')
            ->whereNull('recovered_at')
            ->where('total', '>=', $threshold)
            ->orderByDesc('total')
            ->limit(50)
            ->get();
    }

    /**
     * Detect abandonments that need attention.
     *
     * @return Collection<int, object>
     */
    public function detectAbandonments(): Collection
    {
        $abandonmentMinutes = (int) config('filament-cart.monitoring.abandonment_detection_minutes', 30);

        return DB::table($this->getSnapshotsTable())
            ->where('items_count', '>', 0)
            ->whereNull('checkout_abandoned_at')
            ->whereNull('recovered_at')
            ->whereRaw('COALESCE(last_activity_at, updated_at) < ?', [now()->subMinutes($abandonmentMinutes)])
            ->where('updated_at', '>=', now()->subHours(24))
            ->whereNotExists(function (Builder $query): void {
                $query->select(DB::raw(1))
                    ->from($this->getAlertLogsTable())
                    ->whereColumn('cart_id', $this->getSnapshotsTable() . '.id')
                    ->where('event_type', 'abandonment')
                    ->where('created_at', '>=', now()->subHours(24));
            })
            ->orderByDesc('total')
            ->get();
    }

    /**
     * Detect fraud signals based on cart patterns.
     *
     * @return Collection<int, object>
     */
    public function detectFraudSignals(): Collection
    {
        // Get carts with suspicious patterns
        // This is a simplified detection - real implementation would be more sophisticated
        return DB::table($this->getSnapshotsTable())
            ->whereNull('checkout_abandoned_at')
            ->whereNull('recovered_at')
            ->where(function ($query): void {
                $query
                    ->where(function ($query): void {
                        // High value + new session
                        $query->where('total', '>=', 50000)
                            ->where('created_at', '>=', now()->subMinutes(10));
                    })
                    ->orWhere(function ($query): void {
                        // Multiple high-quantity items
                        $query->where('items_count', '>=', 10)
                            ->where('total', '>=', 100000);
                    });
            })
            ->whereNotExists(function (Builder $query): void {
                $query->select(DB::raw(1))
                    ->from($this->getAlertLogsTable())
                    ->whereColumn('cart_id', $this->getSnapshotsTable() . '.id')
                    ->where('event_type', 'fraud')
                    ->where('created_at', '>=', now()->subHours(1));
            })
            ->orderByDesc('total')
            ->limit(20)
            ->get();
    }

    /**
     * Detect recovery opportunities.
     *
     * @return Collection<int, object>
     */
    public function detectRecoveryOpportunities(): Collection
    {
        // Carts that were abandoned but might be recoverable
        // (abandoned recently, has items, moderate to high value)
        return DB::table($this->getSnapshotsTable())
            ->where('items_count', '>', 0)
            ->whereNull('checkout_abandoned_at')
            ->whereNull('recovered_at')
            ->where('total', '>=', 2000) // $20+
            ->whereBetween(DB::raw('COALESCE(last_activity_at, updated_at)'), [
                now()->subHours(2),
                now()->subMinutes(30),
            ])
            ->orderByDesc('total')
            ->limit(50)
            ->get();
    }

    /**
     * Get recent cart activity feed.
     *
     * @return Collection<int, object>
     */
    public function getRecentActivity(int $limit = 20): Collection
    {
        return DB::table($this->getSnapshotsTable())
            ->selectRaw('
                id,
                identifier as session_id,
                CASE
                    WHEN checkout_abandoned_at IS NOT NULL THEN ?
                    WHEN checkout_started_at IS NOT NULL THEN ?
                    ELSE ?
                END as status,
                items_count,
                total as total_cents,
                updated_at
            ', [
                self::STATUS_ABANDONED,
                self::STATUS_CHECKOUT,
                self::STATUS_ACTIVE,
            ])
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();
    }

    private function getSnapshotsTable(): string
    {
        $tables = config('filament-cart.database.tables', []);
        $prefix = config('filament-cart.database.table_prefix', 'cart_');

        return $tables['snapshots'] ?? $prefix . 'snapshots';
    }

    private function getAlertLogsTable(): string
    {
        $tables = config('filament-cart.database.tables', []);
        $prefix = config('filament-cart.database.table_prefix', 'cart_');

        return $tables['alert_logs'] ?? $prefix . 'alert_logs';
    }
}
