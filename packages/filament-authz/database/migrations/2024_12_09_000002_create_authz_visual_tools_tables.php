<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $tablePrefix = config('filament-authz.database.table_prefix', 'authz_');

        // Identity Provider mappings table
        Schema::create($tablePrefix . 'identity_provider_mappings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('provider_type'); // ldap, saml, oauth
            $table->string('provider_name');
            $table->string('external_group');
            $table->string('local_role');
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['provider_type', 'provider_name', 'external_group'], 'idp_mapping_unique');
        });
    }

    public function down(): void
    {
        $tablePrefix = config('filament-authz.database.table_prefix', 'authz_');

        Schema::dropIfExists($tablePrefix . 'identity_provider_mappings');
    }
};
