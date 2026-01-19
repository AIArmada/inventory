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

        if (Schema::hasColumn($tableName, 'parent_id')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table): void {
            // Hierarchy columns
            $table->foreignUuid('parent_id')->nullable()->after('priority');
            $table->string('path')->nullable()->after('parent_id');
            $table->unsignedInteger('depth')->default(0)->after('path');

            // Temperature zone
            $table->string('temperature_zone')->nullable()->after('depth');
            $table->boolean('is_hazmat_certified')->default(false)->after('temperature_zone');

            // Coordinate/position columns for warehouse mapping
            $table->decimal('coordinate_x', 10, 2)->nullable()->after('is_hazmat_certified');
            $table->decimal('coordinate_y', 10, 2)->nullable()->after('coordinate_x');
            $table->decimal('coordinate_z', 10, 2)->nullable()->after('coordinate_y');
            $table->unsignedInteger('pick_sequence')->nullable()->after('coordinate_z');

            // Capacity
            $table->unsignedInteger('capacity')->nullable()->after('pick_sequence');
            $table->unsignedInteger('current_utilization')->default(0)->after('capacity');

            // Indexes for hierarchy
            $table->index('parent_id');
            $table->index('path');
            $table->index('depth');
            $table->index('temperature_zone');
            $table->index('pick_sequence');
            $table->index(['path', 'is_active'], 'inventory_locations_path_active_idx');
            $table->index(['temperature_zone', 'is_active'], 'inventory_locations_temp_active_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(config('inventory.table_names.locations', 'inventory_locations'), function (Blueprint $table): void {
            $table->dropIndex(['parent_id']);
            $table->dropIndex(['path']);
            $table->dropIndex(['depth']);
            $table->dropIndex(['temperature_zone']);
            $table->dropIndex(['pick_sequence']);
            $table->dropIndex('inventory_locations_path_active_idx');
            $table->dropIndex('inventory_locations_temp_active_idx');

            $table->dropColumn([
                'parent_id',
                'path',
                'depth',
                'temperature_zone',
                'is_hazmat_certified',
                'coordinate_x',
                'coordinate_y',
                'coordinate_z',
                'pick_sequence',
                'capacity',
                'current_utilization',
            ]);
        });
    }
};
