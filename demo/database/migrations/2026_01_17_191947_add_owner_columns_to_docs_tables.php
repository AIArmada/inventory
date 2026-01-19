<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds owner boundary columns to core Docs tables.
     */
    public function up(): void
    {
        $database = config('docs.database', []);
        $tablePrefix = $database['table_prefix'] ?? 'docs_';
        $tables = $database['tables'] ?? [];

        $docsTable = $tables['docs'] ?? $tablePrefix . 'docs';
        $templatesTable = $tables['doc_templates'] ?? $tablePrefix . 'doc_templates';

        // Add owner columns to docs table
        if (! Schema::hasColumn($docsTable, 'owner_type')) {
            Schema::table($docsTable, function (Blueprint $table): void {
                $table->string('owner_type')->nullable()->after('id')->index();
                $table->uuid('owner_id')->nullable()->after('owner_type')->index();
            });
        }

        // Add owner columns to doc_templates table
        if (! Schema::hasColumn($templatesTable, 'owner_type')) {
            Schema::table($templatesTable, function (Blueprint $table) use ($templatesTable): void {
                $table->string('owner_type')->nullable()->after('id')->index();
                $table->uuid('owner_id')->nullable()->after('owner_type')->index();

                $table->dropUnique($templatesTable . '_slug_unique');
                $table->unique(['owner_type', 'owner_id', 'slug'], $templatesTable . '_owner_slug_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $database = config('docs.database', []);
        $tablePrefix = $database['table_prefix'] ?? 'docs_';
        $tables = $database['tables'] ?? [];

        $docsTable = $tables['docs'] ?? $tablePrefix . 'docs';
        $templatesTable = $tables['doc_templates'] ?? $tablePrefix . 'doc_templates';

        if (Schema::hasColumn($docsTable, 'owner_type')) {
            Schema::table($docsTable, function (Blueprint $table): void {
                $table->dropColumn(['owner_type', 'owner_id']);
            });
        }

        if (Schema::hasColumn($templatesTable, 'owner_type')) {
            Schema::table($templatesTable, function (Blueprint $table) use ($templatesTable): void {
                $table->dropUnique($templatesTable . '_owner_slug_unique');
                $table->unique(['slug'], $templatesTable . '_slug_unique');
                $table->dropColumn(['owner_type', 'owner_id']);
            });
        }
    }
};
