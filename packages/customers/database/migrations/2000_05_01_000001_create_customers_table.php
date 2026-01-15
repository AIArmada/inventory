<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('customers.database.tables.customers', 'customers'), function (Blueprint $table): void {
            $jsonColumnType = config('customers.database.json_column_type', 'json');

            $table->uuid('id')->primary();

            // Owner (for multi-tenancy)
            $table->nullableUuidMorphs('owner');

            // Link to User model
            $table->foreignUuid('user_id')->nullable();

            // Basic info
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('company')->nullable();

            // Status
            $table->string('status')->default('active');

            // Preferences
            $table->boolean('accepts_marketing')->default(true);
            $table->boolean('is_tax_exempt')->default(false);
            $table->string('tax_exempt_reason')->nullable();

            // Timestamps
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();

            // Metadata
            $table->{$jsonColumnType}('metadata')->nullable();

            $table->timestamps();

            // Indexes - email unique per owner for multitenancy
            $table->unique(['owner_type', 'owner_id', 'email'], 'customers_owner_email_unique');
            $table->index(['status', 'accepts_marketing']);
            $table->index('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('customers.database.tables.customers', 'customers'));
    }
};
