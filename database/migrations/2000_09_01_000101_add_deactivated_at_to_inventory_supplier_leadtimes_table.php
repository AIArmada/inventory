<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = config('inventory.database.tables', []);
        $prefix = config('inventory.database.table_prefix', 'inventory_');
        $tableName = $tables['supplier_leadtimes'] ?? $prefix . 'supplier_leadtimes';

        Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
            $table->timestampTz('deactivated_at')->nullable()->after('is_active');

            $table->index('deactivated_at', $tableName . '_deactivated_at_idx');
        });
    }

    public function down(): void
    {
        // No down() required per monorepo guidelines
    }
};
