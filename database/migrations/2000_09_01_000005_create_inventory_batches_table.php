<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('inventory.database.tables.batches', 'inventory_batches'), function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('owner_scope')->default('global');

            $table->uuidMorphs('inventoryable');

            $table->string('batch_number')->index();
            $table->string('lot_number')->nullable()->index();
            $table->string('supplier_batch_number')->nullable();

            $table->foreignUuid('location_id');

            $table->integer('quantity_received');
            $table->integer('quantity_on_hand')->default(0);
            $table->integer('quantity_reserved')->default(0);

            $table->date('manufactured_at')->nullable();
            $table->date('received_at');
            $table->date('expires_at')->nullable()->index();

            $table->string('status')->default('active');

            $table->unsignedBigInteger('unit_cost_minor')->nullable();
            $table->string('currency', 3)->default('USD');

            $table->foreignUuid('supplier_id')->nullable();
            $table->string('purchase_order_number')->nullable();

            $table->string('quarantine_reason')->nullable();
            $table->timestampTz('quarantined_at')->nullable();
            $table->timestampTz('quality_checked_at')->nullable();
            $table->string('quality_status')->nullable();

            $table->string('recall_reason')->nullable();
            $table->timestampTz('recalled_at')->nullable();

            $jsonType = commerce_json_column_type('inventory', 'jsonb');
            $table->{$jsonType}('metadata')->nullable();

            $table->nullableUuidMorphs('owner');

            $table->timestampsTz();

            $table->unique(
                ['owner_scope', 'inventoryable_type', 'inventoryable_id', 'location_id', 'batch_number'],
                'inventory_batches_owner_scope_unique'
            );

            $table->index('location_id');
            $table->index('status');
            $table->index('quarantined_at');
            $table->index(['expires_at', 'status'], 'inventory_batches_expiry_status_idx');
            $table->index(['inventoryable_type', 'inventoryable_id', 'status'], 'inventory_batches_item_status_idx');
            $table->index(['owner_type', 'owner_id'], 'inventory_batches_owner_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('inventory.database.tables.batches', 'inventory_batches'));
    }
};
