<?php

declare(strict_types=1);

namespace Database\Seeders;

use AIArmada\Vouchers\States\Active;
use AIArmada\Vouchers\States\Depleted;
use AIArmada\Vouchers\States\Expired;
use AIArmada\Vouchers\States\Paused;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Models\Voucher;
use App\Models\User;
use Illuminate\Database\Seeder;

final class VoucherSeeder extends Seeder
{
    public function run(): void
    {
        // Skip voucher seeding for now due to schema changes
        return;
        $this->createPercentageVouchers();
        $this->createFixedVouchers();
        $this->createLimitedVouchers();
        $this->createExpiredVouchers();
        $this->createUserSpecificVouchers();
    }

    private function createPercentageVouchers(): void
    {
        // 10% off - general discount
        Voucher::create([
            'code' => 'DEMO10',
            'name' => '10% Demo Discount',
            'description' => 'Get 10% off on all products',
            'type' => VoucherType::Percentage,
            'value' => 1000, // 10.00% in basis points
            'currency' => 'MYR',
            'min_cart_value' => 5000, // RM 50 minimum
            'max_discount' => null,
            'usage_limit' => null,
            'usage_limit_per_user' => 1,
            'allows_manual_redemption' => true,
            'status' => Active::class,
            'starts_at' => now()->subDays(30),
            'expires_at' => now()->addDays(60),
        ]);

        // 15% off - new customer
        Voucher::create([
            'code' => 'WELCOME15',
            'name' => 'Welcome 15% Off',
            'description' => 'New customer discount - 15% off first purchase',
            'type' => VoucherType::Percentage,
            'value' => 1500, // 15.00%
            'currency' => 'MYR',
            'min_cart_value' => 10000, // RM 100 minimum
            'max_discount' => 15000, // Max RM 150 discount
            'usage_limit' => 500,
            'usage_limit_per_user' => 1,
            'allows_manual_redemption' => true,
            'status' => Active::class,
            'starts_at' => now()->subDays(7),
            'expires_at' => now()->addDays(90),
        ]);

        // 20% off - flash sale
        Voucher::create([
            'code' => 'FLASH20',
            'name' => 'Flash Sale 20% Off',
            'description' => 'Limited time flash sale discount',
            'type' => VoucherType::Percentage,
            'value' => 2000, // 20.00%
            'currency' => 'MYR',
            'min_cart_value' => 20000, // RM 200 minimum
            'max_discount' => 50000, // Max RM 500 discount
            'usage_limit' => 100,
            'usage_limit_per_user' => 1,
            'allows_manual_redemption' => true,
            'status' => Active::class,
            'starts_at' => now(),
            'expires_at' => now()->addDays(3),
        ]);

        // 25% off - VIP only
        Voucher::create([
            'code' => 'VIP25',
            'name' => 'VIP 25% Discount',
            'description' => 'Exclusive discount for VIP members',
            'type' => VoucherType::Percentage,
            'value' => 2500, // 25.00%
            'currency' => 'MYR',
            'min_cart_value' => 30000, // RM 300 minimum
            'max_discount' => 100000, // Max RM 1000 discount
            'usage_limit' => 50,
            'usage_limit_per_user' => 2,
            'allows_manual_redemption' => true,
            'status' => Active::class,
            'starts_at' => now()->subDays(14),
            'expires_at' => now()->addDays(30),
        ]);
    }

