<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('filament-cart.database.table_prefix', 'cart_');
        $jsonType = config('filament-cart.database.json_column_type', 'json');

        Schema::create($prefix . 'alert_logs', function (Blueprint $table) use ($jsonType) {
            $table->uuid('id')->primary();
            $table->foreignUuid('alert_rule_id');

            $table->string('event_type');
            $table->string('severity')->default('info');
            $table->string('title');
            $table->text('message')->nullable();

            $table->{$jsonType}('event_data');
            $table->{$jsonType}('channels_notified');

            // Related entities
            $table->foreignUuid('cart_id')->nullable();
            $table->string('session_id')->nullable();

            // Read status
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->foreignUuid('read_by')->nullable();

            // Action taken
            $table->boolean('action_taken')->default(false);
            $table->string('action_type')->nullable();
            $table->timestamp('action_at')->nullable();

            $table->timestamps();

            $table->index(['alert_rule_id', 'created_at']);
            $table->index(['is_read', 'severity']);
            $table->index('cart_id');
            $table->index('event_type');
        });
    }

    public function down(): void
    {
        $prefix = config('filament-cart.database.table_prefix', 'cart_');

        Schema::dropIfExists($prefix . 'alert_logs');
    }
};
