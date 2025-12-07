<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();

            // Owner (multi-tenant)
            $table->morphs('owner');

            // Polymorphic link to the shippable (order, etc.)
            $table->nullableMorphs('shippable');

            // Reference & Carrier
            $table->string('reference')->index();
            $table->string('carrier_code', 50)->index();
            $table->string('service_code', 50)->nullable();
            $table->string('tracking_number')->nullable()->index();
            $table->string('carrier_reference')->nullable();

            // Status
            $table->string('status', 50)->default('draft')->index();

            // Addresses (JSON)
            $table->json('origin_address');
            $table->json('destination_address');

            // Package Info
            $table->unsignedInteger('package_count')->default(1);
            $table->unsignedInteger('total_weight')->default(0); // grams
            $table->unsignedInteger('declared_value')->default(0); // cents
            $table->string('currency', 3)->default('MYR');

            // Costs
            $table->unsignedInteger('shipping_cost')->default(0); // cents
            $table->unsignedInteger('insurance_cost')->default(0); // cents
            $table->unsignedInteger('cod_amount')->nullable(); // cents

            // Labels
            $table->string('label_url')->nullable();
            $table->string('label_format', 10)->nullable();

            // Timestamps
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('estimated_delivery_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('last_tracking_sync')->nullable();

            // Metadata
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Composite indexes for common queries
            $table->index(['owner_id', 'owner_type', 'status'], 'shipments_owner_status');
            $table->index(['carrier_code', 'status', 'created_at'], 'shipments_carrier_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
