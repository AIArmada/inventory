<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add analytics columns to chip_purchases table for LocalAnalyticsService.
 */
return new class extends Migration {
    public function up(): void
    {
        $tablePrefix = config('chip.database.table_prefix', 'chip_');

        Schema::table($tablePrefix . 'purchases', function (Blueprint $table): void {
            // Analytics denormalized columns for efficient querying
            $table->string('payment_method', 32)->nullable()->after('status')
                ->comment('Denormalized from transaction_data for analytics queries.');

            $table->integer('total_minor')->default(0)->after('payment_method')
                ->comment('Denormalized total amount in minor units for analytics.');

            $table->integer('refund_amount_minor')->default(0)->after('total_minor')
                ->comment('Tracks refund amount in minor units.');

            $table->string('failure_reason')->nullable()->after('refund_amount_minor')
                ->comment('Stores payment failure reason for analytics.');

            $table->timestamp('failed_at')->nullable()->after('failure_reason')
                ->comment('Timestamp when payment failed.');

            $table->timestamp('refunded_at')->nullable()->after('failed_at')
                ->comment('Timestamp when payment was refunded.');

            // Add indexes for analytics queries
            $table->index(['payment_method', 'status', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        $tablePrefix = config('chip.database.table_prefix', 'chip_');

        Schema::table($tablePrefix . 'purchases', function (Blueprint $table): void {
            $table->dropIndex([$tablePrefix . 'purchases_payment_method_status_created_at_index']);
            $table->dropIndex([$tablePrefix . 'purchases_status_created_at_index']);

            $table->dropColumn([
                'payment_method',
                'total_minor',
                'refund_amount_minor',
                'failure_reason',
                'failed_at',
                'refunded_at',
            ]);
        });
    }
};
