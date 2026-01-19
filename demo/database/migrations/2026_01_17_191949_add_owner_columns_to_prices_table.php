<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = (string) config('pricing.database.tables.prices', 'prices');

        if (Schema::hasColumn($tableName, 'owner_type')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table): void {
            $table->nullableMorphs('owner');
        });
    }

    public function down(): void
    {
        Schema::table((string) config('pricing.database.tables.prices', 'prices'), function (Blueprint $table): void {
            $table->dropMorphs('owner');
        });
    }
};
