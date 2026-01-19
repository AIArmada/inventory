<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('products.database.tables.categories', 'product_categories'), function (Blueprint $table): void {
            $jsonColumnType = config('products.database.json_column_type', 'json');

            $table->uuid('id')->primary();

            // Owner (for multi-tenancy)
            $table->nullableUuidMorphs('owner');

            // Parent for hierarchy
            $table->foreignUuid('parent_id')->nullable();

            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();

            // Display
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_featured')->default(false);

            // SEO
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();

            $table->{$jsonColumnType}('metadata')->nullable();

            $table->timestamps();

            // Unique slug per parent
            $table->unique(['owner_type', 'owner_id', 'parent_id', 'slug']);
            $table->index('parent_id');
            $table->index(['is_visible', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('products.database.tables.categories', 'product_categories'));
    }
};
