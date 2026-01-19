<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $modulesTable = config('affiliates.database.tables.training_modules', 'affiliate_training_modules');
        $progressTable = config('affiliates.database.tables.training_progress', 'affiliate_training_progress');
        $jsonType = commerce_json_column_type('affiliates');

        Schema::create($modulesTable, function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('description')->nullable();
            $table->longText('content')->nullable();
            $table->string('type')->default('article');
            $table->string('video_url')->nullable();
            $table->{$jsonType}('resources')->nullable();
            $table->{$jsonType}('quiz')->nullable();
            $table->unsignedInteger('passing_score')->nullable()->default(70);
            $table->unsignedInteger('duration_minutes')->default(10);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('owner_type')->nullable()->index();
            $table->uuid('owner_id')->nullable()->index();
            $table->timestamps();

            $table->index(['owner_type', 'owner_id'], 'affiliate_training_modules_owner_idx');
        });

        Schema::create($progressTable, function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('affiliate_id')->index();
            $table->foreignUuid('module_id')->index();
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->unsignedInteger('last_position')->nullable();
            $table->unsignedTinyInteger('quiz_score')->nullable();
            $table->unsignedInteger('quiz_attempts')->default(0);
            $table->string('certificate_url')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('quiz_passed_at')->nullable();
            $table->timestamps();

            $table->unique(['affiliate_id', 'module_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('affiliates.database.tables.training_progress', 'affiliate_training_progress'));
        Schema::dropIfExists(config('affiliates.database.tables.training_modules', 'affiliate_training_modules'));
    }
};
