<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('affiliates.database.tables.volume_tiers', 'affiliate_volume_tiers');

        Schema::create($tableName, function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('program_id')->nullable();
            $table->string('name');
            $table->bigInteger('min_volume_minor');
            $table->bigInteger('max_volume_minor')->nullable();
            $table->integer('commission_rate_basis_points');
            $table->string('period')->default('monthly');
            $table->timestamps();

            $table->index('program_id');
            $table->index('min_volume_minor');
        });
    }

    public function down(): void
    {
        $tableName = config('affiliates.database.tables.volume_tiers', 'affiliate_volume_tiers');
        Schema::dropIfExists($tableName);
    }
};
