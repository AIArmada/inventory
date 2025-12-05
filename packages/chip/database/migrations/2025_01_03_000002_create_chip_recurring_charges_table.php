<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('chip.database.table_prefix', 'chip_');

        Schema::create($prefix . 'recurring_charges', function (Blueprint $table) use ($prefix): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('schedule_id');
            $table->string('chip_purchase_id')->nullable();
            $table->bigInteger('amount_minor');
            $table->string('currency', 3)->default('MYR');
            $table->string('status')->default('pending');
            $table->text('failure_reason')->nullable();
            $table->timestamp('attempted_at');
            $table->timestamps();

            $table->index('schedule_id');
            $table->index('chip_purchase_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        $prefix = config('chip.database.table_prefix', 'chip_');
        Schema::dropIfExists($prefix . 'recurring_charges');
    }
};
