<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /** @var array<string, string> $tables */
        $tables = config('vouchers.database.tables', []);
        $prefix = (string) config('vouchers.database.table_prefix', '');
        $table = $tables['voucher_fraud_signals'] ?? $prefix.'voucher_fraud_signals';

        Schema::create($table, function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('voucher_id')->nullable();
            $table->string('voucher_code')->nullable()->index();
            $table->string('signal_type', 50)->index();
            $table->decimal('score', 5, 2);
            $table->string('risk_level', 20)->index();
            $table->string('message');
            $table->string('detector', 50)->index();
            $table->json('metadata')->nullable();
            $table->json('context')->nullable();
            $table->string('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable()->index();
            $table->string('device_fingerprint')->nullable()->index();
            $table->boolean('was_blocked')->default(false)->index();
            $table->boolean('reviewed')->default(false)->index();
            $table->string('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();

            // Composite indexes for common queries
            $table->index(['voucher_code', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['ip_address', 'created_at']);
            $table->index(['risk_level', 'reviewed']);
            $table->index(['detector', 'signal_type']);
        });
    }

    public function down(): void
    {
        /** @var array<string, string> $tables */
        $tables = config('vouchers.database.tables', []);
        $prefix = (string) config('vouchers.database.table_prefix', '');
        $table = $tables['voucher_fraud_signals'] ?? $prefix.'voucher_fraud_signals';

        Schema::dropIfExists($table);
    }
};
