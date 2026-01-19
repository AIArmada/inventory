<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('products.database.tables.option_values', 'product_option_values'), function (Blueprint $table): void {
            $jsonColumnType = config('products.database.json_column_type', 'json');

            $table->uuid('id')->primary();

            // Owner (for multi-tenancy)
            $table->nullableUuidMorphs('owner');

            $table->foreignUuid('option_id');

            $table->string('name'); // e.g., Small, Medium, Large, Red, Blue
            $table->unsignedInteger('position')->default(0);

            // Swatch support
            $table->string('swatch_color', 7)->nullable(); // Hex color
            $table->string('swatch_image')->nullable(); // URL or path

            $table->{$jsonColumnType}('metadata')->nullable();

            $table->timestamps();

            $table->index(['option_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('products.database.tables.option_values', 'product_option_values'));
    }
};
