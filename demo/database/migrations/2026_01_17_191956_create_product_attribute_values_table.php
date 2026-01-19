<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('products.database.tables.attribute_values', 'product_attribute_values'), function (Blueprint $table): void {
            $table->uuid('id')->primary();

            // Owner (for multi-tenancy)
            $table->nullableUuidMorphs('owner');

            // The attribute this value belongs to
            $table->foreignUuid('attribute_id');

            // Polymorphic relation to Product or Variant
            $table->uuidMorphs('attributable');

            // The actual value (stored as text, cast by attribute type)
            $table->text('value')->nullable();

            // For translatable attributes
            $table->string('locale', 10)->nullable();

            $table->timestamps();

            // Unique constraint: one value per attribute per model per locale
            $table->unique(['attribute_id', 'attributable_type', 'attributable_id', 'locale'], 'attr_val_unique');

            $table->index('attribute_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('products.database.tables.attribute_values', 'product_attribute_values'));
    }
};
