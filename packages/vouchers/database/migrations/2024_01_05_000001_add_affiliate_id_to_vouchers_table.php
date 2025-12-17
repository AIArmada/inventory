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
        $tableName = $tables['vouchers'] ?? $prefix.'vouchers';

        Schema::table($tableName, function (Blueprint $table): void {
            $table->foreignUuid('affiliate_id')->nullable()->after('campaign_variant_id');
            $table->index('affiliate_id');
        });
    }

    public function down(): void
    {
        /** @var array<string, string> $tables */
        $tables = config('vouchers.database.tables', []);
        $prefix = (string) config('vouchers.database.table_prefix', '');
        $tableName = $tables['vouchers'] ?? $prefix.'vouchers';

        Schema::table($tableName, function (Blueprint $table): void {
            $table->dropIndex(['affiliate_id']);
            $table->dropColumn('affiliate_id');
        });
    }
};
