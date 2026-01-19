<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('affiliates.database.tables.program_tiers', 'affiliate_program_tiers');

        Schema::create($tableName, function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('program_id');
            $table->string('name');
            $table->integer('level');
            $table->integer('commission_rate_basis_points');
            $table->integer('min_conversions')->default(0);
            $table->integer('min_revenue')->default(0);

            $jsonType = config('affiliates.database.json_column_type', 'json');
            $table->addColumn($jsonType, 'benefits')->nullable();

            $table->timestamps();

            $table->unique(['program_id', 'level']);
            $table->index('program_id');
        });
    }

    public function down(): void
    {
        $tableName = config('affiliates.database.tables.program_tiers', 'affiliate_program_tiers');
        Schema::dropIfExists($tableName);
    }
};
