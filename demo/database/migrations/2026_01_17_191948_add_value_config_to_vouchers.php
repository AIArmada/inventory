<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /** @var array<string, string> $tables */
        $tables = config('vouchers.database.tables', []);
        $prefix = (string) config('vouchers.database.table_prefix', '');
        $tableName = $tables['vouchers'] ?? $prefix . 'vouchers';
        $jsonColumnType = config('vouchers.database.json_column_type', 'json');

        if (Schema::hasColumn($tableName, 'value_config')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($jsonColumnType): void {
            // Compound voucher configuration (for BOGO, Tiered, Bundle, Cashback)
            $table->{$jsonColumnType}('value_config')->nullable()->after('value');

            // Cashback specific columns
            $table->string('credit_destination', 50)->nullable()->after('value_config');
            $table->integer('credit_delay_hours')->default(0)->after('credit_destination');
        });
    }

    public function down(): void
    {
        /** @var array<string, string> $tables */
        $tables = config('vouchers.database.tables', []);
        $prefix = (string) config('vouchers.database.table_prefix', '');
        $tableName = $tables['vouchers'] ?? $prefix . 'vouchers';

        Schema::table($tableName, function (Blueprint $table): void {
            $table->dropColumn(['value_config', 'credit_destination', 'credit_delay_hours']);
        });
    }
};
