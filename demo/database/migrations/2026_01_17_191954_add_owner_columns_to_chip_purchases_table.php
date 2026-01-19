<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('chip.database.table_prefix', 'chip_') . 'purchases';

        if (Schema::hasColumn($tableName, 'owner_type')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table): void {
            // nullableMorphs already creates an index on owner_type, owner_id
            $table->nullableMorphs('owner');
        });
    }

    public function down(): void
    {
        $tableName = config('chip.database.table_prefix', 'chip_') . 'purchases';

        Schema::table($tableName, function (Blueprint $table): void {
            $table->dropMorphs('owner');
        });
    }
};
