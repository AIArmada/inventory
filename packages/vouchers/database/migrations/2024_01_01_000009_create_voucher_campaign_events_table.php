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
        /** @var array<string, string> $tables */
        $tables = config('vouchers.database.tables', []);
        $prefix = (string) config('vouchers.database.table_prefix', '');
        $tableName = $tables['campaign_events'] ?? $prefix.'voucher_campaign_events';

        Schema::create($tableName, function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('campaign_id');
            $table->foreignUuid('variant_id')->nullable();

            // Event Details
            $table->string('event_type'); // impression, application, conversion, abandonment, removal
            $table->string('voucher_code')->nullable();

            // Context (polymorphic references without DB constraints)
            $table->nullableUuidMorphs('user');
            $table->nullableUuidMorphs('cart');
            $table->nullableUuidMorphs('order');

            // Attribution
            $table->string('channel')->nullable();
            $table->string('source')->nullable();
            $table->string('medium')->nullable();

            // Value (in cents)
            $table->bigInteger('value_cents')->nullable();
            $table->bigInteger('discount_cents')->nullable();

            // Additional metadata
            $jsonType = (string) commerce_json_column_type('vouchers', 'json');
            $table->{$jsonType}('metadata')->nullable();

            $table->timestamp('occurred_at');
            $table->timestamps();

            // Indexes for analytics queries
            $table->index(['campaign_id', 'event_type', 'occurred_at'], 'events_campaign_type_time_idx');
            $table->index(['variant_id', 'event_type'], 'events_variant_type_idx');
            $table->index('occurred_at');
            $table->index('event_type');
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
        /** @var array<string, string> $tables */
        $tables = config('vouchers.database.tables', []);
        $prefix = (string) config('vouchers.database.table_prefix', '');
        $tableName = $tables['campaign_events'] ?? $prefix.'voucher_campaign_events';

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("DROP INDEX IF EXISTS {$tableName}_metadata_gin_index");
        }

        Schema::dropIfExists($tableName);
    }
};
