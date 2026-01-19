<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $database = config('docs.database', []);
        $tablePrefix = $database['table_prefix'] ?? 'docs_';
        $tables = $database['tables'] ?? [];

        $sequencesTable = $tables['doc_sequences'] ?? $tablePrefix . 'sequences';
        $numbersTable = $tables['sequence_numbers'] ?? $tablePrefix . 'sequence_numbers';

        Schema::create($sequencesTable, function (Blueprint $table) use ($sequencesTable): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('doc_type');
            $table->string('prefix', 20);
            $table->string('format')->default('{PREFIX}-{YYMM}-{NUMBER}');
            $table->string('reset_frequency')->default('yearly');
            $table->unsignedInteger('start_number')->default(1);
            $table->unsignedInteger('increment')->default(1);
            $table->unsignedTinyInteger('padding')->default(6);
            $table->boolean('is_active')->default(true);
            $table->nullableUuidMorphs('owner');
            $table->timestamps();

            $table->index(['doc_type', 'is_active'], $sequencesTable . '_type_active_index');
            $table->index('owner_type', $sequencesTable . '_owner_type_index');
        });

        Schema::create($numbersTable, function (Blueprint $table) use ($numbersTable): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('doc_sequence_id');
            $table->string('period_key', 20);
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(
                ['doc_sequence_id', 'period_key'],
                $numbersTable . '_sequence_period_unique'
            );
            $table->index('period_key', $numbersTable . '_period_key_index');
        });
    }

    public function down(): void
    {
        $database = config('docs.database', []);
        $tablePrefix = $database['table_prefix'] ?? 'docs_';
        $tables = $database['tables'] ?? [];

        $sequencesTable = $tables['doc_sequences'] ?? $tablePrefix . 'sequences';
        $numbersTable = $tables['sequence_numbers'] ?? $tablePrefix . 'sequence_numbers';

        Schema::dropIfExists($numbersTable);
        Schema::dropIfExists($sequencesTable);
    }
};
