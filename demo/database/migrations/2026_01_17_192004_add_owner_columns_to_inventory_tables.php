<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'levels' => 'inventory_levels',
            'movements' => 'inventory_movements',
            'allocations' => 'inventory_allocations',
            'batches' => 'inventory_batches',
            'serials' => 'inventory_serials',
            'serial_history' => 'inventory_serial_history',
            'cost_layers' => 'inventory_cost_layers',
            'standard_costs' => 'inventory_standard_costs',
            'backorders' => 'inventory_backorders',
            'demand_history' => 'inventory_demand_history',
            'supplier_leadtimes' => 'inventory_supplier_leadtimes',
            'reorder_suggestions' => 'inventory_reorder_suggestions',
        ];

        foreach ($tables as $key => $defaultTableName) {
            $tableName = config('inventory.table_names.' . $key, $defaultTableName);
            $indexName = $tableName . '_owner_idx';

            if (Schema::hasColumn($tableName, 'owner_type') || Schema::hasColumn($tableName, 'owner_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
                $table->nullableUuidMorphs('owner');
                $table->index(['owner_type', 'owner_id'], $indexName);
            });
        }
    }

    public function down(): void
    {
        $tables = [
            'levels' => 'inventory_levels',
            'movements' => 'inventory_movements',
            'allocations' => 'inventory_allocations',
            'batches' => 'inventory_batches',
            'serials' => 'inventory_serials',
            'serial_history' => 'inventory_serial_history',
            'cost_layers' => 'inventory_cost_layers',
            'standard_costs' => 'inventory_standard_costs',
            'backorders' => 'inventory_backorders',
            'demand_history' => 'inventory_demand_history',
            'supplier_leadtimes' => 'inventory_supplier_leadtimes',
            'reorder_suggestions' => 'inventory_reorder_suggestions',
        ];

        foreach ($tables as $key => $defaultTableName) {
            $tableName = config('inventory.table_names.' . $key, $defaultTableName);
            $indexName = $tableName . '_owner_idx';

            if (! Schema::hasColumn($tableName, 'owner_type') || ! Schema::hasColumn($tableName, 'owner_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
                $table->dropIndex($indexName);
                $table->dropMorphs('owner');
            });
        }
    }
};
