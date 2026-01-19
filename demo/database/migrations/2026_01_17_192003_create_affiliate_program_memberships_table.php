<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('affiliates.database.tables.program_memberships', 'affiliate_program_memberships');

        Schema::create($tableName, function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('affiliate_id');
            $table->foreignUuid('program_id');
            $table->foreignUuid('tier_id')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('applied_at');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignUuid('approved_by')->nullable();

            $jsonType = config('affiliates.database.json_column_type', 'json');
            $table->addColumn($jsonType, 'custom_terms')->nullable();

            $table->timestamps();

            $table->unique(['affiliate_id', 'program_id']);
            $table->index(['program_id', 'status']);
        });
    }

    public function down(): void
    {
        $tableName = config('affiliates.database.tables.program_memberships', 'affiliate_program_memberships');
        Schema::dropIfExists($tableName);
    }
};
