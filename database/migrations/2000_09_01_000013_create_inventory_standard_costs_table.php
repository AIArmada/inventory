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

        $tableName = $tables['standard_costs'] ?? $prefix . 'standard_costs';

        Schema::create($tableName, function (Blueprint $table) use ($tableName): void {
            $table->uuid('id')->primary();
            $table->uuidMorphs('inventoryable');
            $table->integer('standard_cost_minor');
            $table->string('currency', 3)->default('MYR');
            $table->timestamp('effective_from');
            $table->timestamp('effective_to')->nullable();
            $table->string('approved_by')->nullable();
            $table->text('notes')->nullable();
            $jsonType = config('inventory.database.json_column_type', commerce_json_column_type('inventory', 'json'));
            $table->addColumn($jsonType, 'metadata')->nullable();
            $table->nullableUuidMorphs('owner');
            $table->timestamps();

            $table->index(['inventoryable_type', 'inventoryable_id', 'effective_from']);
            $table->index('effective_from');
            $table->index('effective_to');
            $table->index(['owner_type', 'owner_id'], $tableName . '_owner_idx');
        });
    }

    public function down(): void
    {
        $tables = config('inventory.database.tables', []);
        $prefix = config('inventory.database.table_prefix', 'inventory_');
        $tableName = $tables['standard_costs'] ?? $prefix . 'standard_costs';

        Schema::dropIfExists($tableName);
    }
};
