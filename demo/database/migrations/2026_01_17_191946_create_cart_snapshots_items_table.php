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
        $tableName = $tables['snapshot_items'] ?? $tablePrefix . 'snapshot_items';
        $jsonType = (string) ($databaseConfig['json_column_type'] ?? commerce_json_column_type('cart', 'json'));

        Schema::create($tableName, function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('cart_id');
            $table->string('item_id')->index(); // The original cart item ID
            $table->string('name');
            $table->unsignedInteger('price'); // Price in cents (from Money object)
            $table->unsignedInteger('quantity');
            $table->{$jsonType}('attributes')->nullable();
            $table->{$jsonType}('conditions')->nullable();
            $table->string('associated_model')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['cart_id', 'item_id']);
            $table->index('name');
            $table->index('price');
            $table->index('quantity');
            $table->index('associated_model');
            $table->index('created_at');
            $table->index('updated_at');
        });

        // GIN indexes only work with jsonb in PostgreSQL
        if (($databaseConfig['json_column_type'] ?? commerce_json_column_type('cart', 'json')) === 'jsonb') {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                DB::statement("CREATE INDEX {$tableName}_attributes_gin_index ON {$tableName} USING GIN (attributes)");
                DB::statement("CREATE INDEX {$tableName}_conditions_gin_index ON {$tableName} USING GIN (conditions)");
            });
        }
    }

    public function down(): void
    {
        $databaseConfig = config('filament-cart.database', []);
        $tablePrefix = $databaseConfig['table_prefix'] ?? 'cart_';
        $tables = $databaseConfig['tables'] ?? [];
        $tableName = $tables['snapshot_items'] ?? $tablePrefix . 'snapshot_items';

        Schema::dropIfExists($tableName);
    }
};
