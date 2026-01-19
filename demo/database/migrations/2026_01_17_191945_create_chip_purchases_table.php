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
        $tablePrefix = config('chip.database.table_prefix', 'chip_');

        Schema::create($tablePrefix . 'purchases', function (Blueprint $table): void {
            // Core API fields - exact match with CHIP API
            $table->uuid('id')->primary();
            $table->string('type')->default('purchase');
            $table->integer('created_on'); // Unix timestamp as per API
            $table->integer('updated_on'); // Unix timestamp as per API

            // Client details - stored as JSON per API structure
            $jsonType = (string) commerce_json_column_type('chip', 'json');
            $table->{$jsonType}('client'); // Full client object from API

            // Purchase details - stored as JSON per API structure
            $table->{$jsonType}('purchase'); // Full purchase object from API

            // Core identifiers - all UUIDs as per API
            $table->uuid('brand_id');
            $table->uuid('company_id')->nullable();
            $table->uuid('user_id')->nullable();
            $table->uuid('billing_template_id')->nullable();
            $table->uuid('client_id')->nullable();

            // Payment details - stored as JSON when present
            $table->{$jsonType}('payment')->nullable(); // Full payment object from API

            // Additional API objects
            $table->{$jsonType}('issuer_details'); // Company/brand details
            $table->{$jsonType}('transaction_data'); // Payment method specific data
            $table->{$jsonType}('status_history'); // Status change tracking

            // Status and workflow - All official CHIP purchase statuses
            $table->string('status', 32)
                ->default('created')
                ->comment('Backed by AIArmada\\Chip\\Enums\\PurchaseStatus enum.');

            // Analytics denormalized columns for efficient querying
            $table->string('payment_method', 32)->nullable()
                ->comment('Denormalized from transaction_data for analytics queries.');
            $table->integer('total_minor')->default(0)
                ->comment('Denormalized total amount in minor units for analytics.');
            $table->integer('refund_amount_minor')->default(0)
                ->comment('Tracks refund amount in minor units.');
            $table->string('failure_reason')->nullable()
                ->comment('Stores payment failure reason for analytics.');
            $table->timestamp('failed_at')->nullable()
                ->comment('Timestamp when payment failed.');
            $table->timestamp('refunded_at')->nullable()
                ->comment('Timestamp when payment was refunded.');

            // Timestamps
            $table->integer('viewed_on')->nullable();

            // Configuration flags
            $table->boolean('send_receipt')->default(false);
            $table->boolean('is_test')->default(false);
            $table->boolean('is_recurring_token')->default(false);
            $table->uuid('recurring_token')->nullable();
            $table->boolean('skip_capture')->default(false);
            $table->boolean('force_recurring')->default(false);

            // Invoice and reference fields
            $table->string('reference', 128)->nullable();
            $table->string('reference_generated')->nullable();
            $table->text('notes')->nullable();
            $table->string('issued')->nullable(); // ISO 8601 date format
            $table->integer('due')->nullable(); // Unix timestamp

            // Refund information
            $table->string('refund_availability', 32)
                ->default('all')
                ->comment('Backed by CHIP refund availability values.');
            $table->integer('refundable_amount')->default(0);

            // Currency conversion data
            $table->{$jsonType}('currency_conversion')->nullable();

            // Payment methods and restrictions
            $table->{$jsonType}('payment_method_whitelist')->nullable();

            // URLs and redirects
            $table->string('success_redirect', 500)->nullable();
            $table->string('failure_redirect', 500)->nullable();
            $table->string('cancel_redirect', 500)->nullable();
            $table->string('success_callback', 500)->nullable();
            $table->string('invoice_url', 500)->nullable();
            $table->string('checkout_url', 500)->nullable();
            $table->string('direct_post_url', 500)->nullable();

            // Platform and creation details
            $table->string('creator_agent', 32)->nullable();
            $table->string('platform', 16)
                ->default('api')
                ->comment('Expected values: web, api, ios, android, macos, windows.');
            $table->string('product', 48)
                ->default('purchases')
                ->comment('Expected values: purchases, billing_invoices, billing_subscriptions, billing_subscriptions_invoice.');
            $table->string('created_from_ip')->nullable();

            // Additional flags
            $table->boolean('marked_as_paid')->default(false);
            $table->string('order_id')->nullable();

            // Metadata for additional application-specific data
            $table->{$jsonType}('metadata')->nullable();

            // Owner scoping
            $table->nullableMorphs('owner');

            // Laravel timestamps for internal use
            $table->timestamps();

            // Indexes for optimal query performance
            $table->index(['status', 'is_test']);
            $table->index(['brand_id', 'is_test']);
            $table->index(['company_id', 'is_test']);
            $table->index('created_on');
            $table->index('viewed_on');
            $table->index('due');
            $table->index('order_id'); // For order lookup queries
            $table->index(['payment_method', 'status', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        // Optional: create GIN index for metadata when using jsonb on PostgreSQL
        if (
            commerce_json_column_type('chip', 'json') === 'jsonb'
            && Schema::getConnection()->getDriverName() === 'pgsql'
        ) {
            $tableName = $tablePrefix . 'purchases';
            DB::statement("CREATE INDEX IF NOT EXISTS chip_purchases_metadata_gin_index ON \"{$tableName}\" USING GIN (\"metadata\")");

            // Add optimized expression indexes for cart_id lookups (PostgreSQL only)
            DB::statement("
                CREATE INDEX IF NOT EXISTS chip_purchases_metadata_cart_id_idx
                ON {$tablePrefix}purchases ((metadata->>'cart_id'))
            ");

            DB::statement("
                CREATE INDEX IF NOT EXISTS chip_purchases_status_cart_id_idx
                ON {$tablePrefix}purchases (status, ((metadata->>'cart_id')))
            ");
        }
    }

    public function down(): void
    {
        $tablePrefix = config('chip.database.table_prefix', 'chip_');

        Schema::dropIfExists($tablePrefix . 'purchases');
    }
};
