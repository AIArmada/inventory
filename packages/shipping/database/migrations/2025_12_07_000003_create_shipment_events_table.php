<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();

            $table->string('carrier_event_code', 50)->nullable();
            $table->string('normalized_status', 50)->index();
            $table->text('description')->nullable();

            // Location
            $table->string('location')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country', 3)->nullable();
            $table->string('postal_code', 20)->nullable();

            $table->timestamp('occurred_at')->index();
            $table->json('raw_data')->nullable();

            $table->timestamps();

            // Prevent duplicate events
            $table->unique(
                ['shipment_id', 'carrier_event_code', 'occurred_at'],
                'shipment_events_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_events');
    }
};
