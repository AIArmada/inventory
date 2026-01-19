<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('pricing.database.tables.price_tiers', 'price_tiers'), function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('price_list_id')->nullable();

            // Polymorphic: Product, Variant, etc.
            $table->uuidMorphs('tierable');

            // Tier quantity range
            $table->unsignedInteger('min_quantity');
            $table->unsignedInteger('max_quantity')->nullable();

            // Price for this tier
            $table->unsignedBigInteger('amount'); // In cents
            $table->string('currency', 3)->default('MYR');

            // Alternative: Discount instead of fixed price
            $table->string('discount_type')->nullable(); // 'percentage', 'fixed'
            $table->unsignedBigInteger('discount_value')->nullable();

            $table->nullableMorphs('owner');

            $table->timestamps();

            // Indexes
            $table->index(['min_quantity', 'max_quantity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('pricing.database.tables.price_tiers', 'price_tiers'));
    }
};
