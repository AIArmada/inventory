<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('affiliates.database.tables.commission_templates', 'affiliate_commission_templates');
        $jsonType = commerce_json_column_type('affiliates');

        Schema::create($tableName, function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->{$jsonType}('rules');
            $table->{$jsonType}('metadata')->nullable();
            $table->string('owner_type')->nullable()->index();
            $table->uuid('owner_id')->nullable()->index();
            $table->timestamps();

            $table->index(['owner_type', 'owner_id'], 'affiliate_commission_templates_owner_idx');
            $table->index(['is_default', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('affiliates.database.tables.commission_templates', 'affiliate_commission_templates'));
    }
};
