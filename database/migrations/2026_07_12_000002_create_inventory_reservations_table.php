<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('inventory.database.tables.reservations', 'inventory_reservations');
        $allocationTable = config('inventory.database.tables.allocations', 'inventory_allocations');
        $jsonType = commerce_json_column_type('inventory', 'jsonb');

        Schema::create($tableName, function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->string('reference');
            $table->string('state')->default('reserved');
            $table->{$jsonType}('line_snapshot');
            $table->nullableMorphs('owner');
            $table->uuid('order_id')->nullable();
            $table->integer('ttl_seconds')->default(900);
            $table->timestampTz('expires_at')->nullable();
            $table->timestampsTz();

            $table->unique(['reference', 'owner_type', 'owner_id'], 'inventory_reservations_ref_owner_unique');
            $table->index('state');
            $table->index('expires_at');
        });

        Schema::table($allocationTable, function (Blueprint $table): void {
            $table->foreignUuid('reservation_group_id')->nullable();
            $table->index('reservation_group_id', 'inv_allocations_reservation_group_idx');
        });
    }
};
