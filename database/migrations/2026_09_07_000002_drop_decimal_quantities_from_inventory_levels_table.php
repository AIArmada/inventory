<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('inventory.database.tables.levels', 'inventory_levels');

        if (! Schema::hasTable($tableName)) {
            return;
        }

        if (Schema::hasColumn($tableName, 'quantity_on_hand_decimal')) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('quantity_on_hand_decimal');
            });
        }

        if (Schema::hasColumn($tableName, 'quantity_reserved_decimal')) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('quantity_reserved_decimal');
            });
        }
    }
};
