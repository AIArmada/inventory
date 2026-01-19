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
        $table = config('cart.database.table', 'carts');
        $jsonType = (string) commerce_json_column_type('cart', 'json');

        if (Schema::hasColumn($table, 'is_collaborative')) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($jsonType): void {
            $table->boolean('is_collaborative')->default(false)->after('metadata');
            $table->foreignUuid('owner_user_id')->nullable()->after('is_collaborative');
            $table->{$jsonType}('collaborators')->nullable()->after('owner_user_id');
            $table->integer('max_collaborators')->default(5)->after('collaborators');
            $table->string('collaboration_mode', 20)->default('edit')->after('max_collaborators');
            $table->string('share_token', 64)->nullable()->unique()->after('collaboration_mode');
            $table->timestamp('share_expires_at')->nullable()->after('share_token');
            $table->bigInteger('crdt_version')->default(0)->after('share_expires_at');
            $table->{$jsonType}('crdt_vector_clock')->nullable()->after('crdt_version');

            $table->index('is_collaborative', 'idx_carts_collaborative');
            $table->index('owner_user_id', 'idx_carts_owner');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $table = config('cart.database.table', 'carts');

        Schema::table($table, function (Blueprint $table): void {
            $table->dropIndex('idx_carts_collaborative');
            $table->dropIndex('idx_carts_owner');

            $table->dropColumn([
                'is_collaborative',
                'owner_user_id',
                'collaborators',
                'max_collaborators',
                'collaboration_mode',
                'share_token',
                'share_expires_at',
                'crdt_version',
                'crdt_vector_clock',
            ]);
        });
    }
};
