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

        Schema::create($prefix . 'recurring_schedules', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('chip_client_id');
            $table->string('recurring_token_id');
            $table->nullableUuidMorphs('subscriber');
            $table->string('status')->default('active');
            $table->bigInteger('amount_minor');
            $table->string('currency', 3)->default('MYR');
            $table->string('interval')->default('monthly');
            $table->integer('interval_count')->default(1);
            $table->timestamp('next_charge_at')->nullable();
            $table->timestamp('last_charged_at')->nullable();
            $table->integer('failure_count')->default(0);
            $table->integer('max_failures')->default(3);
            $table->timestamp('cancelled_at')->nullable();

            $jsonType = config('chip.database.json_column_type', 'json');
            $table->{$jsonType}('metadata')->nullable();

            $table->nullableUuidMorphs('owner');
            $table->timestamps();

            $table->index(['status', 'next_charge_at']);
            // Note: subscriber index is already created by nullableUuidMorphs
        });
    }

    public function down(): void
    {
        $prefix = config('chip.database.table_prefix', 'chip_');
        Schema::dropIfExists($prefix . 'recurring_schedules');
    }
};
