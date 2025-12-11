<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('filament-authz.database.tables.role_templates', 'authz_role_templates');

        Schema::table($tableName, function (Blueprint $table): void {
            $table->nullableMorphs('owner');
        });
    }

    public function down(): void
    {
        $tableName = config('filament-authz.database.tables.role_templates', 'authz_role_templates');

        Schema::table($tableName, function (Blueprint $table): void {
            $table->dropMorphs('owner');
        });
    }
};
