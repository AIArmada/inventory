<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('affiliates.database.tables.daily_stats', 'affiliate_daily_stats');

        Schema::create($tableName, function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('affiliate_id');
            $table->date('date');

            $table->string('owner_type')->nullable()->index();
            $table->uuid('owner_id')->nullable()->index();
            $table->index(['owner_type', 'owner_id'], 'affiliate_daily_stats_owner_idx');

            $table->integer('clicks')->default(0);
            $table->integer('unique_clicks')->default(0);
            $table->integer('attributions')->default(0);
            $table->integer('conversions')->default(0);
            $table->bigInteger('revenue_cents')->default(0);
            $table->bigInteger('commission_cents')->default(0);
            $table->integer('refunds')->default(0);
            $table->bigInteger('refund_amount_cents')->default(0);
            $table->decimal('conversion_rate', 8, 4)->default(0);
            $table->decimal('epc_cents', 10, 4)->default(0);

            $jsonType = config('affiliates.database.json_column_type', 'json');
            $table->addColumn($jsonType, 'breakdown')->nullable();

            $table->timestamps();

            $table->unique(['affiliate_id', 'date']);
            $table->index(['date', 'revenue_cents']);
        });
    }

    public function down(): void
    {
        $tableName = config('affiliates.database.tables.daily_stats', 'affiliate_daily_stats');
        Schema::dropIfExists($tableName);
    }
};
