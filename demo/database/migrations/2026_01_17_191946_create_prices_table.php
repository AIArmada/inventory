<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('pricing.database.tables.prices', 'prices'), function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('price_list_id');

            // Polymorphic: Product, Variant, etc.
            $table->uuidMorphs('priceable');

            // Price data
            $table->unsignedBigInteger('amount'); // In cents
            $table->unsignedBigInteger('compare_amount')->nullable(); // Original/strike-through price
            $table->string('currency', 3)->default('MYR');

            // Quantity-based pricing (min qty for this price)
            $table->unsignedInteger('min_quantity')->default(1);

            // Scheduling
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->nullableMorphs('owner');

            $table->timestamps();

            // Indexes
            $table->index(['priceable_type', 'priceable_id', 'price_list_id']);
            $table->index(['starts_at', 'ends_at']);
            $table->unique(['price_list_id', 'priceable_type', 'priceable_id', 'min_quantity'], 'prices_unique_per_quantity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('pricing.database.tables.prices', 'prices'));
    }
};
