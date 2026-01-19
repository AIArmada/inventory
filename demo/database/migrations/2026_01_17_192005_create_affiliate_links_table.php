<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('affiliates.database.tables.links', 'affiliate_links');

        Schema::create($tableName, function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('affiliate_id');
            $table->foreignUuid('program_id')->nullable();
            $table->string('destination_url');
            $table->string('tracking_url');
            $table->string('short_url')->nullable();
            $table->string('custom_slug')->nullable()->unique();
            $table->string('campaign')->nullable();
            $table->string('sub_id')->nullable();
            $table->string('sub_id_2')->nullable();
            $table->string('sub_id_3')->nullable();
            $table->unsignedBigInteger('clicks')->default(0);
            $table->unsignedBigInteger('conversions')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('affiliate_id');
            $table->index('custom_slug');
            $table->index(['affiliate_id', 'campaign']);
        });
    }

    public function down(): void
    {
        $tableName = config('affiliates.database.tables.links', 'affiliate_links');
        Schema::dropIfExists($tableName);
    }
};
