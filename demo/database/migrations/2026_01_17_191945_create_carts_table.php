<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(config('cart.database.table', 'carts'), function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('identifier')->index();
            $table->string('owner_type')->default('');
            $table->string('owner_id')->default('');
            $table->string('instance')->default('default')->index();
            $jsonType = (string) commerce_json_column_type('cart', 'json');
            $table->{$jsonType}('items')->nullable();
            $table->{$jsonType}('conditions')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->boolean('is_collaborative')->default(false);
            $table->foreignUuid('owner_user_id')->nullable();
            $table->{$jsonType}('collaborators')->nullable();
            $table->integer('max_collaborators')->default(5);
            $table->string('collaboration_mode', 20)->default('edit');
            $table->string('share_token', 64)->nullable()->unique();
            $table->timestamp('share_expires_at')->nullable();
            $table->bigInteger('crdt_version')->default(0);
            $table->{$jsonType}('crdt_vector_clock')->nullable();
            $table->integer('version')->default(1)->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['owner_type', 'owner_id', 'identifier', 'instance']);
            $table->index('is_collaborative', 'idx_carts_collaborative');
            $table->index('owner_user_id', 'idx_carts_owner');
        });

        // Optional: create GIN indexes when using jsonb on PostgreSQL
        $tableName = config('cart.database.table', 'carts');
        if (
            commerce_json_column_type('cart', 'json') === 'jsonb'
            && Schema::getConnection()->getDriverName() === 'pgsql'
        ) {
            DB::statement("CREATE INDEX IF NOT EXISTS carts_items_gin_index ON \"{$tableName}\" USING GIN (\"items\")");
            DB::statement("CREATE INDEX IF NOT EXISTS carts_conditions_gin_index ON \"{$tableName}\" USING GIN (\"conditions\")");
            DB::statement("CREATE INDEX IF NOT EXISTS carts_metadata_gin_index ON \"{$tableName}\" USING GIN (\"metadata\")");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('cart.database.table', 'carts'));
    }
};
