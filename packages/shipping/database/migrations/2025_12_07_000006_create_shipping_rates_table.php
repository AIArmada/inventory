<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('zone_id')->constrained('shipping_zones')->cascadeOnDelete();

            $table->string('carrier_code', 50)->nullable(); // null = all carriers
            $table->string('method_code', 50);
            $table->string('name');
            $table->text('description')->nullable();

            $table->string('calculation_type', 20); // flat, per_kg, per_item, percentage, table
            $table->unsignedInteger('base_rate')->default(0); // cents
            $table->unsignedInteger('per_unit_rate')->default(0); // cents per kg/item
            $table->unsignedInteger('min_charge')->nullable(); // cents
            $table->unsignedInteger('max_charge')->nullable(); // cents
            $table->unsignedInteger('free_shipping_threshold')->nullable(); // cents

            // Rate table for weight-based pricing (JSON)
            $table->json('rate_table')->nullable(); // [{"min_weight": 0, "max_weight": 1000, "rate": 800}]

            $table->unsignedTinyInteger('estimated_days_min')->nullable();
            $table->unsignedTinyInteger('estimated_days_max')->nullable();

            $table->json('conditions')->nullable(); // Additional conditions
            $table->boolean('active')->default(true);

            $table->timestamps();

            $table->index(['zone_id', 'carrier_code', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_rates');
    }
};
