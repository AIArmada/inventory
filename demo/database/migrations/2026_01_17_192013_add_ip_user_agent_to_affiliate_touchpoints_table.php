<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('affiliates.database.tables.touchpoints', 'affiliate_touchpoints');

        if (Schema::hasColumn($tableName, 'ip_address')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table): void {
            $table->string('ip_address', 45)->nullable()->after('content')->index();
            $table->string('user_agent', 512)->nullable()->after('ip_address');
        });
    }

    public function down(): void
    {
        $tableName = config('affiliates.database.tables.touchpoints', 'affiliate_touchpoints');

        Schema::table($tableName, function (Blueprint $table): void {
            $table->dropIndex(['ip_address']);
            $table->dropColumn(['ip_address', 'user_agent']);
        });
    }
};
