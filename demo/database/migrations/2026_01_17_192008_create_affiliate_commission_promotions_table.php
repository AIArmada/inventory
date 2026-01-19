<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('affiliates.database.tables.commission_promotions', 'affiliate_commission_promotions');

        Schema::create($tableName, function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('program_id')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('bonus_type'); // percentage, flat, multiplier
            $table->integer('bonus_value');

            $jsonType = config('affiliates.database.json_column_type', 'json');
            $table->addColumn($jsonType, 'conditions')->nullable();

            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->integer('max_uses')->nullable();
            $table->integer('current_uses')->default(0);

            $table->addColumn($jsonType, 'affiliate_ids')->nullable();

            $table->timestamps();

            $table->index(['starts_at', 'ends_at']);
            $table->index('program_id');
        });
    }

    public function down(): void
    {
        $tableName = config('affiliates.database.tables.commission_promotions', 'affiliate_commission_promotions');
        Schema::dropIfExists($tableName);
    }
};
