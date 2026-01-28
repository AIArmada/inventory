<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('customers.database.tables.customers', 'customers'), function (Blueprint $table): void {
            $table->boolean('is_guest')->default(false)->index();
        });
    }

    public function down(): void
    {
        Schema::table(config('customers.database.tables.customers', 'customers'), function (Blueprint $table): void {
            $table->dropColumn('is_guest');
        });
    }
};
