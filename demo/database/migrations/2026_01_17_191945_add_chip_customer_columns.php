<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'chip_id')) {
                $table->string('chip_id')->nullable()->index();
            }

            if (! Schema::hasColumn('users', 'chip_default_payment_method')) {
                $table->string('chip_default_payment_method')->nullable();
            }

            if (! Schema::hasColumn('users', 'pm_type')) {
                $table->string('pm_type')->nullable();
            }

            if (! Schema::hasColumn('users', 'pm_last_four')) {
                $table->string('pm_last_four', 4)->nullable();
            }

            if (! Schema::hasColumn('users', 'trial_ends_at')) {
                $table->timestamp('trial_ends_at')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'chip_id')) {
                $table->dropIndex(['chip_id']);
                $table->dropColumn('chip_id');
            }

            $columns = ['chip_default_payment_method', 'pm_type', 'pm_last_four', 'trial_ends_at'];

            foreach ($columns as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
