<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('pricing.database.tables.price_lists', 'price_lists');

        if (Schema::hasColumn($tableName, 'owner_type')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table): void {
            $table->nullableMorphs('owner');
        });
    }

    public function down(): void
    {
        Schema::table(config('pricing.database.tables.price_lists', 'price_lists'), function (Blueprint $table): void {
            $table->dropMorphs('owner');
        });
    }
};
