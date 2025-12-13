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

        Schema::create($prefix . 'recovery_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type'); // email, sms, push
            $table->string('status')->default('draft'); // draft, active, archived
            $table->boolean('is_default')->default(false);

            // Email content
            $table->string('email_subject')->nullable();
            $table->string('email_preheader')->nullable();
            $table->text('email_body_html')->nullable();
            $table->text('email_body_text')->nullable();
            $table->string('email_from_name')->nullable();
            $table->string('email_from_email')->nullable();

            // SMS content
            $table->text('sms_body')->nullable();

            // Push notification content
            $table->string('push_title')->nullable();
            $table->text('push_body')->nullable();
            $table->string('push_icon')->nullable();
            $table->string('push_action_url')->nullable();

            // Variables available: {{customer_name}}, {{cart_url}}, {{cart_items}}, {{cart_total}}, {{discount_code}}, {{expiry_time}}

            // Performance (for tracking template effectiveness)
            $table->integer('times_used')->default(0);
            $table->integer('times_opened')->default(0);
            $table->integer('times_clicked')->default(0);
            $table->integer('times_converted')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'status']);
            $table->index('is_default');
        });
    }

    public function down(): void
    {
        $prefix = config('filament-cart.database.table_prefix', 'cart_');

        Schema::dropIfExists($prefix . 'recovery_templates');
    }
};
