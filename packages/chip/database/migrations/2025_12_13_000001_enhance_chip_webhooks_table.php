<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tablePrefix = config('chip.database.table_prefix', 'chip_');

        Schema::table($tablePrefix . 'webhooks', function (Blueprint $table): void {
            // Enhanced webhook fields for retry and monitoring
            $table->string('status')->default('pending')->after('processed');
            $table->string('idempotency_key')->nullable()->unique()->after('status');
            $table->integer('retry_count')->default(0)->after('processing_attempts');
            $table->timestamp('last_retry_at')->nullable()->after('retry_count');
            $table->text('last_error')->nullable()->after('last_retry_at');
            $table->decimal('processing_time_ms', 10, 3)->nullable()->after('last_error');
            $table->string('ip_address')->nullable()->after('processing_time_ms');
            $table->string('event')->nullable()->after('event_type');

            // Indexes for retry and monitoring queries
            $table->index('status');
            $table->index(['status', 'retry_count']);
        });
    }

    public function down(): void
    {
        $tablePrefix = config('chip.database.table_prefix', 'chip_');

        Schema::table($tablePrefix . 'webhooks', function (Blueprint $table): void {
            $table->dropIndex(['status']);
            $table->dropIndex(['status', 'retry_count']);
            $table->dropColumn([
                'status',
                'idempotency_key',
                'retry_count',
                'last_retry_at',
                'last_error',
                'processing_time_ms',
                'ip_address',
                'event',
            ]);
        });
    }
};
