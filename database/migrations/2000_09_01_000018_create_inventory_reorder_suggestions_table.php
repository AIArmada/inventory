<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = config('inventory.database.tables', []);
        $prefix = config('inventory.database.table_prefix', 'inventory_');

        $tableName = $tables['reorder_suggestions'] ?? $prefix . 'reorder_suggestions';

        Schema::create($tableName, function (Blueprint $table) use ($tableName): void {
            $table->uuid('id')->primary();
            $table->uuidMorphs('inventoryable');
            $table->foreignUuid('location_id')->nullable();
            $table->foreignUuid('supplier_leadtime_id')->nullable();
            $table->string('status')->default('pending');
            $table->integer('current_stock');
            $table->integer('reorder_point');
            $table->integer('suggested_quantity');
            $table->integer('economic_order_quantity')->nullable();
            $table->integer('average_daily_demand')->nullable();
            $table->integer('lead_time_days')->nullable();
            $table->date('expected_stockout_date')->nullable();
            $table->string('urgency')->default('normal');
            $table->string('trigger_reason');
            $table->foreignUuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignUuid('order_id')->nullable();
            $table->timestamp('ordered_at')->nullable();
            $jsonType = config('inventory.database.json_column_type', commerce_json_column_type('inventory', 'json'));
            $table->addColumn($jsonType, 'calculation_details')->nullable();
            $table->addColumn($jsonType, 'metadata')->nullable();
            $table->nullableUuidMorphs('owner');
            $table->timestamps();

            $table->index(['status', 'urgency']);
            $table->index(['inventoryable_type', 'inventoryable_id', 'status'], 'inv_reorder_invable_status_idx');
            $table->index('expected_stockout_date');
            $table->index(['owner_type', 'owner_id'], $tableName . '_owner_idx');
        });
    }

    public function down(): void
    {
        $tables = config('inventory.database.tables', []);
        $prefix = config('inventory.database.table_prefix', 'inventory_');
        $tableName = $tables['reorder_suggestions'] ?? $prefix . 'reorder_suggestions';

        Schema::dropIfExists($tableName);
    }
};
