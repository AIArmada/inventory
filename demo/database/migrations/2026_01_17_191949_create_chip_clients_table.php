<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tablePrefix = config('chip.database.table_prefix', 'chip_');

        Schema::create($tablePrefix . 'clients', function (Blueprint $table): void {
            // Core API fields - Client structure from CHIP Collect API
            $table->uuid('id')->primary();
            $table->string('type')->default('client');
            $table->integer('created_on'); // Unix timestamp as per API
            $table->integer('updated_on'); // Unix timestamp as per API

            // Required field - email address (max 254 chars)
            $table->string('email', 254);

            // Contact information
            $table->string('phone', 32)->nullable(); // Format: +<country_code> <number>
            $table->string('full_name', 128)->nullable(); // Name and surname
            $table->string('personal_code', 32)->nullable(); // Personal ID code

            // Billing address
            $table->string('street_address', 128)->nullable();
            $table->string('country', 2)->nullable(); // ISO 3166-1 alpha-2 (e.g. 'GB')
            $table->string('city', 128)->nullable();
            $table->string('zip_code', 32)->nullable();
            $table->string('state', 128)->nullable();

            // Shipping address
            $table->string('shipping_street_address', 128)->nullable();
            $table->string('shipping_country', 2)->nullable(); // ISO 3166-1 alpha-2
            $table->string('shipping_city', 128)->nullable();
            $table->string('shipping_zip_code', 32)->nullable();
            $table->string('shipping_state', 128)->nullable();

            // Email notifications
            $jsonType = (string) commerce_json_column_type('chip', 'json');
            $table->{$jsonType}('cc')->nullable(); // Carbon copy email addresses
            $table->{$jsonType}('bcc')->nullable(); // Blind carbon copy email addresses

            // Company information
            $table->string('legal_name', 128)->nullable(); // Legal company name
            $table->string('brand_name', 128)->nullable(); // Company brand name
            $table->string('registration_number', 32)->nullable(); // Company registration
            $table->string('tax_number', 32)->nullable(); // Tax registration number

            // Banking information
            $table->string('bank_account', 34)->nullable(); // Bank account (e.g. IBAN)
            $table->string('bank_code', 11)->nullable(); // SWIFT/BIC code

            // Owner scoping
            $table->nullableMorphs('owner');

            // Laravel timestamps for internal use
            $table->timestamps();

            // Indexes for optimal query performance
            $table->unique('email'); // Email must be unique
            $table->index('phone');
            $table->index(['country', 'city']);
            $table->index('created_on');
        });
    }

    public function down(): void
    {
        $tablePrefix = config('chip.database.table_prefix', 'chip_');

        Schema::dropIfExists($tablePrefix . 'clients');
    }
};
