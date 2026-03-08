<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(config('inventory.table_names.serial_history', 'inventory_serial_history'), function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignUuid('serial_id');

            // What happened
            $table->string('event_type');
            $table->string('previous_status')->nullable();
            $table->string('new_status')->nullable();

            // Location tracking
            $table->foreignUuid('from_location_id')->nullable();
            $table->foreignUuid('to_location_id')->nullable();

            // Related entities
            $table->nullableUuidMorphs('related_to');
            $table->string('reference')->nullable();

            // Actor
            $table->foreignUuid('user_id')->nullable();
            $table->string('actor_name')->nullable();

            // Details
            $table->text('notes')->nullable();
            $jsonType = config('inventory.database.json_column_type', config('inventory.json_column_type', 'json'));
            $table->{$jsonType}('metadata')->nullable();

            $table->nullableUuidMorphs('owner');

            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();

            // Indexes
            $table->index('serial_id');
            $table->index('event_type');
            $table->index('occurred_at');
            $table->index('reference');
            $table->index(['serial_id', 'occurred_at'], 'inventory_serial_history_timeline_idx');
            $table->index(['owner_type', 'owner_id'], 'inventory_serial_history_owner_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('inventory.table_names.serial_history', 'inventory_serial_history'));
    }
};
