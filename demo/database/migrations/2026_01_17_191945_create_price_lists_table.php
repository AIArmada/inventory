<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('pricing.database.tables.price_lists', 'price_lists'), function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('currency', 3)->default('MYR');
            $table->integer('priority')->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);

            // Optional: Link to customer or segment
            $table->foreignUuid('customer_id')->nullable();
            $table->foreignUuid('segment_id')->nullable();

            // Scheduling
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->nullableMorphs('owner');

            $table->timestamps();

            // Indexes
            $table->index(['is_active', 'priority']);
            $table->index(['starts_at', 'ends_at']);
            $table->index('customer_id');
            $table->index('segment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('pricing.database.tables.price_lists', 'price_lists'));
    }
};
