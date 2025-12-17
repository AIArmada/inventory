<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('inventory.table_names.valuation_snapshots', 'inventory_valuation_snapshots'), function (Blueprint $table): void {
            $table->nullableUuidMorphs('owner');

            $table->index(['owner_type', 'owner_id'], 'inventory_valuation_snapshots_owner_idx');
        });
    }

    public function down(): void
    {
        Schema::table(config('inventory.table_names.valuation_snapshots', 'inventory_valuation_snapshots'), function (Blueprint $table): void {
            $table->dropIndex('inventory_valuation_snapshots_owner_idx');
            $table->dropMorphs('owner');
        });
    }
};
