<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Seeder;

/**
 * 👥 MULTI-ROLE USER SHOWCASE SEEDER (SINGLE TENANCY)
 *
 * Creates demo users with distinct roles to showcase the
 * full power of filament-authz RBAC system.
 *
 * Single Tenancy: All users access the same data but with role-based permissions.
 *
 * Login Credentials (all passwords: "password"):
 * - admin@commerce.demo (Super Admin)
 * - manager@commerce.demo (Admin)
 * - warehouse@commerce.demo (Inventory Manager)
 * - marketing@commerce.demo (Marketing Manager)
 * - finance@commerce.demo (Finance Manager)
 * - support@commerce.demo (Customer Support)
 * - viewer@commerce.demo (Viewer/Analyst)
 */
final class UserSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('👥 Creating Demo Users (Single Tenancy)...');

        // Single tenant owner for role assignments
        $singleTenantOwner = $this->resolveTenantOwner();
        $previousTeamId = getPermissionsTeamId();
        setPermissionsTeamId($singleTenantOwner);

        try {
            $demoUsers = [
                // ============================================
                // 👑 SUPER ADMIN - Full system access
                // ============================================
                [
                    'name' => 'Sarah Chen',
                    'email' => 'admin@commerce.demo',
                    'phone' => '+60123456789',
                    'role' => 'super_admin',
                    'description' => 'CEO & System Administrator',
                ],

                // ============================================
                // 🛠️ ADMIN - Most permissions
                // ============================================
                [
                    'name' => 'Ahmad Ibrahim',
                    'email' => 'manager@commerce.demo',
                    'phone' => '+60123456790',
                    'role' => 'admin',
                    'description' => 'Operations Manager',
                ],

                // ============================================
                // 📦 INVENTORY MANAGER - Warehouse operations
                // ============================================
                [
                    'name' => 'Raj Kumar',
                    'email' => 'warehouse@commerce.demo',
                    'phone' => '+60123456791',
                    'role' => 'inventory_manager',
                    'description' => 'Warehouse Manager - Klang Valley Hub',
                ],

                // ============================================
                // 📣 MARKETING MANAGER - Vouchers & Affiliates
                // ============================================
                [
                    'name' => 'Maya Wong',
                    'email' => 'marketing@commerce.demo',
                    'phone' => '+60123456792',
                    'role' => 'marketing_manager',
                    'description' => 'Head of Marketing & Partnerships',
                ],

                // ============================================
                // 💰 FINANCE MANAGER - Payments & Payouts
                // ============================================
                [
                    'name' => 'Lim Wei Ling',
                    'email' => 'finance@commerce.demo',
                    'phone' => '+60123456793',
                    'role' => 'finance_manager',
                    'description' => 'Finance Director',
                ],

                // ============================================
                // 🎧 CUSTOMER SUPPORT - Limited access
                // ============================================
                [
                    'name' => 'Nurul Aisyah',
                    'email' => 'support@commerce.demo',
                    'phone' => '+60123456794',
                    'role' => 'customer_support',
                    'description' => 'Senior Support Agent',
                ],

                // ============================================
                // 👁️ VIEWER - Read-only access
                // ============================================
                [
                    'name' => 'David Tan',
                    'email' => 'viewer@commerce.demo',
                    'phone' => '+60123456795',
                    'role' => 'viewer',
                    'description' => 'Business Analyst (External Auditor)',
                ],
            ];

            foreach ($demoUsers as $userData) {
                $user = User::firstOrCreate(
                    ['email' => $userData['email']],
                    [
                        'name' => $userData['name'],
                        'phone' => $userData['phone'],
                        'password' => bcrypt('password'),
                        'email_verified_at' => now(),
                    ]
                );

                // Assign role if it exists (within single tenant context)
                if (isset($userData['role'])) {
                    OwnerContext::withOwner($singleTenantOwner, function () use ($user, $userData): void {
                        $user->assignRole($userData['role']);
                    });
                }
            }

            // Create additional demo customers (no admin roles)
            $customers = [
                ['name' => 'John Smith', 'email' => 'john@example.com'],
                ['name' => 'Emily Davis', 'email' => 'emily@example.com'],
                ['name' => 'Michael Brown', 'email' => 'michael@example.com'],
                ['name' => 'Jessica Wilson', 'email' => 'jessica@example.com'],
                ['name' => 'Christopher Lee', 'email' => 'chris@example.com'],
                ['name' => 'Amanda Johnson', 'email' => 'amanda@example.com'],
                ['name' => 'Daniel Garcia', 'email' => 'daniel@example.com'],
                ['name' => 'Lisa Martinez', 'email' => 'lisa@example.com'],
                ['name' => 'James Anderson', 'email' => 'james@example.com'],
                ['name' => 'Sarah Taylor', 'email' => 'sarah.t@example.com'],
            ];

            foreach ($customers as $customer) {
                User::firstOrCreate(
                    ['email' => $customer['email']],
                    [
                        'name' => $customer['name'],
                        'password' => bcrypt('password'),
                        'email_verified_at' => now(),
                    ]
                );
            }

            $this->command->info('   ✓ Created ' . count($demoUsers) . ' admin users + ' . count($customers) . ' customers');
        } finally {
            setPermissionsTeamId($previousTeamId);
        }
    }

    private function resolveTenantOwner(): User
    {
        return User::firstOrCreate(
            ['email' => 'admin@commerce.demo'],
            [
                'name' => 'Sarah Chen',
                'phone' => '+60123456789',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]
        );
    }
}