    private function createFixedVouchers(): void
    {
        // RM 10 off
        Voucher::create([
            'code' => 'SAVE10',
            'name' => 'RM 10 Off',
            'description' => 'Get RM 10 off your order',
            'type' => VoucherType::Fixed,
            'value' => 1000, // RM 10 in cents
            'currency' => 'MYR',
            'min_cart_value' => 5000, // RM 50 minimum
            'usage_limit' => null,
            'usage_limit_per_user' => 3,
            'allows_manual_redemption' => true,
            'status' => Active::class,
            'starts_at' => now()->subDays(30),
            'expires_at' => now()->addDays(60),
        ]);

        // RM 50 off
        Voucher::create([
            'code' => 'SAVE50',
            'name' => 'RM 50 Off',
            'description' => 'Get RM 50 off orders above RM 200',
            'type' => VoucherType::Fixed,
            'value' => 5000, // RM 50
            'currency' => 'MYR',
            'min_cart_value' => 20000, // RM 200 minimum
            'usage_limit' => 200,
            'usage_limit_per_user' => 1,
            'allows_manual_redemption' => true,
            'status' => Active::class,
            'starts_at' => now(),
            'expires_at' => now()->addDays(45),
        ]);

        // RM 100 off - big spender
        Voucher::create([
            'code' => 'BIG100',
            'name' => 'RM 100 Off',
            'description' => 'Get RM 100 off orders above RM 500',
            'type' => VoucherType::Fixed,
            'value' => 10000, // RM 100
            'currency' => 'MYR',
            'min_cart_value' => 50000, // RM 500 minimum
            'usage_limit' => 50,
            'usage_limit_per_user' => 1,
            'allows_manual_redemption' => true,
            'status' => Active::class,
            'starts_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        // Free shipping
        Voucher::create([
            'code' => 'FREESHIP',
            'name' => 'Free Shipping',
            'description' => 'Free shipping on all orders',
            'type' => VoucherType::Fixed,
            'value' => 1500, // RM 15 (shipping cost)
            'currency' => 'MYR',
            'min_cart_value' => 10000, // RM 100 minimum
            'usage_limit' => null,
            'usage_limit_per_user' => 5,
            'allows_manual_redemption' => true,
            'status' => Active::class,
            'starts_at' => now()->subDays(7),
            'expires_at' => now()->addDays(60),
            'metadata' => ['voucher_category' => 'shipping'],
        ]);
    }

    private function createLimitedVouchers(): void
    {
        // Almost depleted voucher
        $depleting = Voucher::create([
            'code' => 'LIMITED5',
            'name' => 'Limited 5% Off',
            'description' => 'Limited quantity voucher',
            'type' => VoucherType::Percentage,
            'value' => 500, // 5.00%
            'currency' => 'MYR',
            'usage_limit' => 10,
            'applied_count' => 12,
            'allows_manual_redemption' => true,
            'status' => Active::class,
            'starts_at' => now()->subDays(7),
            'expires_at' => now()->addDays(30),
        ]);

        // Simulate some usage
        for ($i = 0; $i < 8; $i++) {
            $depleting->usages()->create([
                'user_type' => User::class,
                'user_id' => User::inRandomOrder()->first()?->id,
                'order_id' => null,
                'discount_amount' => rand(500, 5000),
                'used_at' => now()->subDays(rand(1, 7)),
            ]);
        }

        // Fully used voucher
        Voucher::create([
            'code' => 'DEPLETED',
            'name' => 'Depleted Voucher',
            'description' => 'This voucher has been fully used',
            'type' => VoucherType::Fixed,
            'value' => 2000,
            'currency' => 'MYR',
            'usage_limit' => 5,
            'applied_count' => 8,
            'allows_manual_redemption' => true,
            'status' => Depleted::class,
            'starts_at' => now()->subDays(30),
            'expires_at' => now()->addDays(30),
        ]);
    }

    private function createExpiredVouchers(): void
    {
        // Expired percentage voucher
        Voucher::create([
            'code' => 'EXPIRED10',
            'name' => 'Expired 10% Off',
            'description' => 'This voucher has expired',
            'type' => VoucherType::Percentage,
            'value' => 1000,
            'currency' => 'MYR',
            'usage_limit' => 100,
            'applied_count' => 45,
            'allows_manual_redemption' => true,
               'status' => Expired::class,
            'starts_at' => now()->subDays(60),
            'expires_at' => now()->subDays(7),
        ]);

        // Future voucher (not started)
        Voucher::create([
            'code' => 'UPCOMING20',
            'name' => 'Upcoming 20% Sale',
            'description' => 'This voucher will be active soon',
            'type' => VoucherType::Percentage,
            'value' => 2000,
            'currency' => 'MYR',
            'min_cart_value' => 15000,
            'usage_limit' => 200,
            'allows_manual_redemption' => true,
            'status' => Paused::class,
            'starts_at' => now()->addDays(7),
            'expires_at' => now()->addDays(37),
        ]);

        // Paused voucher
        Voucher::create([
            'code' => 'PAUSED15',
            'name' => 'Paused 15% Off',
            'description' => 'This voucher is temporarily paused',
            'type' => VoucherType::Percentage,
            'value' => 1500,
            'currency' => 'MYR',
            'usage_limit' => 100,
            'allows_manual_redemption' => true,
               'status' => Paused::class,
            'starts_at' => now()->subDays(14),
            'expires_at' => now()->addDays(14),
        ]);
    }

    private function createUserSpecificVouchers(): void
    {
        $users = User::take(3)->get();

        foreach ($users as $index => $user) {
            // Birthday voucher
            Voucher::create([
                'code' => 'BDAY'.mb_strtoupper(mb_substr($user->name, 0, 3)).($index + 1),
                'name' => 'Birthday Gift for '.$user->name,
                'description' => 'Special birthday discount',
                'type' => VoucherType::Fixed,
                'value' => 5000, // RM 50
                'currency' => 'MYR',
                'usage_limit' => 1,
                'usage_limit_per_user' => 1,
                'allows_manual_redemption' => true,
                'owner_type' => User::class,
                'owner_id' => $user->id,
                'status' => Active::class,
                'starts_at' => now(),
                'expires_at' => now()->addDays(30),
                'metadata' => ['occasion' => 'birthday'],
            ]);
        }
    }
}
