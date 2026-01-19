<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('products.database.tables.attributes', 'product_attributes'), function (Blueprint $table): void {
            $jsonColumnType = config('products.database.json_column_type', 'json');

            $table->uuid('id')->primary();

            // Owner (for multi-tenancy)
            $table->nullableUuidMorphs('owner');

            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type')->default('text'); // AttributeType enum

            // Validation and options
            $table->{$jsonColumnType}('validation')->nullable();
            $table->{$jsonColumnType}('options')->nullable(); // For select/multiselect types

            // Behavior flags
            $table->boolean('is_required')->default(false);
            $table->boolean('is_filterable')->default(false);
            $table->boolean('is_searchable')->default(false);
            $table->boolean('is_comparable')->default(false);
            $table->boolean('is_visible_on_front')->default(true);
            $table->boolean('is_visible_on_admin')->default(true);

            // Display
            $table->unsignedInteger('position')->default(0);
            $table->string('suffix')->nullable(); // e.g., 'kg', 'cm'
            $table->string('placeholder')->nullable();
            $table->string('help_text')->nullable();

            // Default value
            $table->text('default_value')->nullable();

            $table->timestamps();

            $table->unique(['owner_type', 'owner_id', 'code']);

            $table->index(['type', 'is_filterable']);
            $table->index(['is_searchable']);
            $table->index('position');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('products.database.tables.attributes', 'product_attributes'));
    }
};
