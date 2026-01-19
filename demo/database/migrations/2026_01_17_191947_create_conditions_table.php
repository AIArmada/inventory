<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tableName = config('cart.database.conditions_table', 'conditions');
        $jsonType = (string) commerce_json_column_type('cart', 'json');

        Schema::create($tableName, function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();

            // Core identification
            $table->string('name')->unique();
            $table->string('display_name')->nullable();
            $table->text('description')->nullable();

            // Condition definition
            $table->string('type'); // discount, tax, fee, shipping, etc.
            $table->string('target'); // cart@cart_subtotal/aggregate, etc. (DSL string for UI/filtering)
            $table->{$jsonType}('target_definition'); // structured scope/phase/application payload
            $table->string('value'); // e.g., "-10%", "+5", "15"

            // Computed fields
            $table->string('operator')->nullable(); // +, -, *, /, %
            $table->boolean('is_charge')->default(false);
            $table->boolean('is_dynamic')->default(false);
            $table->boolean('is_discount')->default(false);
            $table->boolean('is_percentage')->default(false);
            $table->string('parsed_value')->nullable();

            // Configuration
            $table->integer('order')->default(0);
            $table->{$jsonType}('attributes')->nullable();
            $table->{$jsonType}('rules')->nullable();

            // Status
            $table->boolean('is_global')->default(false);
            $table->boolean('is_active')->default(false);

            $table->nullableUuidMorphs('owner');

            $table->timestamps();

            // Indexes for filtering and sorting
            $table->index(['type', 'is_active']);
            $table->index(['target', 'is_active']);
            $table->index('is_charge');
            $table->index('is_discount');
            $table->index('is_percentage');
            $table->index('is_dynamic');
            $table->index('is_global');
            $table->index('order');
        });

        if (
            $jsonType === 'jsonb'
            && Schema::getConnection()->getDriverName() === 'pgsql'
        ) {
            DB::statement("CREATE INDEX {$tableName}_attributes_gin_index ON \"{$tableName}\" USING GIN (\"attributes\")");
            DB::statement("CREATE INDEX {$tableName}_rules_gin_index ON \"{$tableName}\" USING GIN (\"rules\")");
            DB::statement("CREATE INDEX {$tableName}_target_definition_gin_index ON \"{$tableName}\" USING GIN (\"target_definition\")");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('cart.database.conditions_table', 'conditions'));
    }
};
