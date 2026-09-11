<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = (string) config('inventory.database.tables.movements', 'inventory_movements');

        if (! Schema::hasTable($tableName)) {
            return;
        }

        foreach (['from_location_id', 'to_location_id', 'occurred_at'] as $columnName) {
            if (Schema::hasColumn($tableName, $columnName)) {
                continue;
            }

            throw new RuntimeException(sprintf(
                'Inventory movement index migration cannot run because [%s] is missing column [%s].',
                $tableName,
                $columnName,
            ));
        }

        $columns = ['from_location_id', 'to_location_id', 'occurred_at'];
        $indexName = 'inventory_movements_location_history_index';

        if (Schema::hasIndex($tableName, $indexName)
            || Schema::hasIndex($tableName, $columns)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($columns, $indexName): void {
            $table->index($columns, $indexName);
        });
    }
};
