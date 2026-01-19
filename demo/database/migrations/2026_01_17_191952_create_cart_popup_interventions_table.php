<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_popup_interventions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('cart_id')->index();
            $table->string('strategy_id')->index();
            $table->boolean('show_discount')->default(false);
            $table->unsignedTinyInteger('discount_percentage')->nullable();
            $table->nullableUuidMorphs('owner');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_popup_interventions');
    }
};
