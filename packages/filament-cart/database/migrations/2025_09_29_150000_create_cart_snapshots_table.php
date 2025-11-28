<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_snapshots', function (Blueprint $table): void {
            $jsonType = (string) commerce_json_column_type('cart', 'json');
            $table->uuid('id')->primary();
            $table->string('identifier');
            $table->string('instance')->default('default');
            $table->unsignedInteger('items_count')->default(0);
            $table->unsignedInteger('quantity')->default(0);
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('total')->default(0);
            $table->unsignedBigInteger('savings')->default(0);
            $table->string('currency', 3)->default('USD');
            $table->{$jsonType}('items')->nullable();
            $table->{$jsonType}('conditions')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestamps();

            $table->unique(['identifier', 'instance']);
            $table->index('identifier');
            $table->index('instance');
            $table->index('items_count');
            $table->index('quantity');
            $table->index('subtotal');
            $table->index('total');
            $table->index('savings');
            $table->index('created_at');
            $table->index('updated_at');
        });

        // GIN indexes only work with jsonb in PostgreSQL
        if (commerce_json_column_type('cart', 'json') === 'jsonb') {
            Schema::table('cart_snapshots', function (Blueprint $table): void {
                DB::statement('CREATE INDEX cart_snapshots_items_gin_index ON cart_snapshots USING GIN (items)');
                DB::statement('CREATE INDEX cart_snapshots_conditions_gin_index ON cart_snapshots USING GIN (conditions)');
                DB::statement('CREATE INDEX cart_snapshots_metadata_gin_index ON cart_snapshots USING GIN (metadata)');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_snapshots');
    }
};
