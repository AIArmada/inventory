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

        Schema::create(config('affiliates.database.tables.payout_events', 'affiliate_payout_events'), function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('affiliate_payout_id')->index();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->index();
            $table->{$jsonType}('metadata')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('affiliates.database.tables.payout_events', 'affiliate_payout_events'));
    }
};
