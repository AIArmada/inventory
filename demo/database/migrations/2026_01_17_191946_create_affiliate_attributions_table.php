<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('affiliates.database.tables.attributions', 'affiliate_attributions');
        $jsonType = commerce_json_column_type('affiliates');

        Schema::create($tableName, function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('affiliate_id')->index();
            $table->string('affiliate_code', 64)->index();
            $table->string('cart_identifier')->nullable();
            $table->string('cart_instance')->default('default');
            $table->string('cookie_value', 120)->nullable()->unique();
            $table->string('voucher_code', 64)->nullable()->index();
            $table->string('source', 64)->nullable();
            $table->string('medium', 64)->nullable();
            $table->string('campaign', 64)->nullable();
            $table->string('term', 64)->nullable();
            $table->string('content', 64)->nullable();
            $table->string('landing_url')->nullable();
            $table->string('referrer_url')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->uuid('user_id')->nullable()->index();
            $table->string('owner_type')->nullable();
            $table->uuid('owner_id')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_cookie_seen_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            $table->index(['cart_identifier', 'cart_instance'], 'affiliate_attributions_cart_index');
            $table->index('cookie_value', 'affiliate_attributions_cookie_index');
            $table->index(['owner_type', 'owner_id'], 'affiliate_attributions_owner_idx');
            $table->index(['affiliate_id', 'first_seen_at'], 'affiliate_attributions_timeline_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('affiliates.database.tables.attributions', 'affiliate_attributions'));
    }
};
