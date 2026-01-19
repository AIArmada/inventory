<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('products.database.tables.attribute_sets', 'product_attribute_sets'), function (Blueprint $table): void {
            $table->uuid('id')->primary();

            // Owner (for multi-tenancy)
            $table->nullableUuidMorphs('owner');

            $table->string('name');
            $table->string('code');
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('position')->default(0);

            $table->timestamps();

            $table->unique(['owner_type', 'owner_id', 'code']);

            $table->index(['is_default', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('products.database.tables.attribute_sets', 'product_attribute_sets'));
    }
};
