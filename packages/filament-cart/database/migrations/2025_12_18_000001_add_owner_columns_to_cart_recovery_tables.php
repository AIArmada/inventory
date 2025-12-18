<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('filament-cart.database.table_prefix', 'cart_');

        Schema::table($prefix . 'recovery_campaigns', function (Blueprint $table): void {
            $table->nullableUuidMorphs('owner');
        });

        Schema::table($prefix . 'recovery_templates', function (Blueprint $table): void {
            $table->nullableUuidMorphs('owner');
        });

        Schema::table($prefix . 'recovery_attempts', function (Blueprint $table): void {
            $table->nullableUuidMorphs('owner');
        });
    }

    public function down(): void
    {
        $prefix = config('filament-cart.database.table_prefix', 'cart_');

        Schema::table($prefix . 'recovery_attempts', function (Blueprint $table): void {
            $table->dropMorphs('owner');
        });

        Schema::table($prefix . 'recovery_templates', function (Blueprint $table): void {
            $table->dropMorphs('owner');
        });

        Schema::table($prefix . 'recovery_campaigns', function (Blueprint $table): void {
            $table->dropMorphs('owner');
        });
    }
};
