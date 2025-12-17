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
            $table->foreignUuid('campaign_id')->nullable()->after('metadata');
            $table->foreignUuid('campaign_variant_id')->nullable()->after('campaign_id');

            $table->index('campaign_id');
            $table->index('campaign_variant_id');
        });
    }

    public function down(): void
    {
        /** @var array<string, string> $tables */
        $tables = config('vouchers.database.tables', []);
        $prefix = (string) config('vouchers.database.table_prefix', '');
        $tableName = $tables['vouchers'] ?? $prefix.'vouchers';

        Schema::table($tableName, function (Blueprint $table): void {
            $table->dropIndex(['campaign_id']);
            $table->dropIndex(['campaign_variant_id']);
            $table->dropColumn(['campaign_id', 'campaign_variant_id']);
        });
    }
};
