<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Attribute to AttributeGroup pivot
        Schema::create(config('products.database.tables.attribute_attribute_group', 'product_attribute_attribute_group'), function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('attribute_id');
            $table->foreignUuid('attribute_group_id');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['attribute_id', 'attribute_group_id'], 'attr_group_unique');
            $table->index('attribute_group_id');
        });

        // Attribute to AttributeSet pivot
        Schema::create(config('products.database.tables.attribute_attribute_set', 'product_attribute_attribute_set'), function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('attribute_id');
            $table->foreignUuid('attribute_set_id');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['attribute_id', 'attribute_set_id'], 'attr_set_unique');
            $table->index('attribute_set_id');
        });

        // AttributeGroup to AttributeSet pivot
        Schema::create(config('products.database.tables.attribute_group_attribute_set', 'product_attribute_group_attribute_set'), function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('attribute_group_id');
            $table->foreignUuid('attribute_set_id');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['attribute_group_id', 'attribute_set_id'], 'group_set_unique');
            $table->index('attribute_set_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('products.database.tables.attribute_group_attribute_set', 'product_attribute_group_attribute_set'));
        Schema::dropIfExists(config('products.database.tables.attribute_attribute_set', 'product_attribute_attribute_set'));
        Schema::dropIfExists(config('products.database.tables.attribute_attribute_group', 'product_attribute_attribute_group'));
    }
};
