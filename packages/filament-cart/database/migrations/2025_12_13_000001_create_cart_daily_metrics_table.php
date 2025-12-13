<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create($this->getTableName(), function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->date('date')->index();
            $table->string('segment')->nullable()->index();

            // Cart counts
            $table->unsignedInteger('carts_created')->default(0);
            $table->unsignedInteger('carts_active')->default(0);
            $table->unsignedInteger('carts_empty')->default(0);
            $table->unsignedInteger('carts_with_items')->default(0);

            // Checkout funnel
            $table->unsignedInteger('checkouts_started')->default(0);
            $table->unsignedInteger('checkouts_completed')->default(0);
            $table->unsignedInteger('checkouts_abandoned')->default(0);

            // Recovery metrics
            $table->unsignedInteger('recovery_emails_sent')->default(0);
            $table->unsignedInteger('carts_recovered')->default(0);
            $table->unsignedBigInteger('recovered_revenue_cents')->default(0);

            // Value metrics
            $table->unsignedBigInteger('total_cart_value_cents')->default(0);
            $table->unsignedBigInteger('average_cart_value_cents')->default(0);
            $table->unsignedInteger('total_items')->default(0);
            $table->decimal('average_items_per_cart', 8, 2)->default(0);

            // Fraud metrics
            $table->unsignedInteger('fraud_alerts_high')->default(0);
            $table->unsignedInteger('fraud_alerts_medium')->default(0);
            $table->unsignedInteger('carts_blocked')->default(0);

            // Collaborative metrics
            $table->unsignedInteger('collaborative_carts')->default(0);
            $table->unsignedInteger('total_collaborators')->default(0);

            $table->timestamps();

            $table->unique(['date', 'segment']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->getTableName());
    }

    private function getTableName(): string
    {
        $prefix = config('filament-cart.database.table_prefix', 'cart_');

        return $prefix . 'daily_metrics';
    }
};
