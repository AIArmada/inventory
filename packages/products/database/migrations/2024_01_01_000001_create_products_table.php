<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('products.database.tables.products', 'products'), function (Blueprint $table): void {
            $jsonColumnType = config('products.database.json_column_type', 'json');
            $table->uuid('id')->primary();

            // Owner (for multi-tenancy)
            $table->nullableUuidMorphs('owner');

            // Basic fields
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->text('short_description')->nullable();
            $table->string('sku')->nullable();
            $table->string('barcode')->nullable();

            // Type and status
            $table->string('type')->default('simple'); // simple, configurable, bundle, digital, subscription
            $table->string('status')->default('draft'); // draft, active, disabled, archived
            $table->string('visibility')->default('catalog_search'); // catalog, search, catalog_search, individual, hidden

            // Pricing (stored in cents)
            $table->unsignedBigInteger('price')->default(0);
            $table->unsignedBigInteger('compare_price')->nullable();
            $table->unsignedBigInteger('cost')->nullable();
            $table->string('currency', 3)->default('MYR');

            // Physical attributes
            $table->decimal('weight', 10, 2)->nullable();
            $table->decimal('length', 10, 2)->nullable();
            $table->decimal('width', 10, 2)->nullable();
            $table->decimal('height', 10, 2)->nullable();
            $table->string('weight_unit', 10)->default('kg');
            $table->string('dimension_unit', 10)->default('cm');

            // Flags
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_taxable')->default(true);
            $table->boolean('requires_shipping')->default(true);

            // SEO
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();

            // Tax integration
            $table->string('tax_class')->nullable();

            // Metadata
            $table->{$jsonColumnType}('metadata')->nullable();

            // Publishing
            $table->timestamp('published_at')->nullable();

            $table->timestamps();

            $table->unique(['owner_type', 'owner_id', 'slug']);
            $table->unique(['owner_type', 'owner_id', 'sku']);

            // Indexes
            $table->index(['status', 'visibility']);
            $table->index('type');
            $table->index('is_featured');
            $table->index('price');
            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('products.database.tables.products', 'products'));
    }
};
