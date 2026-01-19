<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $databaseConfig = config('cashier-chip.database', []);
        $tablePrefix = $databaseConfig['table_prefix'] ?? 'cashier_chip_';
        $tables = $databaseConfig['tables'] ?? [];
        $tableName = $tables['subscription_items'] ?? $tablePrefix . 'subscription_items';

        if (Schema::hasTable($tableName)) {
            return;
        }

        Schema::create($tableName, function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->nullableUuidMorphs('owner');
            $table->foreignUuid('subscription_id');
            $table->string('chip_id')->unique();
            $table->string('chip_product')->nullable();
            $table->string('chip_price')->nullable();
            $table->integer('quantity')->nullable();
            $table->integer('unit_amount')->nullable();
            $table->timestamps();

            $table->index(['subscription_id', 'chip_price']);
            $table->index('subscription_id');
            $table->index('chip_product');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $databaseConfig = config('cashier-chip.database', []);
        $tablePrefix = $databaseConfig['table_prefix'] ?? 'cashier_chip_';
        $tables = $databaseConfig['tables'] ?? [];
        $tableName = $tables['subscription_items'] ?? $tablePrefix . 'subscription_items';

        Schema::dropIfExists($tableName);
    }
};
