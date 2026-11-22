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
        $pools = config('inventory.database.tables.capacity_pools', 'inventory_capacity_pools');
        $holds = config('inventory.database.tables.capacity_holds', 'inventory_capacity_holds');

        Schema::create($pools, function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->nullableUuidMorphs('owner');
            $table->string('resource_key');
            $table->string('scope_key');
            $table->unsignedBigInteger('capacity');
            $table->unsignedBigInteger('reserved')->default(0);
            $table->unsignedBigInteger('committed')->default(0);
            $table->timestampsTz();

            $table->unique(['resource_key', 'scope_key', 'owner_type', 'owner_id'], 'inv_capacity_pool_identity_unique');
        });

        Schema::create($holds, function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('pool_id');
            $table->nullableUuidMorphs('owner');
            $table->string('reference');
            $table->unsignedBigInteger('quantity');
            $table->string('state')->default('held');
            $table->uuid('order_id')->nullable()->index();
            $table->timestampTz('expires_at');
            $table->timestampsTz();

            $table->unique(['pool_id', 'reference'], 'inv_capacity_hold_pool_ref_unique');
            $table->index(['state', 'expires_at']);
            $table->index(['reference', 'owner_type', 'owner_id'], 'inv_capacity_hold_ref_owner_index');
        });

        if (in_array(ConnectionDriver::name(Schema::getConnection()), ['pgsql', 'sqlite'], true)) {
            DB::statement("CREATE UNIQUE INDEX inv_capacity_pool_global_unique ON {$pools} (resource_key, scope_key) WHERE owner_type IS NULL AND owner_id IS NULL");
        }
    }
};
