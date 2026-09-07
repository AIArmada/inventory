<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('inventory.database.tables.allocations', 'inventory_allocations');

        if (! Schema::hasTable($tableName)) {
            return;
        }

        if (! Schema::hasColumn($tableName, 'reservation_group_id')) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignUuid('reservation_group_id')->nullable();
            });
        }

        if (Schema::hasColumn($tableName, 'reservation_group_id')
            && ! Schema::hasIndex($tableName, 'inv_allocations_reservation_group_idx')) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->index('reservation_group_id', 'inv_allocations_reservation_group_idx');
            });
        }
    }
};
