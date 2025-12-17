<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $databaseConfig = config('filament-cart.database', []);
        $tablePrefix = $databaseConfig['table_prefix'] ?? 'cart_';
        $tables = $databaseConfig['tables'] ?? [];
        $tableName = $tables['snapshots'] ?? $tablePrefix . 'snapshots';

        Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
            $table->string('owner_key', 191)->default('global')->after('instance');
            $table->nullableUuidMorphs('owner');

            // This table is addressed by owner + identifier + instance.
            $table->dropUnique(['identifier', 'instance']);
            $table->unique(['owner_key', 'identifier', 'instance'], $tableName . '_owner_key_identifier_instance_unique');

            $table->index('owner_key', $tableName . '_owner_key_index');
        });
    }

    public function down(): void
    {
        $databaseConfig = config('filament-cart.database', []);
        $tablePrefix = $databaseConfig['table_prefix'] ?? 'cart_';
        $tables = $databaseConfig['tables'] ?? [];
        $tableName = $tables['snapshots'] ?? $tablePrefix . 'snapshots';

        Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
            $table->dropUnique($tableName . '_owner_key_identifier_instance_unique');
            $table->unique(['identifier', 'instance']);

            $table->dropIndex($tableName . '_owner_key_index');
            $table->dropColumn('owner_key');
            $table->dropMorphs('owner');
        });
    }
};
