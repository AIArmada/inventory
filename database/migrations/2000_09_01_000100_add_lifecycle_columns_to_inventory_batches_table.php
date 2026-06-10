<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('inventory.database.tables.batches', 'inventory_batches');

        Schema::table($tableName, function (Blueprint $table): void {
            $table->timestampTz('quarantined_at')->nullable()->after('quarantine_reason');
        });

        DB::statement("UPDATE {$tableName} SET quarantined_at = updated_at WHERE is_quarantined = true");

        Schema::table($tableName, function (Blueprint $table): void {
            $table->dropIndex(['is_quarantined']);
            $table->dropIndex(['is_recalled']);
        });

        Schema::table($tableName, function (Blueprint $table): void {
            $table->dropColumn(['is_quarantined', 'is_recalled']);

            $table->index('quarantined_at');
        });
    }

    public function down(): void
    {
        // No down() required per monorepo guidelines
    }
};
