<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $database = config('docs.database', []);
        $tablePrefix = $database['table_prefix'] ?? 'docs_';
        $tables = $database['tables'] ?? [];

        $docsTable = $tables['docs'] ?? $tablePrefix . 'docs';
        $workflowsTable = $tables['workflows'] ?? $tablePrefix . 'workflows';
        $sequencesTable = $tables['doc_sequences'] ?? $tablePrefix . 'sequences';

        $this->ensureOwnerColumns($tables['doc_status_histories'] ?? $tablePrefix . 'doc_status_histories');
        $this->ensureOwnerColumns($tables['doc_payments'] ?? $tablePrefix . 'payments');
        $this->ensureOwnerColumns($tables['doc_emails'] ?? $tablePrefix . 'emails');
        $this->ensureOwnerColumns($tables['doc_versions'] ?? $tablePrefix . 'versions');
        $this->ensureOwnerColumns($tables['doc_approvals'] ?? $tablePrefix . 'approvals');
        $this->ensureOwnerColumns($tables['doc_einvoice_submissions'] ?? $tablePrefix . 'einvoice_submissions');
        $this->ensureOwnerColumns($tables['workflow_steps'] ?? $tablePrefix . 'workflow_steps');
        $this->ensureOwnerColumns($tables['sequence_numbers'] ?? $tablePrefix . 'sequence_numbers');

        $this->backfillOwnerFromParent(
            childTable: $tables['doc_status_histories'] ?? $tablePrefix . 'doc_status_histories',
            parentTable: $docsTable,
            parentIdColumn: 'id',
            childForeignKeyColumn: 'doc_id',
        );
        $this->backfillOwnerFromParent(
            childTable: $tables['doc_payments'] ?? $tablePrefix . 'payments',
            parentTable: $docsTable,
            parentIdColumn: 'id',
            childForeignKeyColumn: 'doc_id',
        );
        $this->backfillOwnerFromParent(
            childTable: $tables['doc_emails'] ?? $tablePrefix . 'emails',
            parentTable: $docsTable,
            parentIdColumn: 'id',
            childForeignKeyColumn: 'doc_id',
        );
        $this->backfillOwnerFromParent(
            childTable: $tables['doc_versions'] ?? $tablePrefix . 'versions',
            parentTable: $docsTable,
            parentIdColumn: 'id',
            childForeignKeyColumn: 'doc_id',
        );
        $this->backfillOwnerFromParent(
            childTable: $tables['doc_approvals'] ?? $tablePrefix . 'approvals',
            parentTable: $docsTable,
            parentIdColumn: 'id',
            childForeignKeyColumn: 'doc_id',
        );
        $this->backfillOwnerFromParent(
            childTable: $tables['doc_einvoice_submissions'] ?? $tablePrefix . 'einvoice_submissions',
            parentTable: $docsTable,
            parentIdColumn: 'id',
            childForeignKeyColumn: 'doc_id',
        );
        $this->backfillOwnerFromParent(
            childTable: $tables['workflow_steps'] ?? $tablePrefix . 'workflow_steps',
            parentTable: $workflowsTable,
            parentIdColumn: 'id',
            childForeignKeyColumn: 'workflow_id',
        );
        $this->backfillOwnerFromParent(
            childTable: $tables['sequence_numbers'] ?? $tablePrefix . 'sequence_numbers',
            parentTable: $sequencesTable,
            parentIdColumn: 'id',
            childForeignKeyColumn: 'doc_sequence_id',
        );
    }

    private function ensureOwnerColumns(string $tableName): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        $hasOwnerType = Schema::hasColumn($tableName, 'owner_type');
        $hasOwnerId = Schema::hasColumn($tableName, 'owner_id');

        if ($hasOwnerType && $hasOwnerId) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName, $hasOwnerType, $hasOwnerId): void {
            if (! $hasOwnerType) {
                $table->string('owner_type')->nullable()->after('id');
            }

            if (! $hasOwnerId) {
                $table->uuid('owner_id')->nullable()->after('owner_type');
            }

            // Avoid relying on morphs() auto index names; keep them explicit and stable.
            $table->index(['owner_type', 'owner_id'], $tableName . '_owner_index');
        });
    }

    private function backfillOwnerFromParent(
        string $childTable,
        string $parentTable,
        string $parentIdColumn,
        string $childForeignKeyColumn,
    ): void {
        if (! Schema::hasTable($childTable) || ! Schema::hasTable($parentTable)) {
            return;
        }

        if (! Schema::hasColumn($childTable, 'owner_type') || ! Schema::hasColumn($childTable, 'owner_id')) {
            return;
        }

        DB::table($childTable)
            ->whereNull('owner_type')
            ->whereNull('owner_id')
            ->orderBy('id')
            ->chunk(500, function ($rows) use ($childTable, $parentTable, $parentIdColumn, $childForeignKeyColumn): void {
                $foreignIds = [];

                foreach ($rows as $row) {
                    $foreignId = $row->{$childForeignKeyColumn} ?? null;
                    if (is_string($foreignId) && $foreignId !== '') {
                        $foreignIds[] = $foreignId;
                    }
                }

                $foreignIds = array_values(array_unique($foreignIds));

                if ($foreignIds === []) {
                    return;
                }

                $parents = DB::table($parentTable)
                    ->whereIn($parentIdColumn, $foreignIds)
                    ->select([$parentIdColumn, 'owner_type', 'owner_id'])
                    ->get();

                $ownerByParentId = [];
                foreach ($parents as $parent) {
                    $ownerByParentId[$parent->{$parentIdColumn}] = [
                        'owner_type' => $parent->owner_type ?? null,
                        'owner_id' => $parent->owner_id ?? null,
                    ];
                }

                foreach ($rows as $row) {
                    $foreignId = $row->{$childForeignKeyColumn} ?? null;
                    if (! is_string($foreignId) || $foreignId === '') {
                        continue;
                    }

                    $owner = $ownerByParentId[$foreignId] ?? null;
                    if ($owner === null) {
                        continue;
                    }

                    DB::table($childTable)
                        ->where('id', $row->id)
                        ->update([
                            'owner_type' => $owner['owner_type'],
                            'owner_id' => $owner['owner_id'],
                        ]);
                }
            });
    }
};
