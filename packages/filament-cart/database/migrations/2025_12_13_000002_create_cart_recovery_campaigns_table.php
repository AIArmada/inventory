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

        Schema::create($prefix . 'recovery_campaigns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('draft'); // draft, active, paused, completed, archived
            $table->string('trigger_type'); // abandoned, high_value, exit_intent, custom
            $table->integer('trigger_delay_minutes')->default(60);
            $table->integer('max_attempts')->default(3);
            $table->integer('attempt_interval_hours')->default(24);

            // Targeting
            $table->integer('min_cart_value_cents')->nullable();
            $table->integer('max_cart_value_cents')->nullable();
            $table->integer('min_items')->nullable();
            $table->integer('max_items')->nullable();
            $table->json('target_segments')->nullable();
            $table->json('exclude_segments')->nullable();

            // Strategy
            $table->string('strategy')->default('email'); // email, sms, push, multi_channel
            $table->boolean('offer_discount')->default(false);
            $table->string('discount_type')->nullable(); // percentage, fixed
            $table->integer('discount_value')->nullable();
            $table->boolean('offer_free_shipping')->default(false);
            $table->integer('urgency_hours')->nullable();

            // A/B Testing
            $table->boolean('ab_testing_enabled')->default(false);
            $table->integer('ab_test_split_percent')->default(50);
            $table->foreignUuid('control_template_id')->nullable();
            $table->foreignUuid('variant_template_id')->nullable();

            // Performance
            $table->integer('total_targeted')->default(0);
            $table->integer('total_sent')->default(0);
            $table->integer('total_opened')->default(0);
            $table->integer('total_clicked')->default(0);
            $table->integer('total_recovered')->default(0);
            $table->integer('recovered_revenue_cents')->default(0);

            // Schedule
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('last_run_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'trigger_type']);
            $table->index('starts_at');
            $table->index('ends_at');
        });
    }

    public function down(): void
    {
        $prefix = config('filament-cart.database.table_prefix', 'cart_');

        Schema::dropIfExists($prefix . 'recovery_campaigns');
    }
};
