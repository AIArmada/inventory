<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('affiliates.database.tables.balances', 'affiliate_balances');

        Schema::create($tableName, function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('affiliate_id')->unique();
            $table->string('currency', 3);
            $table->bigInteger('holding_minor')->default(0);
            $table->bigInteger('available_minor')->default(0);
            $table->bigInteger('lifetime_earnings_minor')->default(0);
            $table->bigInteger('minimum_payout_minor');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $tableName = config('affiliates.database.tables.balances', 'affiliate_balances');
        Schema::dropIfExists($tableName);
    }
};
