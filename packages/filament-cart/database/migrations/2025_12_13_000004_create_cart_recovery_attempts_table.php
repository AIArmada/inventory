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

        Schema::create($prefix . 'recovery_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('campaign_id');
            $table->foreignUuid('cart_id');
            $table->foreignUuid('template_id')->nullable();

            // Recipient
            $table->string('recipient_email')->nullable();
            $table->string('recipient_phone')->nullable();
            $table->string('recipient_name')->nullable();

            // Status
            $table->string('channel'); // email, sms, push
            $table->string('status')->default('scheduled'); // scheduled, queued, sent, delivered, opened, clicked, converted, failed, bounced, unsubscribed
            $table->integer('attempt_number')->default(1);

            // A/B Test
            $table->boolean('is_control')->default(false);
            $table->boolean('is_variant')->default(false);

            // Offer
            $table->string('discount_code')->nullable();
            $table->integer('discount_value_cents')->nullable();
            $table->boolean('free_shipping_offered')->default(false);
            $table->timestamp('offer_expires_at')->nullable();

            // Cart snapshot (at time of attempt)
            $table->integer('cart_value_cents')->default(0);
            $table->integer('cart_items_count')->default(0);

            // Tracking
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            // Metadata
            $table->string('message_id')->nullable(); // External provider message ID
            $table->json('metadata')->nullable();
            $table->text('failure_reason')->nullable();

            $table->timestamps();

            $table->index(['campaign_id', 'status']);
            $table->index(['cart_id', 'status']);
            $table->index('scheduled_for');
            $table->index('status');
        });
    }

    public function down(): void
    {
        $prefix = config('filament-cart.database.table_prefix', 'cart_');

        Schema::dropIfExists($prefix . 'recovery_attempts');
    }
};
