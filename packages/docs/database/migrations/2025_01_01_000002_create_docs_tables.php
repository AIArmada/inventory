<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doc_templates', function (Blueprint $table): void {
            $jsonType = (string) commerce_json_column_type('docs', 'json');
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('view_name');
            $table->string('doc_type')->default('invoice');
            $table->boolean('is_default')->default(false);
            $table->{$jsonType}('settings')->nullable();
            $table->timestamps();

            $table->index('is_default');
            $table->index('doc_type');
        });

        Schema::create('docs', function (Blueprint $table): void {
            $jsonType = (string) commerce_json_column_type('docs', 'json');
            $table->uuid('id')->primary();
            $table->string('doc_number')->unique();
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

            $table->index('doc_type');
            $table->index('status');
            $table->index('issue_date');
            $table->index('due_date');
        });

        Schema::create('doc_status_histories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('doc_id');
            $table->string('status');
            $table->text('notes')->nullable();
            $table->string('changed_by')->nullable();
            $table->timestamps();

            $table->index('doc_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doc_status_histories');
        Schema::dropIfExists('docs');
        Schema::dropIfExists('doc_templates');
    }
};
