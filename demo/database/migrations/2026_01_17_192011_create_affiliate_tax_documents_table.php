<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('affiliates.database.tables.tax_documents', 'affiliate_tax_documents');

        Schema::create($tableName, function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('affiliate_id')->index();
            $table->string('document_type');
            $table->unsignedSmallInteger('tax_year');
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('total_amount_minor');
            $table->string('currency', 3)->default('USD');
            $table->string('document_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['affiliate_id', 'tax_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('affiliates.database.tables.tax_documents', 'affiliate_tax_documents'));
    }
};
