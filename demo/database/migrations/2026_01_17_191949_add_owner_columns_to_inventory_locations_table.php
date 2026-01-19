<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tableName = config('inventory.table_names.locations', 'inventory_locations');

        if (Schema::hasColumn($tableName, 'owner_type')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table): void {
            $table->nullableUuidMorphs('owner');

            $table->index(['owner_type', 'owner_id'], 'inventory_locations_owner_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(config('inventory.table_names.locations', 'inventory_locations'), function (Blueprint $table): void {
            $table->dropIndex('inventory_locations_owner_idx');
            $table->dropMorphs('owner');
        });
    }
};
