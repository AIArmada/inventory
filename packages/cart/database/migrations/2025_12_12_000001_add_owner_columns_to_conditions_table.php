<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('cart.database.conditions_table', 'conditions'), function (Blueprint $table): void {
            $table->nullableUuidMorphs('owner');
        });
    }

    public function down(): void
    {
        Schema::table(config('cart.database.conditions_table', 'conditions'), function (Blueprint $table): void {
            $table->dropMorphs('owner');
        });
    }
};
