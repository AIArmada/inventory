<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('affiliates.database.tables.conversions', 'affiliate_conversions');
        $jsonType = commerce_json_column_type('affiliates');

        Schema::create($tableName, function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('affiliate_id')->index();
            $table->foreignUuid('affiliate_attribution_id')->nullable()->index();
            $table->foreignUuid('affiliate_payout_id')->nullable()->index();
            $table->string('affiliate_code', 64)->index();
            $table->string('cart_identifier')->nullable();
            $table->string('cart_instance')->nullable();
            $table->string('voucher_code', 64)->nullable();
            $table->string('order_reference', 120)->nullable();
            $table->unsignedBigInteger('subtotal_minor')->default(0);
            $table->unsignedBigInteger('total_minor')->default(0);
            $table->unsignedBigInteger('commission_minor')->default(0);
            $table->string('commission_currency', 3)->default(config('affiliates.currency.default', 'USD'))->index();
            $table->string('status', 32)->default(config('affiliates.commissions.default_status', 'pending'))->index();
            $table->string('channel')->nullable();
            $table->string('owner_type')->nullable();
            $table->uuid('owner_id')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestamp('occurred_at')->nullable()->index();
            $table->timestamp('approved_at')->nullable()->index();
            $table->timestamps();

            $table->index(['owner_type', 'owner_id'], 'affiliate_conversions_owner_index');
            $table->index(['affiliate_id', 'status'], 'affiliate_conversions_affiliate_status_idx');
            $table->index(['status', 'occurred_at'], 'affiliate_conversions_status_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('affiliates.database.tables.conversions', 'affiliate_conversions'));
    }
};
