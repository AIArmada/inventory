<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_zones', function (Blueprint $table): void {
            $table->id();
            $table->morphs('owner');

            $table->string('name');
            $table->string('code', 50)->unique();
            $table->string('type', 20); // country, state, postcode, radius

            // Geographic conditions (JSON)
            $table->json('countries')->nullable();
            $table->json('states')->nullable();
            $table->json('postcode_ranges')->nullable(); // [{"from": "40000", "to": "49999"}]

            // For radius-based zones
            $table->decimal('center_lat', 10, 8)->nullable();
            $table->decimal('center_lng', 11, 8)->nullable();
            $table->unsignedInteger('radius_km')->nullable();

            $table->unsignedInteger('priority')->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('active')->default(true);

            $table->timestamps();

            $table->index(['owner_id', 'owner_type', 'active']);
            $table->index('priority');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_zones');
    }
};
