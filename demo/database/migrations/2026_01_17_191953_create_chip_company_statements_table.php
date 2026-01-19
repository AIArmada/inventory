<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tablePrefix = config('chip.database.table_prefix', 'chip_');

        Schema::create($tablePrefix . 'company_statements', function (Blueprint $table): void {
            // CHIP Collect statement identifiers
            $table->uuid('id')->primary();
            $table->string('type', 32);

            // Core configuration
            $table->string('format', 32);
            $table->string('timezone', 64);
            $table->boolean('is_test')->default(false);
            $table->uuid('company_uid');
            $table->text('query_string')->nullable();
            $table->string('status', 24);
            $table->string('download_url', 500)->nullable();

            // Lifecycle timestamps from API (unix seconds)
            $table->integer('began_on')->nullable();
            $table->integer('finished_on')->nullable();
            $table->integer('created_on');
            $table->integer('updated_on');

            // Owner scoping
            $table->nullableMorphs('owner');

            // Laravel timestamps for internal bookkeeping
            $table->timestamps();

            // Useful indexes for reporting
            $table->index(['status', 'is_test']);
            $table->index('company_uid');
            $table->index('created_on');
        });
    }

    public function down(): void
    {
        $tablePrefix = config('chip.database.table_prefix', 'chip_');

        Schema::dropIfExists($tablePrefix . 'company_statements');
    }
};
