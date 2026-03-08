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
        Schema::create(config('inventory.table_names.allocations', 'inventory_allocations'), function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuidMorphs('inventoryable');
            $table->foreignUuid('location_id');
            $table->foreignUuid('level_id');
            $table->foreignUuid('batch_id')->nullable();
            $table->string('cart_id');
            $table->integer('quantity');
            $table->timestamp('expires_at');
            $table->nullableUuidMorphs('owner');
            $table->timestamps();

            $table->index('cart_id');
            $table->index('expires_at');
            $table->index('location_id');
            $table->index('level_id');
            $table->index('batch_id');
            $table->index(['cart_id', 'expires_at'], 'inventory_allocations_cart_expiry_idx');
            $table->index(['inventoryable_type', 'inventoryable_id', 'cart_id'], 'inventory_allocations_inventoryable_cart_idx');
            $table->index(['owner_type', 'owner_id'], 'inventory_allocations_owner_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('inventory.table_names.allocations', 'inventory_allocations'));
    }
};
