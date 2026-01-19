<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jsonType = commerce_json_column_type('affiliates');

        Schema::create(config('affiliates.database.tables.payouts', 'affiliate_payouts'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->string('reference')->unique();
            $table->string('status', 32)->default('draft')->index();
            $table->unsignedBigInteger('total_minor')->default(0);
            $table->unsignedInteger('conversion_count')->default(0);
            $table->string('currency', 3)->default(config('affiliates.payouts.currency', 'USD'))->index();
            $table->{$jsonType}('metadata')->nullable();
            $table->string('payee_type')->nullable()->index();
            $table->uuid('payee_id')->nullable()->index();
            $table->string('owner_type')->nullable()->index();
            $table->uuid('owner_id')->nullable()->index();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('paid_at')->nullable()->index();
            $table->timestamps();

            $table->index(['payee_type', 'payee_id'], 'affiliate_payouts_payee_idx');
            $table->index(['owner_type', 'owner_id'], 'affiliate_payouts_owner_idx');
            $table->index(['status', 'scheduled_at'], 'affiliate_payouts_pending_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('affiliates.database.tables.payouts', 'affiliate_payouts'));
    }
};
