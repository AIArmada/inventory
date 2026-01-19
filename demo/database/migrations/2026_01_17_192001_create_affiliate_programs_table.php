<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('affiliates.database.tables.programs', 'affiliate_programs');

        Schema::create($tableName, function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('requires_approval')->default(true);
            $table->boolean('is_public')->default(true);
            $table->integer('default_commission_rate_basis_points')->default(1000);
            $table->string('commission_type')->default('percentage');
            $table->integer('cookie_lifetime_days')->default(30);
            $table->string('terms_url')->nullable();

            $table->string('owner_type')->nullable()->index();
            $table->uuid('owner_id')->nullable()->index();

            $jsonType = config('affiliates.database.json_column_type', 'json');
            $table->addColumn($jsonType, 'eligibility_rules')->nullable();
            $table->addColumn($jsonType, 'metadata')->nullable();

            $table->timestamps();

            $table->index(['owner_type', 'owner_id'], 'affiliate_programs_owner_idx');
            $table->index('status');
            $table->index(['is_public', 'status']);
        });
    }

    public function down(): void
    {
        $tableName = config('affiliates.database.tables.programs', 'affiliate_programs');
        Schema::dropIfExists($tableName);
    }
};
