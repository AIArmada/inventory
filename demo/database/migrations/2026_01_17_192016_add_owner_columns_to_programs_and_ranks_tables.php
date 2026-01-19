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
            config('affiliates.database.tables.programs', 'affiliate_programs'),
            'affiliate_programs_owner_idx'
        );

        $this->addOwnerColumnsIfMissing(
            config('affiliates.database.tables.ranks', 'affiliate_ranks'),
            'affiliate_ranks_owner_idx'
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

        try {
            Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
                $table->index(['owner_type', 'owner_id'], $indexName);
            });
        } catch (\Throwable) {
            // Index may already exist.
        }
    }
};
