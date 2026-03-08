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
        Schema::create(config('inventory.table_names.locations', 'inventory_locations'), function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('line1')->nullable();
            $table->string('line2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postcode')->nullable();
            $table->string('country', 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(0);
            $table->foreignUuid('parent_id')->nullable();
            $table->string('path')->nullable();
            $table->unsignedInteger('depth')->default(0);
            $table->string('temperature_zone')->nullable();
            $table->boolean('is_hazmat_certified')->default(false);
            $table->decimal('coordinate_x', 10, 2)->nullable();
            $table->decimal('coordinate_y', 10, 2)->nullable();
            $table->decimal('coordinate_z', 10, 2)->nullable();
            $table->unsignedInteger('pick_sequence')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->unsignedInteger('current_utilization')->default(0);
            $table->nullableUuidMorphs('owner');
            $jsonType = config('inventory.database.json_column_type', config('inventory.json_column_type', 'json'));
            $table->{$jsonType}('metadata')->nullable();
            $table->timestamps();

            $table->index('is_active');
            $table->index('priority');
            $table->index('parent_id');
            $table->index('path');
            $table->index('depth');
            $table->index('temperature_zone');
            $table->index('pick_sequence');
            $table->index(['path', 'is_active'], 'inventory_locations_path_active_idx');
            $table->index(['temperature_zone', 'is_active'], 'inventory_locations_temp_active_idx');
            $table->index(['is_active', 'priority'], 'inventory_locations_active_priority_idx');
            $table->index(['owner_type', 'owner_id'], 'inventory_locations_owner_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('inventory.table_names.locations', 'inventory_locations'));
    }
};
