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

        Schema::create($tablePrefix . 'daily_metrics', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->date('date');
            $table->string('payment_method')->nullable();

            // Transaction counts
            $table->integer('total_attempts')->default(0);
            $table->integer('successful_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->integer('refunded_count')->default(0);

            // Revenue metrics (in minor units)
            $table->bigInteger('revenue_minor')->default(0);
            $table->bigInteger('refunds_minor')->default(0);

            // Calculated metrics
            $table->decimal('success_rate', 5, 2)->default(0);
            $table->decimal('avg_transaction_minor', 12, 2)->default(0);

            // Failure breakdown
            $jsonType = (string) commerce_json_column_type('chip', 'json');
            $table->{$jsonType}('failure_breakdown')->nullable();

            $table->timestamps();

            $table->unique(['date', 'payment_method']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        $tablePrefix = config('chip.database.table_prefix', 'chip_');

        Schema::dropIfExists($tablePrefix . 'daily_metrics');
    }
};
