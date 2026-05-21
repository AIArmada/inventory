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

        $tableName = $tables['demand_history'] ?? $prefix . 'demand_history';

        Schema::create($tableName, function (Blueprint $table) use ($tableName): void {
            $table->uuid('id')->primary();
            $table->uuidMorphs('inventoryable');
            $table->foreignUuid('location_id')->nullable();
            $table->date('period_date');
            $table->string('period_type')->default('daily');
            $table->integer('quantity_demanded');
            $table->integer('quantity_fulfilled');
            $table->integer('quantity_lost')->default(0);
            $table->integer('order_count')->default(0);
            $jsonType = config('inventory.database.json_column_type', commerce_json_column_type('inventory', 'json'));
            $table->addColumn($jsonType, 'metadata')->nullable();
            $table->nullableUuidMorphs('owner');
            $table->timestamps();

            $table->unique(['inventoryable_type', 'inventoryable_id', 'location_id', 'period_date', 'period_type'], 'demand_unique');
            $table->index(['period_date', 'period_type']);
            $table->index(['owner_type', 'owner_id'], $tableName . '_owner_idx');
        });
    }

    public function down(): void
    {
        $tables = config('inventory.database.tables', []);
        $prefix = config('inventory.database.table_prefix', 'inventory_');
        $tableName = $tables['demand_history'] ?? $prefix . 'demand_history';

        Schema::dropIfExists($tableName);
    }
};
