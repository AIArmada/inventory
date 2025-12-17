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
        /** @var array<string, string> $tables */
        $tables = config('vouchers.database.tables', []);
        $prefix = (string) config('vouchers.database.table_prefix', '');
        $tableName = $tables['vouchers'] ?? $prefix.'vouchers';

        Schema::table($tableName, function (Blueprint $table): void {
            $jsonType = (string) commerce_json_column_type('vouchers', 'json');

            $table->{$jsonType}('stacking_rules')->nullable()->after('metadata');
            $table->{$jsonType}('exclusion_groups')->nullable()->after('stacking_rules');
            $table->integer('stacking_priority')->default(100)->after('exclusion_groups');

            $table->index('stacking_priority');
        });

        $jsonColumnType = commerce_json_column_type('vouchers', 'json');

        if (
            $jsonColumnType === 'jsonb'
            && Schema::getConnection()->getDriverName() === 'pgsql'
        ) {
            DB::statement("CREATE INDEX IF NOT EXISTS vouchers_stacking_rules_gin_index ON \"{$tableName}\" USING GIN (\"stacking_rules\")");
            DB::statement("CREATE INDEX IF NOT EXISTS vouchers_exclusion_groups_gin_index ON \"{$tableName}\" USING GIN (\"exclusion_groups\")");
        }
    }

    public function down(): void
    {
        /** @var array<string, string> $tables */
        $tables = config('vouchers.database.tables', []);
        $prefix = (string) config('vouchers.database.table_prefix', '');
        $tableName = $tables['vouchers'] ?? $prefix.'vouchers';

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS vouchers_stacking_rules_gin_index');
            DB::statement('DROP INDEX IF EXISTS vouchers_exclusion_groups_gin_index');
        }

        Schema::table($tableName, function (Blueprint $table): void {
            $table->dropIndex(['stacking_priority']);
            $table->dropColumn(['stacking_rules', 'exclusion_groups', 'stacking_priority']);
        });
    }
};
