<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add AI/Analytics columns to cart_snapshots table.
 *
 * These columns mirror the cart package's AI columns for:
 * - Abandonment tracking
 * - Recovery attempt monitoring
 * - Activity tracking
 * - Collaborative cart support
 */
return new class extends Migration
{
    public function up(): void
    {
        $databaseConfig = config('filament-cart.database', []);
        $tablePrefix = $databaseConfig['table_prefix'] ?? 'cart_';
        $tables = $databaseConfig['tables'] ?? [];
        $tableName = $tables['snapshots'] ?? $tablePrefix . 'snapshots';

        if (Schema::hasColumn($tableName, 'last_activity_at')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
            // AI/Analytics - Abandonment Tracking
            $table->timestamp('last_activity_at')->nullable()->after('metadata');
            $table->timestamp('checkout_started_at')->nullable()->after('last_activity_at');
            $table->timestamp('checkout_abandoned_at')->nullable()->after('checkout_started_at');
            $table->unsignedTinyInteger('recovery_attempts')->default(0)->after('checkout_abandoned_at');
            $table->timestamp('recovered_at')->nullable()->after('recovery_attempts');

            // Collaborative Cart Support
            $table->boolean('is_collaborative')->default(false)->after('recovered_at');
            $table->unsignedSmallInteger('collaborator_count')->default(0)->after('is_collaborative');

            // Fraud Detection
            $table->string('fraud_risk_level', 10)->nullable()->after('collaborator_count');
            $table->decimal('fraud_score', 5, 2)->nullable()->after('fraud_risk_level');

            // Indexes for common queries
            $table->index('last_activity_at');
            $table->index('checkout_started_at');
            $table->index('checkout_abandoned_at');
            $table->index('recovered_at');
            $table->index('is_collaborative');
            $table->index('fraud_risk_level');

            // Composite index for abandonment analysis
            $table->index(['checkout_abandoned_at', 'recovered_at'], $tableName . '_abandonment_idx');
        });
    }

    public function down(): void
    {
        $databaseConfig = config('filament-cart.database', []);
        $tablePrefix = $databaseConfig['table_prefix'] ?? 'cart_';
        $tables = $databaseConfig['tables'] ?? [];
        $tableName = $tables['snapshots'] ?? $tablePrefix . 'snapshots';

        Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
            $table->dropIndex($tableName . '_abandonment_idx');
            $table->dropIndex(['last_activity_at']);
            $table->dropIndex(['checkout_started_at']);
            $table->dropIndex(['checkout_abandoned_at']);
            $table->dropIndex(['recovered_at']);
            $table->dropIndex(['is_collaborative']);
            $table->dropIndex(['fraud_risk_level']);

            $table->dropColumn([
                'last_activity_at',
                'checkout_started_at',
                'checkout_abandoned_at',
                'recovery_attempts',
                'recovered_at',
                'is_collaborative',
                'collaborator_count',
                'fraud_risk_level',
                'fraud_score',
            ]);
        });
    }
};
