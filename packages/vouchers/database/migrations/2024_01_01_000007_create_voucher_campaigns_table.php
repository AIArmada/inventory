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
        $tableName = config('vouchers.table_names.campaigns', 'voucher_campaigns');

        Schema::create($tableName, function (Blueprint $table): void {
            $table->uuid('id')->primary();

            // Identity
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            // Type & Objective
            $table->string('type')->default('promotional');
            $table->string('objective')->default('revenue_increase');

            // Budget & Limits
            $table->bigInteger('budget_cents')->nullable();
            $table->bigInteger('spent_cents')->default(0);
            $table->integer('max_redemptions')->nullable();
            $table->integer('current_redemptions')->default(0);

            // Schedule
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('timezone')->default('UTC');

            // A/B Testing
            $table->boolean('ab_testing_enabled')->default(false);
            $table->string('ab_winner_variant')->nullable();
            $table->timestamp('ab_winner_declared_at')->nullable();

            // Status
            $table->string('status')->default('draft');

            // Multi-tenancy
            $table->nullableUuidMorphs('owner');

            // Analytics & Automation
            $jsonType = (string) commerce_json_column_type('vouchers', 'json');
            $table->{$jsonType}('metrics')->nullable();
            $table->{$jsonType}('automation_rules')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['status', 'starts_at', 'ends_at'], 'campaigns_active_lookup_idx');
            $table->index('type');
            $table->index('objective');
        });

        // Create GIN indexes for PostgreSQL
        $jsonColumnType = commerce_json_column_type('vouchers', 'json');

        if (
            $jsonColumnType === 'jsonb'
            && Schema::getConnection()->getDriverName() === 'pgsql'
        ) {
            DB::statement("CREATE INDEX IF NOT EXISTS {$tableName}_metrics_gin_index ON \"{$tableName}\" USING GIN (\"metrics\")");
        }
    }

    public function down(): void
    {
        $tableName = config('vouchers.table_names.campaigns', 'voucher_campaigns');

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("DROP INDEX IF EXISTS {$tableName}_metrics_gin_index");
        }

        Schema::dropIfExists($tableName);
    }
};
