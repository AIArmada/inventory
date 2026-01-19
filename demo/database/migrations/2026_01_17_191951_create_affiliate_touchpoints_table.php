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

        Schema::create(config('affiliates.database.tables.touchpoints', 'affiliate_touchpoints'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('affiliate_attribution_id')->index();
            $table->foreignUuid('affiliate_id')->index();
            $table->string('affiliate_code', 64)->index();

            $table->string('owner_type')->nullable()->index();
            $table->uuid('owner_id')->nullable()->index();
            $table->index(['owner_type', 'owner_id'], 'affiliate_touchpoints_owner_idx');

            $table->string('source', 64)->nullable()->index();
            $table->string('medium', 64)->nullable()->index();
            $table->string('campaign', 64)->nullable()->index();
            $table->string('term', 64)->nullable();
            $table->string('content', 64)->nullable();
            $table->string('ip_address', 45)->nullable()->index();
            $table->string('user_agent', 512)->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestamp('touched_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('affiliates.database.tables.touchpoints', 'affiliate_touchpoints'));
    }
};
