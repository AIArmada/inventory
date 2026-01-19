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
        $movementsTable = config('inventory.table_names.movements', 'inventory_movements');
        $allocationsTable = config('inventory.table_names.allocations', 'inventory_allocations');

        // Add batch_id to movements
        if (! Schema::hasColumn($movementsTable, 'batch_id')) {
            Schema::table($movementsTable, function (Blueprint $table): void {
                $table->foreignUuid('batch_id')->nullable()->after('to_location_id');
                $table->index('batch_id');
            });
        }

        // Add batch_id to allocations
        if (! Schema::hasColumn($allocationsTable, 'batch_id')) {
            Schema::table($allocationsTable, function (Blueprint $table): void {
                $table->foreignUuid('batch_id')->nullable()->after('level_id');
                $table->index('batch_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(config('inventory.table_names.movements', 'inventory_movements'), function (Blueprint $table): void {
            $table->dropIndex(['batch_id']);
            $table->dropColumn('batch_id');
        });

        Schema::table(config('inventory.table_names.allocations', 'inventory_allocations'), function (Blueprint $table): void {
            $table->dropIndex(['batch_id']);
            $table->dropColumn('batch_id');
        });
    }
};
