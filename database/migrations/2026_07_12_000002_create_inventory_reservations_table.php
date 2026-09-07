<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('inventory.database.tables.reservations', 'inventory_reservations');
        $jsonType = commerce_json_column_type('inventory', 'jsonb');

        commerce_schema_create_if_missing($tableName, function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->string('reference');
            $table->string('status')->default('reserved');
            $table->{$jsonType}('line_snapshot');
            $table->nullableMorphs('owner');
            $table->uuid('order_id')->nullable();
            $table->integer('ttl_seconds')->default(900);
            $table->timestampTz('expires_at')->nullable();
            $table->timestampsTz();

            $table->unique(['reference', 'owner_type', 'owner_id'], 'inventory_reservations_ref_owner_unique');
            $table->index('status');
            $table->index('expires_at');
        });
    }
};
