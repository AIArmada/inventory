<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = config('jnt.database.tables', []);
        $prefix = config('jnt.database.table_prefix', 'jnt_');

        $orderItemsTable = $tables['order_items'] ?? $prefix . 'order_items';
        $orderParcelsTable = $tables['order_parcels'] ?? $prefix . 'order_parcels';
        $trackingEventsTable = $tables['tracking_events'] ?? $prefix . 'tracking_events';
        $webhookLogsTable = $tables['webhook_logs'] ?? $prefix . 'webhook_logs';

        foreach ([$orderItemsTable, $orderParcelsTable, $trackingEventsTable, $webhookLogsTable] as $tableName) {
            if (Schema::hasColumn($tableName, 'owner_type')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->nullableMorphs('owner');
            });
        }
    }

    public function down(): void
    {
        $tables = config('jnt.database.tables', []);
        $prefix = config('jnt.database.table_prefix', 'jnt_');

        $orderItemsTable = $tables['order_items'] ?? $prefix . 'order_items';
        $orderParcelsTable = $tables['order_parcels'] ?? $prefix . 'order_parcels';
        $trackingEventsTable = $tables['tracking_events'] ?? $prefix . 'tracking_events';
        $webhookLogsTable = $tables['webhook_logs'] ?? $prefix . 'webhook_logs';

        foreach ([$orderItemsTable, $orderParcelsTable, $trackingEventsTable, $webhookLogsTable] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropIndex(['owner_type', 'owner_id']);
                $table->dropMorphs('owner');
            });
        }
    }
};
