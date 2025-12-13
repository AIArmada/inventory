<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('customers.tables.notes', 'customer_notes'), function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignUuid('customer_id');

            // Who created the note
            $table->foreignId('created_by')->nullable();

            // Note content
            $table->text('content');

            // Visibility
            $table->boolean('is_internal')->default(true);
            $table->boolean('is_pinned')->default(false);

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['customer_id', 'is_pinned']);
            $table->index(['customer_id', 'is_internal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('customers.tables.notes', 'customer_notes'));
    }
};
