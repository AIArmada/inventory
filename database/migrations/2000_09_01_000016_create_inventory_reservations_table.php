<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\ConnectionDriver;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('inventory.database.tables.reservations', 'inventory_reservations');
        $jsonType = commerce_json_column_type('inventory', 'jsonb');

        Schema::create($tableName, function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->string('reference');
            $table->string('status')->default('reserved');
            $table->{$jsonType}('line_snapshot');
            $table->nullableUuidMorphs('owner');
            $table->uuid('order_id')->nullable();
            $table->integer('ttl_seconds')->default(900);
            $table->timestampTz('expires_at')->nullable();
            $table->timestampsTz();

            $table->unique(['reference', 'owner_type', 'owner_id'], 'inventory_reservations_ref_owner_unique');
            $table->index('status');
            $table->index('expires_at');
        });

        // NULL owner tuples defeat the composite unique key (NULLs never
        // compare equal), so global references get their own partial unique
        // where the driver supports it. MySQL relies on the service-level
        // reservation lock plus unique-violation rescue instead.
        if (in_array(ConnectionDriver::name(Schema::getConnection()), ['pgsql', 'sqlite'], true)) {
            DB::statement(
                "CREATE UNIQUE INDEX inventory_reservations_ref_global_unique ON {$tableName} (reference) WHERE owner_type IS NULL AND owner_id IS NULL"
            );
        }
    }
};
