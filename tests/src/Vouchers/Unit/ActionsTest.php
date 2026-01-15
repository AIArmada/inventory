<?php

declare(strict_types=1);

use AIArmada\Vouchers\Actions\AddVoucherToWallet;
use AIArmada\Vouchers\Actions\CreateVoucher;
use AIArmada\Vouchers\Actions\RecordVoucherUsage;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Exceptions\VoucherNotFoundException;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Models\VoucherUsage;
use AIArmada\Vouchers\Models\VoucherWallet;
use Akaunting\Money\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('CreateVoucher Action', function (): void {
    it('creates a voucher with provided data', function (): void {
        $action = new CreateVoucher;

        $voucher = $action->handle([
            'code' => 'TEST20',
            'type' => VoucherType::Percentage,
            'value' => 20,
            'description' => 'Test voucher',
        ]);

        expect($voucher)->toBeInstanceOf(Voucher::class)
            ->and($voucher->code)->toBe('TEST20')
            ->and($voucher->type)->toBe(VoucherType::Percentage)
            ->and($voucher->value)->toBe(20);
    });

    it('generates a code if not provided', function (): void {
        $action = new CreateVoucher;

        $voucher = $action->handle([
            'type' => VoucherType::Fixed,
            'value' => 1000,
        ]);

        expect($voucher->code)->not->toBeEmpty()
            ->and($voucher->code)->toBeString();
    });

    it('normalizes the code to uppercase', function (): void {
        $action = new CreateVoucher;

        $voucher = $action->handle([
            'code' => 'lowercase-code',
            'type' => VoucherType::Fixed,
            'value' => 500,
        ]);

        expect($voucher->code)->toBe('LOWERCASE-CODE');
    });

    it('creates voucher with all optional fields', function (): void {
        $action = new CreateVoucher;

        $voucher = $action->handle([
            'code' => 'FULL-VOUCHER',
            'type' => VoucherType::Percentage,
            'value' => 15,
            'currency' => 'USD',
            'description' => 'Full featured voucher',
            'max_uses' => 100,
            'max_uses_per_user' => 1,
            'min_order_value' => 5000,
            'max_discount_value' => 2000,
            'starts_at' => now(),
            'expires_at' => now()->addMonth(),
            'metadata' => ['campaign' => 'summer'],
        ]);

        expect($voucher->currency)->toBe('USD')
            ->and($voucher->usage_limit)->toBe(100)
            ->and($voucher->usage_limit_per_user)->toBe(1)
            ->and($voucher->min_cart_value)->toBe(5000)
            ->and($voucher->max_discount)->toBe(2000)
            ->and($voucher->metadata)->toBe(['campaign' => 'summer']);
    });

    it('can be run as action', function (): void {
        $voucher = CreateVoucher::run([
            'code' => 'ACTION-TEST',
            'type' => VoucherType::Fixed,
            'value' => 100,
        ]);

        expect($voucher)->toBeInstanceOf(Voucher::class)
            ->and($voucher->code)->toBe('ACTION-TEST');
    });
});

