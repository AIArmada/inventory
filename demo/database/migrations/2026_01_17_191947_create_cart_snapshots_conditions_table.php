<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $databaseConfig = config('filament-cart.database', []);
        $tablePrefix = $databaseConfig['table_prefix'] ?? 'cart_';
        $tables = $databaseConfig['tables'] ?? [];
        $tableName = $tables['snapshot_conditions'] ?? $tablePrefix . 'snapshot_conditions';
        $jsonType = (string) ($databaseConfig['json_column_type'] ?? commerce_json_column_type('cart', 'json'));

        Schema::create($tableName, function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('cart_id');
            $table->foreignUuid('cart_item_id')->nullable();
            $table->string('name');
            $table->string('type'); // discount, tax, fee, shipping, etc.
            $table->string('target'); // subtotal, total, price, etc.
            $table->{$jsonType}('target_definition'); // structured scope/phase/application
            $table->string('value'); // percentage or fixed amount
            $table->string('operator')->nullable(); // +, -, *, /, %
            $table->boolean('is_charge')->default(false);
            $table->boolean('is_dynamic')->default(false);
            $table->boolean('is_discount')->default(false);
            $table->boolean('is_percentage')->default(false);
            $table->boolean('is_global')->default(false);
            $table->string('parsed_value')->nullable(); // Calculated value
            $table->{$jsonType}('rules')->nullable(); // Additional rules
            $table->integer('order')->default(0);
            $table->{$jsonType}('attributes')->nullable();
            $table->string('item_id')->nullable()->index(); // Cart item ID this applies to (if item-level)
            $table->timestamps();

            // Indexes for performance
            $table->index(['cart_id', 'name']);
            $table->index('name');
            $table->index('type');
            $table->index('target');
            $table->index('order');
            $table->index('is_discount');
            $table->index('is_charge');
            $table->index('is_percentage');
            $table->index('is_dynamic');
            $table->index('is_global');
            $table->index('created_at');
            $table->index('updated_at');
        });

        // GIN indexes only work with jsonb in PostgreSQL
        if (($databaseConfig['json_column_type'] ?? commerce_json_column_type('cart', 'json')) === 'jsonb') {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                DB::statement("CREATE INDEX {$tableName}_rules_gin_index ON {$tableName} USING GIN (rules)");
                DB::statement("CREATE INDEX {$tableName}_attributes_gin_index ON {$tableName} USING GIN (attributes)");
            });
        }
    }

    public function down(): void
    {
        $databaseConfig = config('filament-cart.database', []);
        $tablePrefix = $databaseConfig['table_prefix'] ?? 'cart_';
        $tables = $databaseConfig['tables'] ?? [];
        $tableName = $tables['snapshot_conditions'] ?? $tablePrefix . 'snapshot_conditions';

        Schema::dropIfExists($tableName);
    }
};
