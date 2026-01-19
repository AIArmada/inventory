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
        Schema::create(config('inventory.table_names.batches', 'inventory_batches'), function (Blueprint $table): void {
            $table->uuid('id')->primary();

            // The inventoryable item this batch is for
            $table->uuidMorphs('inventoryable');

            // Batch identification
            $table->string('batch_number')->index();
            $table->string('lot_number')->nullable()->index();
            $table->string('supplier_batch_number')->nullable();

            // Location
            $table->foreignUuid('location_id');

            // Quantities
            $table->integer('quantity_received');
            $table->integer('quantity_on_hand')->default(0);
            $table->integer('quantity_reserved')->default(0);

            // Dates
            $table->date('manufactured_at')->nullable();
            $table->date('received_at');
            $table->date('expires_at')->nullable()->index();

            // Status
            $table->string('status')->default('active');

            // Cost tracking per batch
            $table->unsignedBigInteger('unit_cost_minor')->nullable();
            $table->string('currency', 3)->default('USD');

            // Supplier reference
            $table->foreignUuid('supplier_id')->nullable();
            $table->string('purchase_order_number')->nullable();

            // Quality/Compliance
            $table->boolean('is_quarantined')->default(false);
            $table->string('quarantine_reason')->nullable();
            $table->timestamp('quality_checked_at')->nullable();
            $table->string('quality_status')->nullable();

            // Recall tracking
            $table->boolean('is_recalled')->default(false);
            $table->string('recall_reason')->nullable();
            $table->timestamp('recalled_at')->nullable();

            // Metadata
            $jsonType = config('inventory.database.json_column_type', config('inventory.json_column_type', 'json'));
            $table->{$jsonType}('metadata')->nullable();

            $table->nullableUuidMorphs('owner');

            $table->timestamps();

            // Unique constraint for batch within inventoryable and location
            $table->unique(
                ['inventoryable_type', 'inventoryable_id', 'location_id', 'batch_number'],
                'inventory_batches_unique'
            );

            // Indexes
            $table->index('location_id');
            $table->index('status');
            $table->index('is_quarantined');
            $table->index('is_recalled');
            $table->index(['expires_at', 'status'], 'inventory_batches_expiry_status_idx');
            $table->index(['inventoryable_type', 'inventoryable_id', 'status'], 'inventory_batches_item_status_idx');
            $table->index(['owner_type', 'owner_id'], 'inventory_batches_owner_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('inventory.table_names.batches', 'inventory_batches'));
    }
};
