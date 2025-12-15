<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jsonColumnType = (string) config('pricing.json_column_type', 'json');

        Schema::create(config('pricing.tables.promotions', 'promotions'), function (Blueprint $table) use ($jsonColumnType): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('code')->nullable()->unique(); // Optional coupon code
            $table->text('description')->nullable();

            // Discount type
            $table->string('type')->default('percentage'); // 'percentage', 'fixed', 'buy_x_get_y'
            $table->unsignedBigInteger('discount_value'); // Percentage (0-100) or cents

            // Priority for stacking/override
            $table->integer('priority')->default(0);
            $table->boolean('is_stackable')->default(false);
            $table->boolean('is_active')->default(true);

            // Usage limits
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_count')->default(0);
            $table->unsignedInteger('per_customer_limit')->nullable();

            // Minimum requirements
            $table->unsignedBigInteger('min_purchase_amount')->nullable();
            $table->unsignedInteger('min_quantity')->nullable();

            // Conditions (JSON rules)
            $table->{$jsonColumnType}('conditions')->nullable();

            // Scheduling
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['is_active', 'priority']);
            $table->index(['starts_at', 'ends_at']);
        });

        // Pivot table for promotion-product/category relationships
        Schema::create(config('pricing.tables.promotionables', 'promotionables'), function (Blueprint $table): void {
            $table->foreignUuid('promotion_id');
            $table->uuidMorphs('promotionable');

            $table->primary(['promotion_id', 'promotionable_id', 'promotionable_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('pricing.tables.promotionables', 'promotionables'));
        Schema::dropIfExists(config('pricing.tables.promotions', 'promotions'));
    }
};
