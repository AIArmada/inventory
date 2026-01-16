<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jsonColumnType = (string) config('promotions.database.json_column_type', 'json');

        Schema::create((string) config('promotions.database.tables.promotions', 'promotions'), function (Blueprint $table) use ($jsonColumnType): void {
            $table->uuid('id')->primary();
            $table->nullableMorphs('owner');
            $table->string('name');
            $table->string('code')->nullable()->unique();
            $table->text('description')->nullable();

            $table->string('type')->default('percentage');
            $table->unsignedBigInteger('discount_value');

            $table->integer('priority')->default(0);
            $table->boolean('is_stackable')->default(false);
            $table->boolean('is_active')->default(true);

            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_count')->default(0);
            $table->unsignedInteger('per_customer_limit')->nullable();

            $table->unsignedBigInteger('min_purchase_amount')->nullable();
            $table->unsignedInteger('min_quantity')->nullable();

            $table->{$jsonColumnType}('conditions')->nullable();

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->timestamps();

            $table->index(['is_active', 'priority']);
            $table->index(['starts_at', 'ends_at']);
        });

        Schema::create((string) config('promotions.database.tables.promotionables', 'promotionables'), function (Blueprint $table): void {
            $table->foreignUuid('promotion_id');
            $table->uuidMorphs('promotionable');

            $table->primary(['promotion_id', 'promotionable_id', 'promotionable_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists((string) config('promotions.database.tables.promotionables', 'promotionables'));
        Schema::dropIfExists((string) config('promotions.database.tables.promotions', 'promotions'));
    }
};
