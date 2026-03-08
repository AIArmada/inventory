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
        Schema::create(config('inventory.table_names.levels', 'inventory_levels'), function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuidMorphs('inventoryable');
            $table->foreignUuid('location_id');
            $table->integer('quantity_on_hand')->default(0);
            $table->decimal('quantity_on_hand_decimal', 15, 4)->nullable();
            $table->integer('quantity_reserved')->default(0);
            $table->decimal('quantity_reserved_decimal', 15, 4)->nullable();
            $table->integer('reorder_point')->nullable();
            $table->integer('safety_stock')->nullable();
            $table->integer('max_stock')->nullable();
            $table->string('allocation_strategy')->nullable();
            $table->string('alert_status')->nullable();
            $table->timestamp('last_alert_at')->nullable();
            $table->timestamp('last_stock_check_at')->nullable();
            $table->string('unit_of_measure')->default('each');
            $table->decimal('unit_conversion_factor', 10, 4)->default(1);
            $table->unsignedInteger('lead_time_days')->nullable();
            $table->foreignUuid('preferred_supplier_id')->nullable();
            $table->nullableUuidMorphs('owner');
            $jsonType = config('inventory.database.json_column_type', config('inventory.json_column_type', 'json'));
            $table->{$jsonType}('metadata')->nullable();
            $table->timestamps();

            $table->unique(
                ['inventoryable_type', 'inventoryable_id', 'location_id'],
                'inventory_levels_inventoryable_location_unique'
            );
            $table->index('location_id');
            $table->index('quantity_on_hand');
            $table->index('reorder_point');
            $table->index('safety_stock');
            $table->index('max_stock');
            $table->index('alert_status');
            $table->index('last_alert_at');
            $table->index(['quantity_on_hand', 'safety_stock'], 'inventory_levels_stock_alert_idx');
            $table->index(['owner_type', 'owner_id'], 'inventory_levels_owner_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('inventory.table_names.levels', 'inventory_levels'));
    }
};
