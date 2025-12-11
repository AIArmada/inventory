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

        $tableName = $tables['supplier_leadtimes'] ?? $prefix . 'supplier_leadtimes';

        Schema::create($tableName, function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuidMorphs('inventoryable');
            $table->foreignUuid('supplier_id')->nullable();
            $table->string('supplier_name')->nullable();
            $table->integer('lead_time_days');
            $table->integer('lead_time_variance_days')->default(0);
            $table->integer('minimum_order_quantity')->default(1);
            $table->integer('order_multiple')->default(1);
            $table->integer('unit_cost_minor')->nullable();
            $table->string('currency', 3)->default('MYR');
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_order_at')->nullable();
            $table->timestamp('last_received_at')->nullable();
            $jsonType = config('inventory.database.json_column_type', 'json');
            $table->addColumn($jsonType, 'metadata')->nullable();
            $table->timestamps();

            $table->index(['inventoryable_type', 'inventoryable_id', 'is_active']);
            $table->index('is_primary');
        });
    }

    public function down(): void
    {
        $tables = config('inventory.database.tables', []);
        $prefix = config('inventory.database.table_prefix', 'inventory_');
        $tableName = $tables['supplier_leadtimes'] ?? $prefix . 'supplier_leadtimes';

        Schema::dropIfExists($tableName);
    }
};
