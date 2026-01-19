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
        $databaseConfig = config('filament-cart.database', []);
        $tablePrefix = $databaseConfig['table_prefix'] ?? 'cart_';
        $tables = $databaseConfig['tables'] ?? [];
        $tableName = $tables['snapshots'] ?? $tablePrefix . 'snapshots';
        $jsonType = (string) ($databaseConfig['json_column_type'] ?? commerce_json_column_type('cart', 'json'));

        Schema::create($tableName, function (Blueprint $table) use ($jsonType, $tableName): void {
            $table->uuid('id')->primary();
            $table->string('identifier');
            $table->string('instance')->default('default');
            $table->string('owner_key', 191)->default('global');
            $table->nullableUuidMorphs('owner');
            $table->unsignedInteger('items_count')->default(0);
            $table->unsignedInteger('quantity')->default(0);
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('total')->default(0);
            $table->unsignedBigInteger('savings')->default(0);
            $table->string('currency', 3)->default('USD');
            $table->{$jsonType}('items')->nullable();
            $table->{$jsonType}('conditions')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('checkout_started_at')->nullable();
            $table->timestamp('checkout_abandoned_at')->nullable();
            $table->unsignedTinyInteger('recovery_attempts')->default(0);
            $table->timestamp('recovered_at')->nullable();
            $table->boolean('is_collaborative')->default(false);
            $table->unsignedSmallInteger('collaborator_count')->default(0);
            $table->string('fraud_risk_level', 10)->nullable();
            $table->decimal('fraud_score', 5, 2)->nullable();
            $table->timestamps();

            $table->unique(['owner_key', 'identifier', 'instance'], $tableName . '_owner_key_identifier_instance_unique');
            $table->index('identifier');
            $table->index('instance');
            $table->index('owner_key', $tableName . '_owner_key_index');
            $table->index('items_count');
            $table->index('quantity');
            $table->index('subtotal');
            $table->index('total');
            $table->index('savings');
            $table->index('last_activity_at');
            $table->index('checkout_started_at');
            $table->index('checkout_abandoned_at');
            $table->index('recovered_at');
            $table->index('is_collaborative');
            $table->index('fraud_risk_level');
            $table->index(['checkout_abandoned_at', 'recovered_at'], $tableName . '_abandonment_idx');
            $table->index('created_at');
            $table->index('updated_at');
        });

        if (($databaseConfig['json_column_type'] ?? commerce_json_column_type('cart', 'json')) === 'jsonb') {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                DB::statement("CREATE INDEX {$tableName}_items_gin_index ON {$tableName} USING GIN (items)");
                DB::statement("CREATE INDEX {$tableName}_conditions_gin_index ON {$tableName} USING GIN (conditions)");
                DB::statement("CREATE INDEX {$tableName}_metadata_gin_index ON {$tableName} USING GIN (metadata)");
            });
        }
    }

    public function down(): void
    {
        $databaseConfig = config('filament-cart.database', []);
        $tablePrefix = $databaseConfig['table_prefix'] ?? 'cart_';
        $tables = $databaseConfig['tables'] ?? [];
        $tableName = $tables['snapshots'] ?? $tablePrefix . 'snapshots';

        Schema::dropIfExists($tableName);
    }
};
