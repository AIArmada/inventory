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
        $tableName = $tables['campaign_variants'] ?? $prefix.'voucher_campaign_variants';

        Schema::create($tableName, function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('campaign_id');
            $table->foreignUuid('voucher_id')->nullable();

            // Identity
            $table->string('name');
            $table->char('variant_code', 1); // A, B, C, etc.

            // Traffic Allocation (percentage with 2 decimal places)
            $table->decimal('traffic_percentage', 5, 2)->default(100.00);

            // Metrics
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('applications')->default(0);
            $table->unsignedBigInteger('conversions')->default(0);
            $table->bigInteger('revenue_cents')->default(0);
            $table->bigInteger('discount_cents')->default(0);

            // Control variant flag
            $table->boolean('is_control')->default(false);

            $table->timestamps();

            // Unique variant code per campaign
            $table->unique(['campaign_id', 'variant_code']);

            // Indexes
            $table->index('campaign_id');
            $table->index('voucher_id');
        });
    }

    public function down(): void
    {
        /** @var array<string, string> $tables */
        $tables = config('vouchers.database.tables', []);
        $prefix = (string) config('vouchers.database.table_prefix', '');
        $tableName = $tables['campaign_variants'] ?? $prefix.'voucher_campaign_variants';

        Schema::dropIfExists($tableName);
    }
};
