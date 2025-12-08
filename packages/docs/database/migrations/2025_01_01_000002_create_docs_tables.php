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

        $templatesTable = $tables['doc_templates'] ?? $tablePrefix.'doc_templates';
        $docsTable = $tables['docs'] ?? $tablePrefix.'docs';
        $statusTable = $tables['doc_status_histories'] ?? $tablePrefix.'doc_status_histories';

        Schema::create($templatesTable, function (Blueprint $table) use ($templatesTable): void {
            $jsonType = (string) commerce_json_column_type('docs', 'json');
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique($templatesTable.'_slug_unique');
            $table->text('description')->nullable();
            $table->string('view_name');
            $table->string('doc_type')->default('invoice');
            $table->boolean('is_default')->default(false);
            $table->{$jsonType}('settings')->nullable();
            $table->timestamps();

            $table->index('is_default', $templatesTable.'_is_default_index');
            $table->index('doc_type', $templatesTable.'_doc_type_index');
        });

        Schema::create($docsTable, function (Blueprint $table) use ($docsTable): void {
            $jsonType = (string) commerce_json_column_type('docs', 'json');
            $table->uuid('id')->primary();
            $table->string('doc_number')->unique($docsTable.'_doc_number_unique');
            $table->string('doc_type')->default('invoice');
            $table->foreignUuid('doc_template_id')->nullable();
            $table->nullableUuidMorphs('docable');
            $table->string('status')->default('draft');
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->string('currency', 3)->default('MYR');
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->{$jsonType}('customer_data')->nullable();
            $table->{$jsonType}('company_data')->nullable();
            $table->{$jsonType}('items')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamps();

            $table->index('doc_type', $docsTable.'_doc_type_index');
            $table->index('status', $docsTable.'_status_index');
            $table->index('issue_date', $docsTable.'_issue_date_index');
            $table->index('due_date', $docsTable.'_due_date_index');
        });

        Schema::create($statusTable, function (Blueprint $table) use ($statusTable): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('doc_id');
            $table->string('status');
            $table->text('notes')->nullable();
            $table->string('changed_by')->nullable();
            $table->timestamps();

            $table->index('doc_id', $statusTable.'_doc_id_index');
            $table->index('status', $statusTable.'_status_index');
        });
    }

    public function down(): void
    {
        $database = config('docs.database', []);
        $tablePrefix = $database['table_prefix'] ?? 'docs_';
        $tables = $database['tables'] ?? [];

        $templatesTable = $tables['doc_templates'] ?? $tablePrefix.'doc_templates';
        $docsTable = $tables['docs'] ?? $tablePrefix.'docs';
        $statusTable = $tables['doc_status_histories'] ?? $tablePrefix.'doc_status_histories';

        Schema::dropIfExists($statusTable);
        Schema::dropIfExists($docsTable);
        Schema::dropIfExists($templatesTable);
    }
};
