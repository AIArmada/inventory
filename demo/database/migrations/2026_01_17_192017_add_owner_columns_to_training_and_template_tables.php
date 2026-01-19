<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addOwnerColumnsIfMissing(
            config('affiliates.database.tables.training_modules', 'affiliate_training_modules'),
            'affiliate_training_modules_owner_idx'
        );

        $this->addOwnerColumnsIfMissing(
            config('affiliates.database.tables.commission_templates', 'affiliate_commission_templates'),
            'affiliate_commission_templates_owner_idx'
        );
    }

    private function addOwnerColumnsIfMissing(string $tableName, string $indexName): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        $hasOwnerType = Schema::hasColumn($tableName, 'owner_type');
        $hasOwnerId = Schema::hasColumn($tableName, 'owner_id');

        if ($hasOwnerType && $hasOwnerId) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($hasOwnerType, $hasOwnerId): void {
            if (! $hasOwnerType) {
                $table->string('owner_type')->nullable()->index();
            }

            if (! $hasOwnerId) {
                $table->uuid('owner_id')->nullable()->index();
            }
        });

        if (! Schema::hasColumn($tableName, 'owner_type') || ! Schema::hasColumn($tableName, 'owner_id')) {
            return;
        }

        try {
            Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
                $table->index(['owner_type', 'owner_id'], $indexName);
            });
        } catch (\Throwable) {
            // Index may already exist.
        }
    }
};
