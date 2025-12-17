<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = (string) config('chip.database.table_prefix', 'chip_');

        $tables = [
            'purchases',
            'payments',
            'clients',
            'bank_accounts',
            'webhooks',
            'send_instructions',
            'send_limits',
            'send_webhooks',
            'company_statements',
            'daily_metrics',
            'recurring_schedules',
            'recurring_charges',
        ];

        foreach ($tables as $suffix) {
            $tableName = $prefix . $suffix;

            if (! Schema::hasTable($tableName)) {
                continue;
            }

            if (Schema::hasColumn($tableName, 'owner_type') || Schema::hasColumn($tableName, 'owner_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->nullableMorphs('owner');
            });
        }
    }
};
