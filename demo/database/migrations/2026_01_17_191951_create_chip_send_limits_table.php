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

        Schema::create($tablePrefix . 'send_limits', function (Blueprint $table): void {
            // Core API fields - Send Limit structure from CHIP Send API
            $table->integer('id')->primary();

            // Monetary and accounting details (values are provided in minor units)
            $table->bigInteger('amount');
            $table->bigInteger('fee');
            $table->bigInteger('net_amount');

            // Classification fields straight from the API contract
            $table->string('currency', 3);
            $table->string('fee_type', 16);
            $table->string('transaction_type', 16);
            $table->string('status', 24);

            // Approval metadata
            $table->integer('approvals_required')->default(0);
            $table->integer('approvals_received')->default(0);

            // Settlement and lifecycle timestamps
            $table->date('from_settlement')->nullable();
            $table->timestamp('created_at');
            $table->timestamp('updated_at');

            // Owner scoping
            $table->nullableMorphs('owner');

            // Indexes for efficient querying by status and currency buckets
            $table->index(['status', 'transaction_type']);
            $table->index(['currency', 'from_settlement']);
        });
    }

    public function down(): void
    {
        $tablePrefix = config('chip.database.table_prefix', 'chip_');

        Schema::dropIfExists($tablePrefix . 'send_limits');
    }
};
