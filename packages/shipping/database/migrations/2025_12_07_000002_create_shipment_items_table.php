<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();

            // Polymorphic link to original item
            $table->nullableMorphs('shippable_item');

            $table->string('sku')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('weight')->default(0); // grams
            $table->unsignedInteger('declared_value')->default(0); // cents

            // For customs
            $table->string('hs_code')->nullable();
            $table->string('origin_country', 3)->nullable();

            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['shipment_id', 'sku']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_items');
    }
};
