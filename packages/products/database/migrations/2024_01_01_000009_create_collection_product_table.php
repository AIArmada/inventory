<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('products.database.tables.collection_product', 'collection_product'), function (Blueprint $table): void {
            $table->foreignUuid('collection_id');
            $table->foreignUuid('product_id');

            // Position for ordering products within a collection
            $table->unsignedInteger('position')->default(0);

            $table->timestamps();

            $table->primary(['collection_id', 'product_id']);
            $table->index(['collection_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('products.database.tables.collection_product', 'collection_product'));
    }
};
