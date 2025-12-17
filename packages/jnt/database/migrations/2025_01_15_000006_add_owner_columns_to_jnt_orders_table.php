<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = config('jnt.database.tables', []);
        $prefix = config('jnt.database.table_prefix', 'jnt_');
        $tableName = $tables['orders'] ?? $prefix . 'orders';

        Schema::table($tableName, function (Blueprint $table): void {
            // nullableMorphs already creates an index on owner_type and owner_id
            $table->nullableMorphs('owner');
        });
    }

    public function down(): void
    {
        $tables = config('jnt.database.tables', []);
        $prefix = config('jnt.database.table_prefix', 'jnt_');
        $tableName = $tables['orders'] ?? $prefix . 'orders';

        Schema::table($tableName, function (Blueprint $table): void {
            $table->dropIndex(['owner_type', 'owner_id']);
            $table->dropMorphs('owner');
        });
    }
};
