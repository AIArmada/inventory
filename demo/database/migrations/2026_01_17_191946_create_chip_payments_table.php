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

        Schema::create($tablePrefix . 'payments', function (Blueprint $table): void {
            // Core API fields - Payment object structure from CHIP API
            $table->uuid('id')->primary();
            $table->uuid('purchase_id'); // Reference to purchases table

            // Payment type and direction
            $table->string('payment_type', 16)
                ->default('purchase')
                ->comment('Backed by CHIP payment type values: purchase, refund, payout.');
            $table->boolean('is_outgoing')->default(false);

            // Amount fields - exactly as per API (integers for cents)
            $table->integer('amount'); // Amount in smallest currency unit
            $table->string('currency', 3)->default('MYR');
            $table->integer('net_amount'); // Net amount after fees
            $table->integer('fee_amount'); // Processing fees
            $table->integer('pending_amount')->default(0); // Pending amount
            $table->integer('pending_unfreeze_on')->nullable(); // Unix timestamp

            // Payment details
            $table->text('description')->nullable();
            $table->integer('paid_on')->nullable(); // Unix timestamp
            $table->integer('remote_paid_on')->nullable(); // Unix timestamp

            // API timestamps (Unix timestamps as integers)
            $table->integer('created_on'); // When payment was created
            $table->integer('updated_on'); // When payment was last updated

            // Owner scoping
            $table->nullableMorphs('owner');

            // Laravel timestamps for internal use
            $table->timestamps();

            // Indexes for optimal query performance
            $table->index(['purchase_id', 'payment_type']);
            $table->index(['created_on', 'payment_type']);
            $table->index('paid_on');
        });
    }

    public function down(): void
    {
        $tablePrefix = config('chip.database.table_prefix', 'chip_');

        Schema::dropIfExists($tablePrefix . 'payments');
    }
};
