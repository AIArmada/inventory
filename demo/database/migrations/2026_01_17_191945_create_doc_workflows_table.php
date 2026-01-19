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
        $prefix = $database['table_prefix'] ?? 'docs_';
        $tables = $database['tables'] ?? [];
        $jsonColumnType = $database['json_column_type'] ?? 'json';

        $workflowsTable = $tables['workflows'] ?? $prefix . 'workflows';
        $workflowStepsTable = $tables['workflow_steps'] ?? $prefix . 'workflow_steps';

        Schema::create($workflowsTable, function (Blueprint $table) use ($jsonColumnType): void {
            $table->uuid('id')->primary();
            $table->nullableUuidMorphs('owner');
            $table->string('name');
            $table->string('doc_type')->nullable();
            $table->boolean('is_active')->default(true);
            $table->{$jsonColumnType}('rules')->nullable();
            $table->unsignedInteger('priority')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'doc_type', 'priority']);
        });

        Schema::create($workflowStepsTable, function (Blueprint $table) use ($jsonColumnType): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('workflow_id');
            $table->string('name');
            $table->unsignedInteger('order')->default(0);
            $table->string('action_type');
            $table->{$jsonColumnType}('action_config')->nullable();
            $table->{$jsonColumnType}('conditions')->nullable();
            $table->boolean('is_required')->default(true);
            $table->unsignedInteger('timeout_hours')->nullable();
            $table->timestamps();

            $table->index(['workflow_id', 'order']);
        });
    }

    public function down(): void
    {
        $database = config('docs.database', []);
        $prefix = $database['table_prefix'] ?? 'docs_';
        $tables = $database['tables'] ?? [];

        $workflowsTable = $tables['workflows'] ?? $prefix . 'workflows';
        $workflowStepsTable = $tables['workflow_steps'] ?? $prefix . 'workflow_steps';

        Schema::dropIfExists($workflowStepsTable);
        Schema::dropIfExists($workflowsTable);
    }
};
