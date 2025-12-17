<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table((string) config('tax.database.tables.tax_rates', 'tax_rates'), function (Blueprint $table): void {
            $table->nullableMorphs('owner');
            $table->boolean('is_shipping')->default(true);
            $table->text('description')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table((string) config('tax.database.tables.tax_rates', 'tax_rates'), function (Blueprint $table): void {
            $table->dropColumn(['is_shipping', 'description']);
            $table->dropMorphs('owner');
        });
    }
};
