<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('products.database.tables.variants', 'product_variants'), function (Blueprint $table): void {
            $jsonColumnType = config('products.database.json_column_type', 'json');

            $table->uuid('id')->primary();

            // Owner (for multi-tenancy)
            $table->nullableUuidMorphs('owner');

            $table->foreignUuid('product_id');

            $table->string('name')->nullable();

            // Identification
            $table->string('sku');
            $table->string('barcode')->nullable();

            // Price overrides (null = use parent product price)
            $table->unsignedBigInteger('price')->nullable();
            $table->unsignedBigInteger('compare_price')->nullable();
            $table->unsignedBigInteger('cost')->nullable();

            // Physical attributes override
            $table->decimal('weight', 10, 2)->nullable();
            $table->decimal('length', 10, 2)->nullable();
            $table->decimal('width', 10, 2)->nullable();
            $table->decimal('height', 10, 2)->nullable();

            // Status
            $table->boolean('is_default')->default(false);
            $table->boolean('is_enabled')->default(true);

            $table->{$jsonColumnType}('metadata')->nullable();

            $table->timestamps();

            $table->unique(['owner_type', 'owner_id', 'sku']);

            $table->index('product_id');
            $table->index('is_enabled');
            $table->index('is_default');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('products.database.tables.variants', 'product_variants'));
    }
};
