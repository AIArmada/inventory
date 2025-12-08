<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add owner columns to support multi-tenancy scoping regardless of runtime configuration.
     */
    public function up(): void
    {
        $tableName = config('cart.database.table', 'carts');

        if (Schema::hasColumn($tableName, 'owner_type')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table): void {
            $table->string('owner_type')->nullable()->after('identifier')->index();
            $table->string('owner_id')->nullable()->after('owner_type')->index();
        });

        // Update the unique constraint to include owner columns
        Schema::table($tableName, function (Blueprint $table): void {
            $table->dropUnique(['identifier', 'instance']);
            $table->unique(['owner_type', 'owner_id', 'identifier', 'instance']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('cart.database.table', 'carts');

        if (! Schema::hasColumn($tableName, 'owner_type')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table): void {
            $table->dropUnique(['owner_type', 'owner_id', 'identifier', 'instance']);
            $table->unique(['identifier', 'instance']);
            $table->dropColumn(['owner_type', 'owner_id']);
        });
    }
};
