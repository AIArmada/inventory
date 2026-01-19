<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $affiliatesTable = config('affiliates.database.tables.affiliates', 'affiliates');

        $this->addOwnerColumnsIfMissing(config('affiliates.database.tables.touchpoints', 'affiliate_touchpoints'));
        $this->addOwnerColumnsIfMissing(config('affiliates.database.tables.network', 'affiliate_network'));
        $this->addOwnerColumnsIfMissing(config('affiliates.database.tables.daily_stats', 'affiliate_daily_stats'));

        $this->backfillOwnerFromAffiliateId(
            table: config('affiliates.database.tables.touchpoints', 'affiliate_touchpoints'),
            affiliatesTable: $affiliatesTable,
            affiliateIdColumn: 'affiliate_id',
        );

        $this->backfillOwnerFromAffiliateId(
            table: config('affiliates.database.tables.daily_stats', 'affiliate_daily_stats'),
            affiliatesTable: $affiliatesTable,
            affiliateIdColumn: 'affiliate_id',
        );

        // Network rows do not have affiliate_id; use descendant_id as the canonical owner source.
        $this->backfillOwnerFromAffiliateId(
            table: config('affiliates.database.tables.network', 'affiliate_network'),
            affiliatesTable: $affiliatesTable,
            affiliateIdColumn: 'descendant_id',
        );
    }

    private function addOwnerColumnsIfMissing(string $tableName): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        $hasOwnerType = Schema::hasColumn($tableName, 'owner_type');
        $hasOwnerId = Schema::hasColumn($tableName, 'owner_id');

        if ($hasOwnerType && $hasOwnerId) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($hasOwnerType, $hasOwnerId): void {
            if (! $hasOwnerType) {
                $table->string('owner_type')->nullable()->index();
            }

            if (! $hasOwnerId) {
                $table->uuid('owner_id')->nullable()->index();
            }
        });

        if (! Schema::hasColumn($tableName, 'owner_type') || ! Schema::hasColumn($tableName, 'owner_id')) {
            return;
        }

        // Named index to match the base-create migrations.
        if (! Schema::hasColumn($tableName, 'id')) {
            return;
        }

        $indexName = match ($tableName) {
            config('affiliates.database.tables.touchpoints', 'affiliate_touchpoints') => 'affiliate_touchpoints_owner_idx',
            config('affiliates.database.tables.network', 'affiliate_network') => 'affiliate_network_owner_idx',
            config('affiliates.database.tables.daily_stats', 'affiliate_daily_stats') => 'affiliate_daily_stats_owner_idx',
            default => null,
        };

        if ($indexName === null) {
            return;
        }

        // Schema builder has no portable "hasIndex"; ignore if it already exists.
        try {
            Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
                $table->index(['owner_type', 'owner_id'], $indexName);
            });
        } catch (\Throwable) {
            // No-op.
        }
    }

    private function backfillOwnerFromAffiliateId(string $table, string $affiliatesTable, string $affiliateIdColumn): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasTable($affiliatesTable)) {
            return;
        }

        if (! Schema::hasColumn($table, 'owner_type') || ! Schema::hasColumn($table, 'owner_id')) {
            return;
        }

        if (! Schema::hasColumn($table, $affiliateIdColumn)) {
            return;
        }

        // Portable backfill: safe for SQLite test runs.
        $rows = DB::table($table)
            ->whereNull('owner_type')
            ->whereNull('owner_id')
            ->select(['id', $affiliateIdColumn])
            ->get();

        foreach ($rows as $row) {
            $affiliate = DB::table($affiliatesTable)
                ->where('id', $row->{$affiliateIdColumn})
                ->select(['owner_type', 'owner_id'])
                ->first();

            if (! $affiliate || ! $affiliate->owner_type || ! $affiliate->owner_id) {
                continue;
            }

            DB::table($table)
                ->where('id', $row->id)
                ->update([
                    'owner_type' => $affiliate->owner_type,
                    'owner_id' => $affiliate->owner_id,
                ]);
        }
    }
};
