<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $affiliatesTable = config('affiliates.database.tables.affiliates', 'affiliates');

        if (Schema::hasColumn($affiliatesTable, 'rank_id')) {
            return;
        }

        Schema::table($affiliatesTable, function (Blueprint $table): void {
            $table->foreignUuid('rank_id')->nullable()->after('parent_affiliate_id');
            $table->integer('network_depth')->default(0)->after('rank_id');
            $table->integer('direct_downline_count')->default(0)->after('network_depth');
            $table->integer('total_downline_count')->default(0)->after('direct_downline_count');
        });
    }

    public function down(): void
    {
        $affiliatesTable = config('affiliates.database.tables.affiliates', 'affiliates');

        Schema::table($affiliatesTable, function (Blueprint $table): void {
            $table->dropColumn(['rank_id', 'network_depth', 'direct_downline_count', 'total_downline_count']);
        });
    }
};