describe('AddVoucherToWallet Action', function (): void {
    it('adds voucher to wallet', function (): void {
        $voucher = Voucher::create([
            'code' => 'WALLET-TEST',
            'name' => 'Wallet Test Voucher',
            'type' => VoucherType::Fixed,
            'value' => 500,
            'status' => 'active',
        ]);

        $owner = new class extends Model
        {
            public $exists = true;

            protected $table = 'users';

            public function getKey(): string
            {
                return 'user-123';
            }

            public function getMorphClass(): string
            {
                return 'user';
            }
        };

        $action = new AddVoucherToWallet;
        $wallet = $action->handle('WALLET-TEST', $owner);

        expect($wallet)->toBeInstanceOf(VoucherWallet::class)
            ->and($wallet->voucher_id)->toBe($voucher->id)
            ->and($wallet->holder_type)->toBe('user')
            ->and($wallet->holder_id)->toBe('user-123');
    });

    it('returns existing wallet entry if already added', function (): void {
        $voucher = Voucher::create([
            'code' => 'WALLET-DUPE',
            'name' => 'Wallet Dupe Voucher',
            'type' => VoucherType::Fixed,
            'value' => 500,
            'status' => 'active',
        ]);

        $owner = new class extends Model
        {
            public $exists = true;

            protected $table = 'users';

            public function getKey(): string
            {
                return 'user-456';
            }

            public function getMorphClass(): string
            {
                return 'user';
            }
        };

        $action = new AddVoucherToWallet;

        $wallet1 = $action->handle('WALLET-DUPE', $owner);
        $wallet2 = $action->handle('WALLET-DUPE', $owner);

        expect($wallet1->id)->toBe($wallet2->id);
    });

    it('throws exception for non-existent voucher', function (): void {
        $owner = new class extends Model
        {
            public $exists = true;

            protected $table = 'users';

            public function getKey(): string
            {
                return 'user-789';
            }

            public function getMorphClass(): string
            {
                return 'user';
            }
        };

        $action = new AddVoucherToWallet;
        $action->handle('NON-EXISTENT', $owner);
    })->throws(VoucherNotFoundException::class);

    it('adds wallet entry with metadata', function (): void {
        $voucher = Voucher::create([
            'code' => 'META-TEST',
            'name' => 'Meta Test Voucher',
            'type' => VoucherType::Percentage,
            'value' => 10,
            'status' => 'active',
        ]);

        $owner = new class extends Model
        {
            public $exists = true;

            protected $table = 'users';

            public function getKey(): string
            {
                return 'user-meta';
            }

            public function getMorphClass(): string
            {
                return 'user';
            }
        };

        $action = new AddVoucherToWallet;
        $wallet = $action->handle('META-TEST', $owner, ['source' => 'signup']);

        expect($wallet->metadata)->toBe(['source' => 'signup']);
    });

    it('normalizes code before lookup', function (): void {
        $voucher = Voucher::create([
            'code' => 'NORMALIZE-TEST',
            'name' => 'Normalize Test Voucher',
            'type' => VoucherType::Fixed,
            'value' => 100,
            'status' => 'active',
        ]);

        $owner = new class extends Model
        {
            public $exists = true;

            protected $table = 'users';

            public function getKey(): string
            {
                return 'user-norm';
            }

            public function getMorphClass(): string
            {
                return 'user';
            }
        };

        $action = new AddVoucherToWallet;
        $wallet = $action->handle('  normalize-test  ', $owner);

        expect($wallet->voucher_id)->toBe($voucher->id);
    });
});

describe('RecordVoucherUsage Action', function (): void {
    it('records voucher usage', function (): void {
        $voucher = Voucher::create([
            'code' => 'USAGE-TEST',
            'name' => 'Usage Test Voucher',
            'type' => VoucherType::Fixed,
            'value' => 500,
            'status' => 'active',
        ]);

        $action = new RecordVoucherUsage;
        $usage = $action->handle(
            'USAGE-TEST',
            Money::MYR(500)
        );

        expect($usage)->toBeInstanceOf(VoucherUsage::class)
            ->and($usage->voucher_id)->toBe($voucher->id)
            ->and($usage->discount_amount)->toBe(500)
            ->and($usage->currency)->toBe('MYR');

        $voucher->refresh();
        expect($voucher->applied_count)->toBe(1);
    });

    it('records usage with redeemed by user', function (): void {
        $voucher = Voucher::create([
            'code' => 'REDEEM-USER',
            'name' => 'Redeem User Voucher',
            'type' => VoucherType::Percentage,
            'value' => 10,
            'status' => 'active',
        ]);

        $user = new class extends Model
        {
            public $exists = true;

            protected $table = 'users';

            public function getKey(): string
            {
                return 'redeemer-123';
            }

            public function getMorphClass(): string
            {
                return 'user';
            }
        };

        $action = new RecordVoucherUsage;
        $usage = $action->handle(
            'REDEEM-USER',
            Money::MYR(1000),
            'mobile_app',
            ['order_id' => 'order-123'],
            $user,
            'Applied during checkout'
        );

        expect($usage->channel)->toBe('mobile_app')
            ->and($usage->metadata)->toBe(['order_id' => 'order-123'])
            ->and($usage->redeemed_by_type)->toBe('user')
            ->and($usage->redeemed_by_id)->toBe('redeemer-123')
            ->and($usage->notes)->toBe('Applied during checkout');
    });

    it('throws exception for non-existent voucher', function (): void {
        $action = new RecordVoucherUsage;
        $action->handle('NON-EXISTENT-USAGE', Money::MYR(100));
    })->throws(VoucherNotFoundException::class);

    it('accepts pre-loaded voucher model', function (): void {
        $voucher = Voucher::create([
            'code' => 'PRELOAD-USAGE',
            'name' => 'Preload Usage Voucher',
            'type' => VoucherType::Fixed,
            'value' => 200,
            'status' => 'active',
            'applied_count' => 5,
        ]);

        $action = new RecordVoucherUsage;
        $usage = $action->handle(
            'PRELOAD-USAGE',
            Money::MYR(200),
            null,
            null,
            null,
            null,
            $voucher
        );

        expect($usage->voucher_id)->toBe($voucher->id);

        $voucher->refresh();
        expect($voucher->applied_count)->toBe(6);
    });
});
