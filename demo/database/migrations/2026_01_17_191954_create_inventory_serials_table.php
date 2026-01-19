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
        Schema::create(config('inventory.table_names.serials', 'inventory_serials'), function (Blueprint $table): void {
            $table->uuid('id')->primary();

            // The inventoryable item this serial belongs to
            $table->uuidMorphs('inventoryable');

            // Serial identification
            $table->string('serial_number')->unique();
            $table->string('sku')->nullable()->index();

            // Current location and batch
            $table->foreignUuid('location_id')->nullable();
            $table->foreignUuid('batch_id')->nullable();

            // Status tracking
            $table->string('status')->default('available');
            $table->string('condition')->default('new');

            // Cost
            $table->unsignedBigInteger('unit_cost_minor')->nullable();
            $table->string('currency', 3)->default('USD');

            // Warranty and support
            $table->date('warranty_expires_at')->nullable();
            $table->date('manufactured_at')->nullable();
            $table->date('received_at')->nullable();

            // Ownership/Assignment
            $table->nullableUuidMorphs('assigned_to');
            $table->timestamp('assigned_at')->nullable();

            // Sales tracking
            $table->foreignUuid('order_id')->nullable();
            $table->timestamp('sold_at')->nullable();
            $table->foreignUuid('customer_id')->nullable();

            // Supplier info
            $table->foreignUuid('supplier_id')->nullable();
            $table->string('purchase_order_number')->nullable();

            // Notes and metadata
            $table->text('notes')->nullable();
            $jsonType = config('inventory.database.json_column_type', config('inventory.json_column_type', 'json'));
            $table->{$jsonType}('metadata')->nullable();

            $table->nullableUuidMorphs('owner');

            $table->timestamps();

            // Indexes
            $table->index('location_id');
            $table->index('batch_id');
            $table->index('status');
            $table->index('condition');
            $table->index('warranty_expires_at');
            $table->index('order_id');
            $table->index('customer_id');
            $table->index(['inventoryable_type', 'inventoryable_id', 'status'], 'inventory_serials_item_status_idx');
            $table->index(['owner_type', 'owner_id'], 'inventory_serials_owner_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('inventory.table_names.serials', 'inventory_serials'));
    }
};
