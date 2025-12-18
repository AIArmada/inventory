<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = config('filament-cart.database.tables', []);
        $prefix = config('filament-cart.database.table_prefix', 'cart_');

        $alertRules = $tables['alert_rules'] ?? $prefix . 'alert_rules';
        $alertLogs = $tables['alert_logs'] ?? $prefix . 'alert_logs';

        Schema::table($alertRules, function (Blueprint $table): void {
            $table->nullableUuidMorphs('owner');
        });

        Schema::table($alertLogs, function (Blueprint $table): void {
            $table->nullableUuidMorphs('owner');
        });
    }

    public function down(): void
    {
        $tables = config('filament-cart.database.tables', []);
        $prefix = config('filament-cart.database.table_prefix', 'cart_');

        $alertRules = $tables['alert_rules'] ?? $prefix . 'alert_rules';
        $alertLogs = $tables['alert_logs'] ?? $prefix . 'alert_logs';

        Schema::table($alertLogs, function (Blueprint $table): void {
            $table->dropMorphs('owner');
        });

        Schema::table($alertRules, function (Blueprint $table): void {
            $table->dropMorphs('owner');
        });
    }
};
