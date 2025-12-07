<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_authorizations', function (Blueprint $table): void {
            $table->id();
            $table->morphs('owner');

            $table->string('rma_number')->unique();
            $table->foreignId('original_shipment_id')
                ->nullable()
                ->constrained('shipments')
                ->nullOnDelete();

            $table->string('order_reference')->nullable();
            $table->foreignId('customer_id')->nullable();

            $table->string('status', 50)->default('pending'); // pending, approved, rejected, received, completed, cancelled
            $table->string('type', 50); // refund, exchange, store_credit
            $table->string('reason', 100);
            $table->text('reason_details')->nullable();

            $table->foreignId('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['owner_id', 'owner_type', 'status']);
        });

        Schema::create('return_authorization_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('return_authorization_id')
                ->constrained('return_authorizations')
                ->cascadeOnDelete();

            $table->nullableMorphs('original_item');

            $table->string('sku')->nullable();
            $table->string('name');
            $table->unsignedInteger('quantity_requested')->default(1);
            $table->unsignedInteger('quantity_approved')->default(0);
            $table->unsignedInteger('quantity_received')->default(0);

            $table->string('reason', 100)->nullable();
            $table->string('condition', 50)->nullable(); // unused, opened, damaged

            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_authorization_items');
        Schema::dropIfExists('return_authorizations');
    }
};
