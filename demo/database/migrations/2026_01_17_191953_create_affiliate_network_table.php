<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('affiliates.database.tables.network', 'affiliate_network');

        Schema::create($tableName, function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('ancestor_id');
            $table->foreignUuid('descendant_id');
            $table->integer('depth');

            $table->string('owner_type')->nullable()->index();
            $table->uuid('owner_id')->nullable()->index();
            $table->index(['owner_type', 'owner_id'], 'affiliate_network_owner_idx');

            $table->unique(['ancestor_id', 'descendant_id']);
            $table->index(['descendant_id', 'depth']);
            $table->index(['ancestor_id', 'depth']);
        });
    }

    public function down(): void
    {
        $tableName = config('affiliates.database.tables.network', 'affiliate_network');
        Schema::dropIfExists($tableName);
    }
};
