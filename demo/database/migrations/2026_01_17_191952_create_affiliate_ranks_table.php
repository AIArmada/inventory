<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('affiliates.database.tables.ranks', 'affiliate_ranks');

        Schema::create($tableName, function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->integer('level')->unique();
            $table->integer('min_personal_sales')->default(0);
            $table->integer('min_team_sales')->default(0);
            $table->integer('min_active_downlines')->default(0);
            $table->integer('commission_rate_basis_points');

            $table->string('owner_type')->nullable()->index();
            $table->uuid('owner_id')->nullable()->index();

            $jsonType = config('affiliates.database.json_column_type', 'json');
            $table->addColumn($jsonType, 'override_rates')->nullable();
            $table->addColumn($jsonType, 'benefits')->nullable();
            $table->addColumn($jsonType, 'metadata')->nullable();

            $table->timestamps();

            $table->index(['owner_type', 'owner_id'], 'affiliate_ranks_owner_idx');
        });
    }

    public function down(): void
    {
        $tableName = config('affiliates.database.tables.ranks', 'affiliate_ranks');
        Schema::dropIfExists($tableName);
    }
};
