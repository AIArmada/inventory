<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_recovery_outcomes', function (Blueprint $table): void {
            $table->nullableUuidMorphs('owner');
        });

        Schema::table('cart_popup_interventions', function (Blueprint $table): void {
            $table->nullableUuidMorphs('owner');
        });
    }

    public function down(): void
    {
        Schema::table('cart_recovery_outcomes', function (Blueprint $table): void {
            $table->dropMorphs('owner');
        });

        Schema::table('cart_popup_interventions', function (Blueprint $table): void {
            $table->dropMorphs('owner');
        });
    }
};
