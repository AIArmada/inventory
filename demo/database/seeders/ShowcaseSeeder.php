<?php

declare(strict_types=1);

namespace Database\Seeders;

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\ConversionStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Cart\Facades\Cart;
use AIArmada\Vouchers\Enums\VoucherStatus;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Models\VoucherUsage;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * 🎭 THE ULTIMATE SHOWCASE SEEDER
 *
 * This seeder creates a complete, impressive demo that showcases
 * the full power of the AIArmada Commerce ecosystem.
 */
final class ShowcaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('');
        $this->command->info('🎭 ═══════════════════════════════════════════════════════════════');
        $this->command->info('   COMMERCE SHOWCASE - Building Your Empire...');
        $this->command->info('═══════════════════════════════════════════════════════════════ 🎭');
        $this->command->info('');

        $this->seedVouchers();
        $this->seedAffiliates();
        $this->seedStockTransactions();
        $this->seedCarts();

        $this->command->info('');
        $this->command->info('✨ ═══════════════════════════════════════════════════════════════');
        $this->command->info('   SHOWCASE COMPLETE - Your Commerce Empire Awaits!');
        $this->command->info('═══════════════════════════════════════════════════════════════ ✨');
        $this->command->info('');
    }

    private function seedVouchers(): void
    {
        $this->command->info('💳 Creating Voucher Campaigns...');

        // Flash Sales - High Impact
        $flashSaleVouchers = [
            [
                'code' => 'FLASH50',
                'name' => '🔥 Flash Sale - 50% OFF!',
                'description' => 'Massive 50% discount - Limited time only! Use before midnight to save big.',
                'type' => VoucherType::Percentage,
                'value' => 5000, // 50%
                'min_cart_value' => 10000,
                'max_discount' => 50000,
                'usage_limit' => 100,
                'status' => VoucherStatus::Active,
                'starts_at' => now(),
                'expires_at' => now()->addHours(6),
            ],
            [
                'code' => 'WEEKEND30',
                'name' => '🎉 Weekend Special - 30% OFF',
                'description' => 'Celebrate the weekend with 30% off everything!',
                'type' => VoucherType::Percentage,
                'value' => 3000, // 30%
                'min_cart_value' => 15000,
                'max_discount' => 30000,
                'usage_limit' => 200,
                'status' => VoucherStatus::Active,
                'starts_at' => now()->startOfWeek()->addDays(5),
                'expires_at' => now()->endOfWeek(),
            ],
        ];

        // Loyalty & Retention
        $loyaltyVouchers = [
            [
                'code' => 'LOYAL100',
                'name' => '👑 VIP Loyalty Reward',
                'description' => 'Thank you for being a loyal customer! Enjoy RM100 off your next purchase.',
                'type' => VoucherType::Fixed,
                'value' => 10000,
                'min_cart_value' => 20000,
                'usage_limit' => 500,
                'usage_limit_per_user' => 1,
                'status' => VoucherStatus::Active,
                'starts_at' => now()->subMonth(),
                'expires_at' => now()->addMonths(3),
            ],
            [
                'code' => 'BIRTHDAY25',
                'name' => '🎂 Birthday Special',
                'description' => 'Happy Birthday! Enjoy 25% off as our gift to you.',
                'type' => VoucherType::Percentage,
                'value' => 2500,
                'max_discount' => 50000,
                'usage_limit_per_user' => 1,
                'status' => VoucherStatus::Active,
                'starts_at' => now()->subYear(),
                'expires_at' => now()->addYear(),
            ],
        ];

        // New Customer Acquisition
        $acquisitionVouchers = [
            [
                'code' => 'WELCOME2024',
                'name' => '🌟 New Customer Welcome',
                'description' => 'Welcome to Commerce Demo! Enjoy 20% off your first order.',
                'type' => VoucherType::Percentage,
                'value' => 2000,
                'min_cart_value' => 5000,
                'max_discount' => 20000,
                'usage_limit_per_user' => 1,
                'status' => VoucherStatus::Active,
                'starts_at' => now()->subMonths(6),
                'expires_at' => now()->addYear(),
            ],
            [
                'code' => 'FIRSTBUY50',
                'name' => '🎁 First Purchase Bonus',
                'description' => 'RM50 off your first purchase - No minimum spend!',
                'type' => VoucherType::Fixed,
                'value' => 5000,
                'usage_limit' => 1000,
                'usage_limit_per_user' => 1,
                'status' => VoucherStatus::Active,
                'starts_at' => now(),
                'expires_at' => now()->addMonths(6),
            ],
        ];

        // Seasonal Campaigns
        $seasonalVouchers = [
            [
                'code' => 'HOLIDAY2024',
                'name' => '🎄 Holiday Season Sale',
                'description' => 'Celebrate the holidays with 35% off sitewide!',
                'type' => VoucherType::Percentage,
                'value' => 3500,
                'min_cart_value' => 10000,
                'max_discount' => 100000,
                'usage_limit' => 5000,
                'status' => VoucherStatus::Paused, // Not started yet - paused until holiday
                'starts_at' => now()->addDays(7),
                'expires_at' => now()->addMonth(),
            ],
            [
                'code' => 'SUMMERSALE',
                'name' => '☀️ Summer Clearance',
                'description' => 'Beat the heat with cool savings - 40% off!',
                'type' => VoucherType::Percentage,
                'value' => 4000,
                'min_cart_value' => 8000,
                'max_discount' => 80000,
                'usage_limit' => 2000,
                'applied_count' => 1245,
                'status' => VoucherStatus::Expired,
                'starts_at' => now()->subMonths(4),
                'expires_at' => now()->subMonth(),
            ],
        ];

        // Free Shipping
        $shippingVouchers = [
            [
                'code' => 'FREESHIP',
                'name' => '🚚 Free Shipping',
                'description' => 'Free shipping on orders above RM80',
                'type' => VoucherType::Fixed,
                'value' => 1500,
                'min_cart_value' => 8000,
                'usage_limit' => null,
                'usage_limit_per_user' => 5,
                'status' => VoucherStatus::Active,
                'starts_at' => now()->subMonths(3),
                'expires_at' => now()->addYear(),
                'metadata' => ['category' => 'shipping'],
            ],
            [
                'code' => 'SHIPFREE100',
                'name' => '📦 Premium Free Shipping',
                'description' => 'Free express shipping for orders above RM100',
                'type' => VoucherType::Fixed,
                'value' => 2500,
                'min_cart_value' => 10000,
                'status' => VoucherStatus::Active,
                'starts_at' => now(),
                'expires_at' => now()->addMonths(6),
                'metadata' => ['category' => 'shipping', 'shipping_type' => 'express'],
            ],
        ];

        // Influencer/Affiliate Codes
        $influencerVouchers = [
            [
                'code' => 'MAYA15',
                'name' => '🌟 Maya Tech Reviews Exclusive',
                'description' => 'Exclusive 15% discount from Maya Tech Reviews',
                'type' => VoucherType::Percentage,
                'value' => 1500,
                'min_cart_value' => 10000,
                'max_discount' => 30000,
                'usage_limit' => 500,
                'applied_count' => 287,
                'status' => VoucherStatus::Active,
                'starts_at' => now()->subMonths(2),
                'expires_at' => now()->addMonths(4),
                'metadata' => ['affiliate_code' => 'INFLUENCER-MAYA', 'campaign' => 'influencer'],
            ],
            [
                'code' => 'AMIR10',
                'name' => '✨ Amir Lifestyle Special',
                'description' => '10% off - Exclusive code from Amir Lifestyle',
                'type' => VoucherType::Percentage,
                'value' => 1000,
                'min_cart_value' => 8000,
                'max_discount' => 20000,
                'usage_limit' => 300,
                'applied_count' => 156,
                'status' => VoucherStatus::Active,
                'starts_at' => now()->subMonths(1),
                'expires_at' => now()->addMonths(5),
                'metadata' => ['affiliate_code' => 'LIFESTYLE-AMIR', 'campaign' => 'influencer'],
            ],
        ];

        // Create all vouchers
        $allVouchers = array_merge(
            $flashSaleVouchers,
            $loyaltyVouchers,
            $acquisitionVouchers,
            $seasonalVouchers,
            $shippingVouchers,
            $influencerVouchers
        );

        foreach ($allVouchers as $voucherData) {
            $voucher = Voucher::create(array_merge([
                'currency' => 'MYR',
                'allows_manual_redemption' => true,
            ], $voucherData));

            // Create some usage history for active vouchers with applied_count
            if (isset($voucherData['applied_count']) && $voucherData['applied_count'] > 0) {
                $this->createVoucherUsageHistory($voucher, (int) ($voucherData['applied_count'] * 0.7));
            }
        }

        $this->command->info('   ✓ Created ' . count($allVouchers) . ' voucher campaigns');
    }

    private function createVoucherUsageHistory(Voucher $voucher, int $count): void
    {
        $users = User::inRandomOrder()->limit($count)->get();

        foreach ($users as $user) {
            VoucherUsage::create([
                'voucher_id' => $voucher->id,
                'redeemed_by_type' => User::class,
                'redeemed_by_id' => $user->id,
                'discount_amount' => rand(1000, 10000),
                'currency' => 'MYR',
                'channel' => fake()->randomElement(['automatic', 'manual', 'api']),
                'used_at' => now()->subDays(rand(1, 60)),
                'metadata' => [
                    'order_id' => 'ORD-' . Str::upper(Str::random(8)),
                    'source' => fake()->randomElement(['checkout', 'cart', 'api']),
                ],
            ]);
        }
    }

    private function seedAffiliates(): void
    {
        $this->command->info('🤝 Building Affiliate Network...');

        // Top-Tier Influencers
        $topInfluencers = [
            [
                'code' => 'INFLUENCER-MAYA',
                'name' => 'Maya Tech Reviews',
                'description' => 'Top tech influencer with 500K YouTube subscribers. Known for detailed product reviews and unboxing videos.',
                'commission_type' => CommissionType::Percentage,
                'commission_rate' => 1000, // 10%
                'contact_email' => 'maya@techreviews.my',
                'website_url' => 'https://mayatechreviews.com',
                'payout_terms' => 'monthly',
                'tracking_domain' => 'ref.mayatech.com',
                'default_voucher_code' => 'MAYA15',
                'metadata' => [
                    'platform' => 'YouTube',
                    'followers' => 500000,
                    'niche' => 'Technology',
                    'tier' => 'platinum',
                    'avg_views_per_video' => 150000,
                ],
            ],
            [
                'code' => 'LIFESTYLE-AMIR',
                'name' => 'Amir Lifestyle Blog',
                'description' => 'Popular lifestyle blogger covering fashion, gadgets, and home living. 200K monthly blog visitors.',
                'commission_type' => CommissionType::Percentage,
                'commission_rate' => 800, // 8%
                'contact_email' => 'amir@lifestyleblog.my',
                'website_url' => 'https://amirlifestyle.com',
                'payout_terms' => 'monthly',
                'default_voucher_code' => 'AMIR10',
                'metadata' => [
                    'platform' => 'Blog',
                    'monthly_visitors' => 200000,
                    'niche' => 'Lifestyle',
                    'tier' => 'gold',
                ],
            ],
            [
                'code' => 'TIKTOK-ZARA',
                'name' => 'Zara Shops',
                'description' => 'Viral TikTok creator with 1M followers. Specializes in shopping hauls and deal hunting.',
                'commission_type' => CommissionType::Percentage,
                'commission_rate' => 1200, // 12%
                'contact_email' => 'zara@tiktokshops.com',
                'website_url' => 'https://tiktok.com/@zarashops',
                'payout_terms' => 'weekly',
                'metadata' => [
                    'platform' => 'TikTok',
                    'followers' => 1000000,
                    'niche' => 'Shopping',
                    'tier' => 'platinum',
                    'avg_views_per_video' => 500000,
                ],
            ],
        ];

        // Strategic Business Partners
        $businessPartners = [
            [
                'code' => 'PARTNER-TECHMART',
                'name' => 'TechMart Malaysia',
                'description' => 'Strategic retail partner with 50+ physical stores nationwide.',
                'commission_type' => CommissionType::Fixed,
                'commission_rate' => 5000, // RM50 per sale
                'contact_email' => 'partnerships@techmart.com.my',
                'website_url' => 'https://techmart.com.my',
                'payout_terms' => 'weekly',
                'metadata' => [
                    'partnership_type' => 'strategic',
                    'tier' => 'enterprise',
                    'store_count' => 50,
                    'contract_value' => 'high',
                ],
            ],
            [
                'code' => 'PARTNER-GADGETWORLD',
                'name' => 'Gadget World Online',
                'description' => 'Major online electronics retailer with cross-promotion agreement.',
                'commission_type' => CommissionType::Percentage,
                'commission_rate' => 500, // 5%
                'contact_email' => 'bizdev@gadgetworld.my',
                'website_url' => 'https://gadgetworld.my',
                'payout_terms' => 'monthly',
                'metadata' => [
                    'partnership_type' => 'cross-promotion',
                    'tier' => 'gold',
                ],
            ],
        ];

        // Regular Affiliates
        $regularAffiliates = [
            [
                'code' => 'AFF-SARAH',
                'name' => 'Sarah Fashion Corner',
                'description' => 'Instagram fashion influencer with 50K followers.',
                'commission_type' => CommissionType::Percentage,
                'commission_rate' => 500,
                'contact_email' => 'sarah@fashioncorner.com',
                'metadata' => ['platform' => 'Instagram', 'followers' => 50000, 'niche' => 'Fashion'],
            ],
            [
                'code' => 'AFF-KUMAR',
                'name' => 'Kumar Deals Telegram',
                'description' => 'Telegram deals channel with 30K subscribers.',
                'commission_type' => CommissionType::Percentage,
                'commission_rate' => 400,
                'contact_email' => 'kumar@deals.my',
                'metadata' => ['platform' => 'Telegram', 'subscribers' => 30000, 'niche' => 'Deals'],
            ],
            [
                'code' => 'AFF-NURUL',
                'name' => 'Nurul Home & Living',
                'description' => 'Facebook page dedicated to home and living products.',
                'commission_type' => CommissionType::Percentage,
                'commission_rate' => 550,
                'contact_email' => 'nurul@homeliving.my',
                'metadata' => ['platform' => 'Facebook', 'followers' => 25000, 'niche' => 'Home'],
            ],
            [
                'code' => 'AFF-DAVID',
                'name' => 'David Tech Blog',
                'description' => 'Tech blogger focusing on budget gadgets.',
                'commission_type' => CommissionType::Percentage,
                'commission_rate' => 600,
                'contact_email' => 'david@techblog.my',
                'metadata' => ['platform' => 'Blog', 'monthly_visitors' => 15000, 'niche' => 'Budget Tech'],
            ],
            [
                'code' => 'AFF-LISA',
                'name' => 'Lisa Beauty Hub',
                'description' => 'Beauty YouTuber with product review focus.',
                'commission_type' => CommissionType::Percentage,
                'commission_rate' => 700,
                'contact_email' => 'lisa@beautyhub.my',
                'metadata' => ['platform' => 'YouTube', 'subscribers' => 20000, 'niche' => 'Beauty'],
            ],
        ];

        // Pending Affiliates
        $pendingAffiliates = [
            [
                'code' => 'PENDING-ALEX',
                'name' => 'Alex Gaming Stream',
                'description' => 'Twitch streamer interested in promoting gaming peripherals.',
                'commission_type' => CommissionType::Percentage,
                'commission_rate' => 800,
                'contact_email' => 'alex@gaming.stream',
                'status' => AffiliateStatus::Pending,
                'metadata' => ['platform' => 'Twitch', 'followers' => 10000, 'niche' => 'Gaming'],
            ],
            [
                'code' => 'PENDING-STARTUP',
                'name' => 'TechStartup MY',
                'description' => 'New tech startup blog seeking partnership.',
                'commission_type' => CommissionType::Percentage,
                'commission_rate' => 600,
                'contact_email' => 'hello@techstartup.my',
                'status' => AffiliateStatus::Pending,
                'metadata' => ['platform' => 'Blog', 'monthly_visitors' => 5000],
            ],
        ];

        // Create all affiliates
        $allAffiliates = [];

        foreach ($topInfluencers as $data) {
            $affiliate = Affiliate::create(array_merge([
                'status' => AffiliateStatus::Active,
                'currency' => 'MYR',
                'activated_at' => now()->subMonths(rand(3, 12)),
            ], $data));
            $allAffiliates[] = $affiliate;
        }

        foreach ($businessPartners as $data) {
            $affiliate = Affiliate::create(array_merge([
                'status' => AffiliateStatus::Active,
                'currency' => 'MYR',
                'activated_at' => now()->subMonths(rand(6, 18)),
            ], $data));
            $allAffiliates[] = $affiliate;
        }

        foreach ($regularAffiliates as $data) {
            $affiliate = Affiliate::create(array_merge([
                'status' => AffiliateStatus::Active,
                'currency' => 'MYR',
                'payout_terms' => 'monthly',
                'activated_at' => now()->subMonths(rand(1, 6)),
            ], $data));
            $allAffiliates[] = $affiliate;
        }

        foreach ($pendingAffiliates as $data) {
            Affiliate::create(array_merge([
                'currency' => 'MYR',
                'payout_terms' => 'monthly',
            ], $data));
        }

        // Create attributions and conversions for active affiliates
        $this->createAffiliateActivity($allAffiliates);

        $this->command->info('   ✓ Created ' . (count($topInfluencers) + count($businessPartners) + count($regularAffiliates) + count($pendingAffiliates)) . ' affiliates');
    }

    /**
     * @param  array<Affiliate>  $affiliates
     */
    private function createAffiliateActivity(array $affiliates): void
    {
        $orders = Order::all();

        foreach ($affiliates as $affiliate) {
            // Create attributions (visits/clicks)
            $attributionCount = match (true) {
                str_contains((string) $affiliate->code, 'INFLUENCER') => rand(100, 300),
                str_contains((string) $affiliate->code, 'TIKTOK') => rand(200, 500),
                str_contains((string) $affiliate->code, 'PARTNER') => rand(50, 150),
                default => rand(20, 80),
            };

            for ($i = 0; $i < $attributionCount; $i++) {
                AffiliateAttribution::create([
                    'affiliate_id' => $affiliate->id,
                    'affiliate_code' => $affiliate->code,
                    'cart_identifier' => Str::uuid()->toString(),
                    'cart_instance' => 'default',
                    'landing_url' => fake()->randomElement([
                        '/products/iphone-15-pro',
                        '/products/macbook-pro-14',
                        '/products/airpods-pro',
                        '/collections/electronics',
                        '/sale',
                    ]),
                    'referrer_url' => fake()->randomElement([
                        'https://youtube.com/watch?v=' . Str::random(11),
                        'https://tiktok.com/@' . Str::random(8),
                        'https://instagram.com/p/' . Str::random(11),
                        'https://facebook.com/posts/' . rand(1000000, 9999999),
                        null,
                    ]),
                    'source' => $affiliate->code,
                    'medium' => fake()->randomElement(['social', 'video', 'email', 'banner']),
                    'campaign' => fake()->randomElement(['holiday_2024', 'flash_sale', 'new_arrivals', 'clearance']),
                    'user_agent' => fake()->userAgent(),
                    'ip_address' => fake()->ipv4(),
                    'first_seen_at' => now()->subDays(rand(1, 90)),
                    'last_seen_at' => now()->subDays(rand(0, 30)),
                ]);
            }

            // Create conversions (successful sales)
            $conversionRate = match (true) {
                str_contains((string) $affiliate->code, 'PARTNER') => 0.15,
                str_contains((string) $affiliate->code, 'INFLUENCER') => 0.08,
                str_contains((string) $affiliate->code, 'TIKTOK') => 0.05,
                default => 0.03,
            };

            $conversionCount = (int) ($attributionCount * $conversionRate);

            for ($i = 0; $i < $conversionCount; $i++) {
                $orderValue = rand(5000, 100000);
                $commissionAmount = $affiliate->commission_type === CommissionType::Percentage
                    ? (int) ($orderValue * $affiliate->commission_rate / 10000)
                    : (int) $affiliate->commission_rate;

                $status = fake()->randomElement([
                    ConversionStatus::Pending,
                    ConversionStatus::Pending,
                    ConversionStatus::Approved,
                    ConversionStatus::Approved,
                    ConversionStatus::Approved,
                    ConversionStatus::Paid,
                    ConversionStatus::Paid,
                ]);

                AffiliateConversion::create([
                    'affiliate_id' => $affiliate->id,
                    'affiliate_code' => $affiliate->code,
                    'order_reference' => 'ORD-' . Str::upper(Str::random(8)),
                    'subtotal_minor' => $orderValue,
                    'total_minor' => $orderValue,
                    'commission_minor' => $commissionAmount,
                    'commission_currency' => 'MYR',
                    'status' => $status,
                    'channel' => fake()->randomElement(['web', 'mobile', 'api']),
                    'approved_at' => $status !== ConversionStatus::Pending ? now()->subDays(rand(1, 30)) : null,
                    'occurred_at' => now()->subDays(rand(1, 60)),
                    'metadata' => [
                        'items_count' => rand(1, 5),
                        'source' => fake()->randomElement(['web', 'mobile', 'app']),
                    ],
                ]);
            }
        }
    }

    private function seedStockTransactions(): void
    {
        // NOTE: Inventory is now handled by InventorySeeder with multi-location support
        // This method is kept for backwards compatibility but delegates to the new system
        $this->command->info('📦 Inventory handled by InventorySeeder (multi-location)');
    }

    private function seedCarts(): void
    {
        $this->command->info('🛒 Building Shopping Carts...');

        // Note: Cart seeding requires the cart session to be properly initialized
        // For demo purposes, carts are typically created through user interactions
        // This seeder focuses on backend data that persists in the database

        $this->command->info('   ✓ Cart infrastructure ready for user interactions');
    }
}
