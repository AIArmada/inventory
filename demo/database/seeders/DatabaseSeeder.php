<?php

declare(strict_types=1);

namespace Database\Seeders;

use AIArmada\CommerceSupport\Support\OwnerContext;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * 🎭 COMMERCE DEMO DATABASE SEEDER (SINGLE TENANCY)
 *
 * Seeds all demo data in the correct order for a SINGLE TENANT:
 * 1. Users & Permissions (authz showcase)
 * 2. Products & Categories (catalog)
 * 3. Inventory (multi-location warehouse demo)
 * 4. Orders (commerce history)
 * 5. Showcase (vouchers, affiliates, etc.)
 * 6. Billing (subscription demos)
 *
 * SINGLE TENANCY MODE:
 * - All data belongs to one tenant owner (admin@commerce.demo)
 * - All users share access to the same data
 * - Role-based access control determines what users can do
 */
final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('');
        $this->command->info('🎭 ═══════════════════════════════════════════════════════════════');
        $this->command->info('   AIARMADA COMMERCE DEMO - Single Tenancy Mode');
        $this->command->info('═══════════════════════════════════════════════════════════════ 🎭');
        $this->command->info('');

        // Global/non-owner seeders
        $this->call([
            PermissionSeeder::class,
            UserSeeder::class,
        ]);

        // Single tenant: All data belongs to admin@commerce.demo
        $singleTenantOwner = User::query()
            ->where('email', 'admin@commerce.demo')
            ->firstOrFail();

        OwnerContext::withOwner($singleTenantOwner, function () use ($singleTenantOwner): void {
            $this->command->info('');
            $this->command->info('🏢 Seeding single tenant data for: ' . $singleTenantOwner->email);
            $this->command->info('   (All users will share access to this data)');

            $this->call([
                CategorySeeder::class,
                ProductSeeder::class,
                InventorySeeder::class,
                OrderSeeder::class,
                ShowcaseSeeder::class,
                JntShippingSeeder::class,
                BillingShowcaseSeeder::class,
            ]);
        });

        $this->command->info('');
        $this->command->info('✨ ═══════════════════════════════════════════════════════════════');
        $this->command->info('   DEMO SEEDING COMPLETE! (Single Tenancy Mode)');
        $this->command->info('');
        $this->command->info('   🔐 Login Credentials (password: "password"):');
        $this->command->info('   • admin@commerce.demo     - Super Admin');
        $this->command->info('   • manager@commerce.demo   - Operations Manager');
        $this->command->info('   • warehouse@commerce.demo - Inventory Manager');
        $this->command->info('   • marketing@commerce.demo - Marketing Manager');
        $this->command->info('   • finance@commerce.demo   - Finance Manager');
        $this->command->info('   • support@commerce.demo   - Customer Support');
        $this->command->info('   • viewer@commerce.demo    - Analyst (Read-only)');
        $this->command->info('');
        $this->command->info('   📌 All users access the SAME data (single tenant)');
        $this->command->info('   📌 Role-based permissions control what each user can do');
        $this->command->info('');
        $this->command->info('   🌐 URLs:');
        $this->command->info('   • Admin:  /admin');
        $this->command->info('   • Shop:   /');
        $this->command->info('═══════════════════════════════════════════════════════════════ ✨');
        $this->command->info('');
    }
}
