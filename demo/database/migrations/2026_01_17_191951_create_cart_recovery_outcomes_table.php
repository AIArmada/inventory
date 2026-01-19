<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_recovery_outcomes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('cart_id')->index();
            $table->string('strategy_id')->index();
            $table->boolean('recovered')->default(false)->index();
            $table->unsignedInteger('time_to_recovery_minutes')->nullable();
            $table->unsignedInteger('discount_used_cents')->nullable();
            $table->nullableUuidMorphs('owner');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_recovery_outcomes');
    }
};
