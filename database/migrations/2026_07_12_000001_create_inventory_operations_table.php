<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('inventory.database.tables.operations', 'inventory_operations'), function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id');
            $table->string('kind');
            $table->string('status');
            $table->timestampTz('completed_at')->nullable();
            $table->nullableUuidMorphs('owner');
            $table->timestampsTz();

            $table->unique(['order_id', 'kind'], 'inventory_operations_order_kind_unique');
            $table->index('status');
            $table->index(['owner_type', 'owner_id'], 'inventory_operations_owner_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('inventory.database.tables.operations', 'inventory_operations'));
    }
};
