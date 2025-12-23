<?php

declare(strict_types=1);

use AIArmada\Vouchers\Exceptions\VoucherUsageLimitException;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Services\VoucherService;
use Akaunting\Money\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('enforces global usage_limit during recordUsage', function (): void {
    $voucher = Voucher::create([
        'code' => 'LIMIT1',
        'name' => 'Limit 1',
        'type' => 'percentage',
        'value' => 10,
        'currency' => 'MYR',
        'status' => 'active',
        'usage_limit' => 1,
    ]);

    $service = app(VoucherService::class);

    $service->recordUsage(
        code: $voucher->code,
        discountAmount: Money::MYR(100),
        channel: 'test'
    );

    expect(fn () => $service->recordUsage(
        code: $voucher->code,
        discountAmount: Money::MYR(100),
        channel: 'test'
    ))->toThrow(VoucherUsageLimitException::class);
});

it('enforces usage_limit_per_user during recordUsage', function (): void {
    $voucher = Voucher::create([
        'code' => 'USERLIMIT1',
        'name' => 'User Limit 1',
        'type' => 'percentage',
        'value' => 10,
        'currency' => 'MYR',
        'status' => 'active',
        'usage_limit_per_user' => 1,
    ]);

    $user = new class extends Model
    {
        protected $table = 'users';

        public function getMorphClass()
        {
            return 'User';
        }

        public function getKey()
        {
            return 1;
        }
    };

    $service = app(VoucherService::class);

    $service->recordUsage(
        code: $voucher->code,
        discountAmount: Money::MYR(100),
        channel: 'test',
        redeemedBy: $user
    );

    expect(fn () => $service->recordUsage(
        code: $voucher->code,
        discountAmount: Money::MYR(100),
        channel: 'test',
        redeemedBy: $user
    ))->toThrow(VoucherUsageLimitException::class);
});
