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

        $tableName = $tables['valuation_snapshots'] ?? $prefix . 'valuation_snapshots';

        Schema::create($tableName, function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('location_id')->nullable();
            $table->string('costing_method');
            $table->date('snapshot_date');
            $table->integer('total_quantity');
            $table->integer('total_value_minor');
            $table->integer('average_unit_cost_minor');
            $table->string('currency', 3)->default('MYR');
            $table->integer('sku_count');
            $table->integer('variance_from_previous_minor')->nullable();
            $jsonType = config('inventory.database.json_column_type', commerce_json_column_type('inventory', 'json'));
            $table->addColumn($jsonType, 'breakdown')->nullable();
            $table->addColumn($jsonType, 'metadata')->nullable();
            $table->nullableUuidMorphs('owner');
            $table->timestamps();

            $table->index(['snapshot_date', 'location_id']);
            $table->index('costing_method');
            $table->unique(['location_id', 'costing_method', 'snapshot_date']);
            $table->index(['owner_type', 'owner_id'], 'inventory_valuation_snapshots_owner_idx');
        });
    }

    public function down(): void
    {
        $tables = config('inventory.database.tables', []);
        $prefix = config('inventory.database.table_prefix', 'inventory_');
        $tableName = $tables['valuation_snapshots'] ?? $prefix . 'valuation_snapshots';

        Schema::dropIfExists($tableName);
    }
};
