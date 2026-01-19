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
        /** @var array{tables?: array<string, string>} $databaseConfig */
        $databaseConfig = (array) config('orders.database', []);
        /** @var array<string, string> $tables */
        $tables = (array) ($databaseConfig['tables'] ?? []);

        $ordersTable = (string) ($tables['orders'] ?? 'orders');

        $related = [
            (string) ($tables['order_items'] ?? 'order_items'),
            (string) ($tables['order_addresses'] ?? 'order_addresses'),
            (string) ($tables['order_payments'] ?? 'order_payments'),
            (string) ($tables['order_refunds'] ?? 'order_refunds'),
            (string) ($tables['order_notes'] ?? 'order_notes'),
        ];

        foreach ($related as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            if (Schema::hasColumn($tableName, 'owner_type') && Schema::hasColumn($tableName, 'owner_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->nullableUuidMorphs('owner');
            });
        }

        $driver = (string) DB::connection()->getDriverName();

        // Best-effort backfill for existing data so child rows are not accidentally treated as global.
        if (! in_array($driver, ['mysql', 'pgsql'], true)) {
            return;
        }

        foreach ($related as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            if (! Schema::hasColumn($tableName, 'order_id')) {
                continue;
            }

            if ($driver === 'mysql') {
                DB::statement(
                    "UPDATE {$tableName} AS child \n" .
                    "JOIN {$ordersTable} AS parent ON parent.id = child.order_id \n" .
                    "SET child.owner_type = parent.owner_type, child.owner_id = parent.owner_id \n" .
                    'WHERE child.owner_type IS NULL AND child.owner_id IS NULL'
                );
            }

            if ($driver === 'pgsql') {
                DB::statement(
                    "UPDATE {$tableName} AS child \n" .
                    "SET owner_type = parent.owner_type, owner_id = parent.owner_id \n" .
                    "FROM {$ordersTable} AS parent \n" .
                    "WHERE parent.id = child.order_id \n" .
                    'AND child.owner_type IS NULL AND child.owner_id IS NULL'
                );
            }
        }
    }
};
