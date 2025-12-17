<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Actions;

use AIArmada\Vouchers\Models\Voucher as VoucherModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Create a new voucher.
 */
final class CreateVoucher
{
    use AsAction;

    /**
     * Create a new voucher with the given data.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): VoucherModel
    {
        return DB::transaction(function () use ($data): VoucherModel {
            $code = $data['code'] ?? $this->generateCode();

            $voucher = VoucherModel::create([
                'code' => $this->normalizeCode($code),
                'name' => $data['name'] ?? $this->normalizeCode($code),
                'type' => $data['type'],
                'value' => $data['value'],
                'currency' => $data['currency'] ?? config('vouchers.default_currency', 'MYR'),
                'description' => $data['description'] ?? null,
                'usage_limit' => $data['max_uses'] ?? $data['usage_limit'] ?? null,
                'usage_limit_per_user' => $data['max_uses_per_user'] ?? $data['usage_limit_per_user'] ?? null,
                'min_cart_value' => $data['min_order_value'] ?? $data['min_cart_value'] ?? null,
                'max_discount' => $data['max_discount_value'] ?? $data['max_discount'] ?? null,
                'starts_at' => $data['starts_at'] ?? null,
                'expires_at' => $data['expires_at'] ?? null,
                'metadata' => $data['metadata'] ?? null,
                'owner_type' => $data['owner_type'] ?? null,
                'owner_id' => $data['owner_id'] ?? null,
            ]);

            return $voucher;
        });
    }

    private function generateCode(): string
    {
        /** @var string $prefix */
        $prefix = (string) config('vouchers.code.prefix', '');
        $length = (int) config('vouchers.code.length', 8);

        do {
            $code = $this->normalizeCode($prefix . Str::random($length));
        } while (VoucherModel::where('code', $code)->exists());

        return $code;
    }

    private function normalizeCode(string $code): string
    {
        $normalized = mb_trim($code);

        if (config('vouchers.code.auto_uppercase', true)) {
            return Str::upper($normalized);
        }

        return $normalized;
    }
}
