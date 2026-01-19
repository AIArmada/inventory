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
        $tableName = config('affiliates.database.tables.payouts', 'affiliate_payouts');

        if (Schema::hasColumn($tableName, 'payee_type')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table): void {
            $table->string('payee_type')->nullable()->index();
            $table->uuid('payee_id')->nullable()->index();
            $table->index(['payee_type', 'payee_id'], 'affiliate_payouts_payee_idx');
        });

        DB::table($tableName)
            ->whereNotNull('owner_type')
            ->update([
                'payee_type' => DB::raw('owner_type'),
                'payee_id' => DB::raw('owner_id'),
            ]);

        DB::table($tableName)->update([
            'owner_type' => null,
            'owner_id' => null,
        ]);
    }

    public function down(): void
    {
        //
    }
};
