<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('products.database.tables.attribute_groups', 'product_attribute_groups'), function (Blueprint $table): void {
            $table->uuid('id')->primary();

            // Owner (for multi-tenancy)
            $table->nullableUuidMorphs('owner');

            $table->string('name');
            $table->string('code');
            $table->text('description')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_visible')->default(true);

            $table->timestamps();

            $table->unique(['owner_type', 'owner_id', 'code']);

            $table->index(['position', 'is_visible']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('products.database.tables.attribute_groups', 'product_attribute_groups'));
    }
};
