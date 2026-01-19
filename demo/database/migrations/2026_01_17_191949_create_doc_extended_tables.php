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
        $jsonType = (string) commerce_json_column_type('docs', 'json');

        // Phase 2: Payments
        $paymentsTable = $tables['doc_payments'] ?? $tablePrefix . 'payments';
        if (! Schema::hasTable($paymentsTable)) {
            Schema::create($paymentsTable, function (Blueprint $table) use ($paymentsTable, $jsonType): void {
                $table->uuid('id')->primary();
                $table->nullableUuidMorphs('owner');
                $table->foreignUuid('doc_id');
                $table->decimal('amount', 15, 2);
                $table->string('currency', 3)->default('MYR');
                $table->string('payment_method');
                $table->string('reference')->nullable();
                $table->string('transaction_id')->nullable();
                $table->timestamp('paid_at');
                $table->text('notes')->nullable();
                $table->{$jsonType}('metadata')->nullable();
                $table->timestamps();

                $table->index('doc_id', $paymentsTable . '_doc_id_index');
                $table->index('paid_at', $paymentsTable . '_paid_at_index');
            });
        }

        // Phase 3: Email Templates
        $emailTemplatesTable = $tables['doc_email_templates'] ?? $tablePrefix . 'email_templates';
        if (! Schema::hasTable($emailTemplatesTable)) {
            Schema::create($emailTemplatesTable, function (Blueprint $table) use ($emailTemplatesTable): void {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('slug')->unique($emailTemplatesTable . '_slug_unique');
                $table->string('doc_type');
                $table->string('trigger'); // created, sent, paid, overdue, reminder
                $table->string('subject');
                $table->text('body');
                $table->boolean('is_active')->default(true);
                $table->nullableUuidMorphs('owner');
                $table->timestamps();

                $table->index(['doc_type', 'trigger'], $emailTemplatesTable . '_type_trigger_index');
            });
        }

        // Phase 3: Email Logs
        $emailsTable = $tables['doc_emails'] ?? $tablePrefix . 'emails';
        if (! Schema::hasTable($emailsTable)) {
            Schema::create($emailsTable, function (Blueprint $table) use ($emailsTable, $jsonType): void {
                $table->uuid('id')->primary();
                $table->nullableUuidMorphs('owner');
                $table->foreignUuid('doc_id');
                $table->foreignUuid('doc_email_template_id')->nullable();
                $table->string('recipient_email');
                $table->string('recipient_name')->nullable();
                $table->string('subject');
                $table->text('body');
                $table->string('status')->default('queued'); // queued, sent, failed, bounced
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('opened_at')->nullable();
                $table->timestamp('clicked_at')->nullable();
                $table->unsignedInteger('open_count')->default(0);
                $table->unsignedInteger('click_count')->default(0);
                $table->text('failure_reason')->nullable();
                $table->{$jsonType}('metadata')->nullable();
                $table->timestamps();

                $table->index('doc_id', $emailsTable . '_doc_id_index');
                $table->index('status', $emailsTable . '_status_index');
                $table->index('sent_at', $emailsTable . '_sent_at_index');
            });
        }

        // Phase 4: Versions
        $versionsTable = $tables['doc_versions'] ?? $tablePrefix . 'versions';
        if (! Schema::hasTable($versionsTable)) {
            Schema::create($versionsTable, function (Blueprint $table) use ($versionsTable, $jsonType): void {
                $table->uuid('id')->primary();
                $table->nullableUuidMorphs('owner');
                $table->foreignUuid('doc_id');
                $table->unsignedInteger('version_number');
                $table->{$jsonType}('snapshot');
                $table->text('change_summary')->nullable();
                $table->string('changed_by')->nullable();
                $table->timestamps();

                $table->unique(
                    ['doc_id', 'version_number'],
                    $versionsTable . '_doc_version_unique'
                );
                $table->index('doc_id', $versionsTable . '_doc_id_index');
            });
        }

        // Phase 4: Approvals
        $approvalsTable = $tables['doc_approvals'] ?? $tablePrefix . 'approvals';
        if (! Schema::hasTable($approvalsTable)) {
            Schema::create($approvalsTable, function (Blueprint $table) use ($approvalsTable): void {
                $table->uuid('id')->primary();
                $table->nullableUuidMorphs('owner');
                $table->foreignUuid('doc_id');
                $table->foreignUuid('requested_by');
                $table->foreignUuid('assigned_to')->nullable();
                $table->string('status')->default('pending'); // pending, approved, rejected
                $table->text('comments')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();

                $table->index('doc_id', $approvalsTable . '_doc_id_index');
                $table->index('status', $approvalsTable . '_status_index');
                $table->index('assigned_to', $approvalsTable . '_assigned_to_index');
            });
        }

        // Phase 5: E-Invoice Submissions
        $einvoiceTable = $tables['doc_einvoice_submissions'] ?? $tablePrefix . 'einvoice_submissions';
        if (! Schema::hasTable($einvoiceTable)) {
            Schema::create($einvoiceTable, function (Blueprint $table) use ($einvoiceTable, $jsonType): void {
                $table->uuid('id')->primary();
                $table->nullableUuidMorphs('owner');
                $table->foreignUuid('doc_id');
                $table->string('submission_uid');
                $table->string('document_uuid')->nullable();
                $table->string('long_id')->nullable();
                $table->string('status')->default('pending'); // pending, submitted, processing, completed, failed
                $table->string('validation_status')->nullable(); // valid, invalid, pending
                $table->{$jsonType}('errors')->nullable();
                $table->{$jsonType}('warnings')->nullable();
                $table->longText('ubl_xml')->nullable();
                $table->string('qr_code_url')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('validated_at')->nullable();
                $table->timestamps();

                $table->index('doc_id', $einvoiceTable . '_doc_id_index');
                $table->index('submission_uid', $einvoiceTable . '_submission_uid_index');
                $table->index('status', $einvoiceTable . '_status_index');
            });
        }
    }

    public function down(): void
    {
        $database = config('docs.database', []);
        $tablePrefix = $database['table_prefix'] ?? 'docs_';
        $tables = $database['tables'] ?? [];

        Schema::dropIfExists($tables['doc_einvoice_submissions'] ?? $tablePrefix . 'einvoice_submissions');
        Schema::dropIfExists($tables['doc_approvals'] ?? $tablePrefix . 'approvals');
        Schema::dropIfExists($tables['doc_versions'] ?? $tablePrefix . 'versions');
        Schema::dropIfExists($tables['doc_emails'] ?? $tablePrefix . 'emails');
        Schema::dropIfExists($tables['doc_email_templates'] ?? $tablePrefix . 'email_templates');
        Schema::dropIfExists($tables['doc_payments'] ?? $tablePrefix . 'payments');
    }
};
