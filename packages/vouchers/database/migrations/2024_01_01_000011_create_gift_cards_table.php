<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('vouchers.table_names.gift_cards', 'gift_cards');

        Schema::create($tableName, function (Blueprint $table): void {
            $table->uuid('id')->primary();

            // Identification
            $table->string('code', 32)->unique();
            $table->string('pin', 8)->nullable();

            // Type & Configuration
            $table->string('type')->default('standard');
            $table->string('currency', 3)->default('MYR');

            // Balance (stored in cents)
            $table->bigInteger('initial_balance');
            $table->bigInteger('current_balance');

            // Status
            $table->string('status')->default('inactive');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();

            // Ownership
            $table->nullableUuidMorphs('purchaser');
            $table->nullableUuidMorphs('recipient');

            // Multi-tenancy
            $table->nullableUuidMorphs('owner');

            // Metadata
            $jsonType = (string) commerce_json_column_type('vouchers', 'json');
            $table->{$jsonType}('metadata')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['status', 'expires_at'], 'gift_cards_active_lookup_idx');
            $table->index(['recipient_type', 'recipient_id'], 'gift_cards_recipient_idx');
            $table->index(['purchaser_type', 'purchaser_id'], 'gift_cards_purchaser_idx');
            $table->index('type');
        });

        // Create GIN indexes for PostgreSQL
        $jsonColumnType = commerce_json_column_type('vouchers', 'json');

        if (
            $jsonColumnType === 'jsonb'
            && Schema::getConnection()->getDriverName() === 'pgsql'
        ) {
            DB::statement("CREATE INDEX IF NOT EXISTS {$tableName}_metadata_gin_index ON \"{$tableName}\" USING GIN (\"metadata\")");
        }
    }

    public function down(): void
    {
        $tableName = config('vouchers.table_names.gift_cards', 'gift_cards');

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("DROP INDEX IF EXISTS {$tableName}_metadata_gin_index");
        }

        Schema::dropIfExists($tableName);
    }
};
