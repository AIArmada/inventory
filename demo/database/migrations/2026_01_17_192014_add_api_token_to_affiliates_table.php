<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('affiliates.database.tables.affiliates', 'affiliates');

        if (Schema::hasColumn($tableName, 'api_token')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table): void {
            $table->string('api_token', 64)->nullable()->unique();
        });
    }

    public function down(): void
    {
        $tableName = config('affiliates.database.tables.affiliates', 'affiliates');

        Schema::table($tableName, function (Blueprint $table): void {
            $table->dropUnique(['api_token']);
            $table->dropColumn('api_token');
        });
    }
};
