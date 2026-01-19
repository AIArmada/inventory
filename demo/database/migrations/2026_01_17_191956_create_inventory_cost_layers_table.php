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

        $tableName = $tables['cost_layers'] ?? $prefix . 'cost_layers';

        Schema::create($tableName, function (Blueprint $table) use ($tableName): void {
            $table->uuid('id')->primary();
            $table->uuidMorphs('inventoryable');
            $table->foreignUuid('location_id')->nullable();
            $table->foreignUuid('batch_id')->nullable();
            $table->integer('quantity');
            $table->integer('remaining_quantity');
            $table->integer('unit_cost_minor');
            $table->integer('total_cost_minor');
            $table->string('currency', 3)->default('MYR');
            $table->string('reference')->nullable();
            $table->string('costing_method');
            $table->timestamp('layer_date');
            $table->nullableUuidMorphs('owner');
            $table->timestamps();

            $table->index(['inventoryable_type', 'inventoryable_id', 'layer_date']);
            $table->index('remaining_quantity');
            $table->index('costing_method');
            $table->index(['owner_type', 'owner_id'], $tableName . '_owner_idx');
        });
    }

    public function down(): void
    {
        $tables = config('inventory.database.tables', []);
        $prefix = config('inventory.database.table_prefix', 'inventory_');
        $tableName = $tables['cost_layers'] ?? $prefix . 'cost_layers';

        Schema::dropIfExists($tableName);
    }
};
