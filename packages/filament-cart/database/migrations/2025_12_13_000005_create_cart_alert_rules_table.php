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

        Schema::create($prefix . 'alert_rules', function (Blueprint $table) use ($jsonType) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();

            // Trigger conditions
            $table->string('event_type'); // abandonment, fraud, high_value, recovery, custom
            $table->{$jsonType}('conditions'); // Flexible condition rules

            // Channels
            $table->boolean('notify_email')->default(true);
            $table->boolean('notify_slack')->default(false);
            $table->boolean('notify_webhook')->default(false);
            $table->boolean('notify_database')->default(true);

            // Recipients
            $table->{$jsonType}('email_recipients')->nullable();
            $table->string('slack_webhook_url')->nullable();
            $table->string('webhook_url')->nullable();

            // Throttling
            $table->unsignedInteger('cooldown_minutes')->default(60);
            $table->timestamp('last_triggered_at')->nullable();

            // Priority & ordering
            $table->string('severity')->default('info'); // info, warning, critical
            $table->unsignedInteger('priority')->default(0);

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['event_type', 'is_active']);
            $table->index('severity');
        });
    }

    public function down(): void
    {
        $prefix = config('filament-cart.database.table_prefix', 'cart_');

        Schema::dropIfExists($prefix . 'alert_rules');
    }
};
