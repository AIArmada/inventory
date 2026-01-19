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
        Schema::create(config('inventory.table_names.movements', 'inventory_movements'), function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuidMorphs('inventoryable');
            $table->foreignUuid('from_location_id')->nullable();
            $table->foreignUuid('to_location_id')->nullable();
            $table->foreignUuid('batch_id')->nullable();
            $table->integer('quantity');
            $table->string('type');
            $table->string('reason')->nullable();
            $table->string('reference')->nullable();
            $table->foreignUuid('user_id')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->nullableUuidMorphs('owner');
            $table->timestamps();

            $table->index('type');
            $table->index('reason');
            $table->index('reference');
            $table->index('occurred_at');
            $table->index('from_location_id');
            $table->index('to_location_id');
            $table->index('batch_id');
            $table->index('user_id');
            $table->index(['inventoryable_type', 'inventoryable_id', 'type'], 'inventory_movements_inventoryable_type_idx');
            $table->index(['inventoryable_type', 'inventoryable_id', 'occurred_at'], 'inventory_movements_inventoryable_history_idx');
            $table->index(['owner_type', 'owner_id'], 'inventory_movements_owner_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('inventory.table_names.movements', 'inventory_movements'));
    }
};
