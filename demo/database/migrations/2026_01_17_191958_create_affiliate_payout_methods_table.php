<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('affiliates.database.tables.payout_methods', 'affiliate_payout_methods');

        Schema::create($tableName, function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('affiliate_id');
            $table->string('type');
            $table->text('details');
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_default')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['affiliate_id', 'is_default']);
        });
    }

    public function down(): void
    {
        $tableName = config('affiliates.database.tables.payout_methods', 'affiliate_payout_methods');
        Schema::dropIfExists($tableName);
    }
};
