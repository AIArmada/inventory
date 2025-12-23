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
        /** @var array<string, string> $tables */
        $tables = config('vouchers.database.tables', []);
        $prefix = (string) config('vouchers.database.table_prefix', '');
        $tableName = $tables['voucher_wallets'] ?? $prefix . 'voucher_wallets';

        $vouchersTable = $tables['vouchers'] ?? $prefix . 'vouchers';

        if (Schema::hasColumn($tableName, 'holder_type')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table): void {
            $table->string('holder_type')->nullable()->index();
            $table->uuid('holder_id')->nullable()->index();
            $table->index(['holder_type', 'holder_id'], 'voucher_wallets_holder_idx');
        });

        DB::table($tableName)
            ->whereNotNull('owner_type')
            ->update([
                'holder_type' => DB::raw('owner_type'),
                'holder_id' => DB::raw('owner_id'),
            ]);

        // Re-populate tenant owner boundary from the associated voucher.
        // This prevents legacy rows from becoming global-only (owner = null) after migration.
        DB::table($tableName)
            ->select(['id', 'voucher_id'])
            ->orderBy('id')
            ->chunkById(500, function ($walletRows) use ($tableName, $vouchersTable): void {
                $voucherIds = $walletRows->pluck('voucher_id')->filter()->unique()->values();

                /** @var \Illuminate\Support\Collection<string, array{owner_type: string|null, owner_id: string|null}> $ownersByVoucherId */
                $ownersByVoucherId = DB::table($vouchersTable)
                    ->whereIn('id', $voucherIds)
                    ->get(['id', 'owner_type', 'owner_id'])
                    ->keyBy('id')
                    ->map(fn ($row): array => [
                        'owner_type' => $row->owner_type,
                        'owner_id' => $row->owner_id,
                    ]);

                foreach ($walletRows as $walletRow) {
                    $voucherId = (string) $walletRow->voucher_id;

                    $owner = $ownersByVoucherId->get($voucherId);
                    if ($owner === null) {
                        continue;
                    }

                    DB::table($tableName)
                        ->where('id', $walletRow->id)
                        ->update([
                            'owner_type' => $owner['owner_type'],
                            'owner_id' => $owner['owner_id'],
                        ]);
                }
            });

        Schema::table($tableName, function (Blueprint $table): void {
            $table->dropUnique(['voucher_id', 'owner_type', 'owner_id', 'is_redeemed']);
            $table->unique(['voucher_id', 'holder_type', 'holder_id', 'is_redeemed'], 'voucher_wallets_holder_unique');
        });
    }

    public function down(): void
    {
        //
    }
};
