<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = config('inventory.database.tables', []);
        $prefix = config('inventory.database.table_prefix', 'inventory_');

        $tableName = $tables['backorders'] ?? $prefix . 'backorders';

        Schema::create($tableName, function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuidMorphs('inventoryable');
            $table->foreignUuid('location_id')->nullable();
            $table->foreignUuid('order_id')->nullable();
            $table->foreignUuid('customer_id')->nullable();
            $table->integer('quantity_requested');
            $table->integer('quantity_fulfilled')->default(0);
            $table->integer('quantity_cancelled')->default(0);
            $table->string('status')->default('pending');
            $table->string('priority')->default('normal');
            $table->timestamp('requested_at');
            $table->timestamp('promised_at')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('notes')->nullable();
            $jsonType = config('inventory.database.json_column_type', 'json');
            $table->addColumn($jsonType, 'metadata')->nullable();
            $table->timestamps();

            $table->index(['inventoryable_type', 'inventoryable_id', 'status']);
            $table->index(['status', 'priority']);
            $table->index('requested_at');
            $table->index('promised_at');
        });
    }

    public function down(): void
    {
        $tables = config('inventory.database.tables', []);
        $prefix = config('inventory.database.table_prefix', 'inventory_');
        $tableName = $tables['backorders'] ?? $prefix . 'backorders';

        Schema::dropIfExists($tableName);
    }
};
