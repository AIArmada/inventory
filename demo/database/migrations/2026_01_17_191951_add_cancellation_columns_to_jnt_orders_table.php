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

        if (Schema::hasColumn($tableName, 'cancelled_at')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table): void {
            $table->timestamp('cancelled_at')->nullable()->index();
            $table->string('cancellation_reason', 255)->nullable();
        });
    }

    public function down(): void
    {
        $tables = config('jnt.database.tables', []);
        $prefix = config('jnt.database.table_prefix', 'jnt_');
        $tableName = $tables['orders'] ?? $prefix . 'orders';

        Schema::table($tableName, function (Blueprint $table): void {
            $table->dropIndex(['cancelled_at']);
            $table->dropColumn(['cancelled_at', 'cancellation_reason']);
        });
    }
};
