<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('affiliates.database.tables.commission_rules', 'affiliate_commission_rules');

        Schema::create($tableName, function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('program_id')->nullable();
            $table->string('name');
            $table->string('rule_type');
            $table->integer('priority')->default(0);

            $jsonType = config('affiliates.database.json_column_type', 'json');
            $table->addColumn($jsonType, 'conditions');

            $table->string('commission_type');
            $table->integer('commission_value');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);

            $table->addColumn($jsonType, 'metadata')->nullable();

            $table->timestamps();

            $table->index(['program_id', 'is_active', 'priority']);
            $table->index('rule_type');
        });
    }

    public function down(): void
    {
        $tableName = config('affiliates.database.tables.commission_rules', 'affiliate_commission_rules');
        Schema::dropIfExists($tableName);
    }
};
