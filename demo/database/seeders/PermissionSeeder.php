<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * 🔐 AUTHZ SHOWCASE SEEDER
 *
 * Creates a comprehensive permission and role structure demonstrating
 * the full power of the filament-authz package with realistic
 * commerce-focused access control.
 */
final class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command->info('🔐 Building Authorization System...');

        $this->createPermissions();
        $this->createRoles();
        $this->assignRolesToUsers();

        $this->command->info('   ✓ Authorization system complete');
    }

    private function createPermissions(): void
    {
        // ============================================
        // RESOURCE PERMISSIONS (CRUD for each resource)
        // ============================================
        $resources = [
            // Core Commerce
            'product' => 'Products - Core product catalog',
            'category' => 'Categories - Product organization',
            'order' => 'Orders - Customer purchases',

            // Cart & Checkout
            'cart' => 'Carts - Shopping sessions',

            // Vouchers & Promotions
            'voucher' => 'Vouchers - Discount codes',
            'voucher_usage' => 'Voucher Usage - Redemption tracking',
            'voucher_wallet' => 'Voucher Wallet - Saved vouchers',

            // Inventory Management
            'inventory_location' => 'Inventory Locations - Warehouses',
            'inventory_level' => 'Inventory Levels - Stock quantities',
            'inventory_movement' => 'Inventory Movements - Stock history',
            'inventory_allocation' => 'Inventory Allocations - Reservations',

            // Affiliates
            'affiliate' => 'Affiliates - Partner management',
            'affiliate_attribution' => 'Affiliate Attribution - Visit tracking',
            'affiliate_conversion' => 'Affiliate Conversion - Sales tracking',

            // Payments
            'purchase' => 'Purchases - CHIP transactions',
            'subscription' => 'Subscriptions - Recurring billing',

            // Shipping
            'shipment' => 'Shipments - J&T orders',

            // Users & Access
            'user' => 'Users - Customer accounts',
            'role' => 'Roles - Permission groups',
            'permission' => 'Permissions - Access rights',
        ];

        $actions = ['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'forceDelete'];

        foreach ($resources as $resource => $description) {
            foreach ($actions as $action) {
                Permission::firstOrCreate([
                    'name' => "{$resource}.{$action}",
                    'guard_name' => 'web',
                ]);
            }
        }

        // ============================================
        // SPECIAL ACTION PERMISSIONS
        // ============================================
        $specialPermissions = [
            // Inventory special actions
            'inventory.transfer' => 'Transfer stock between locations',
            'inventory.adjust' => 'Make stock adjustments',
            'inventory.receive' => 'Receive new stock',
            'inventory.ship' => 'Process shipments',
            'inventory.allocate' => 'Allocate stock to orders',

            // Voucher special actions
            'voucher.redeem_manual' => 'Manually redeem vouchers',
            'voucher.bulk_create' => 'Bulk create vouchers',
            'voucher.analytics' => 'View voucher analytics',

            // Affiliate special actions
            'affiliate.approve' => 'Approve affiliate applications',
            'affiliate.payout' => 'Process affiliate payouts',
            'affiliate.analytics' => 'View affiliate analytics',

            // Order special actions
            'order.cancel' => 'Cancel orders',
            'order.refund' => 'Process refunds',
            'order.fulfill' => 'Fulfill orders',

            // Payment special actions
            'purchase.refund' => 'Refund purchases',
            'subscription.cancel' => 'Cancel subscriptions',

            // Dashboard
            'dashboard.view' => 'View admin dashboard',
            'analytics.view' => 'View analytics dashboards',
            'reports.export' => 'Export reports',

            // System
            'settings.manage' => 'Manage system settings',
            'audit.view' => 'View audit logs',
            'impersonate.user' => 'Impersonate users',
        ];

        foreach ($specialPermissions as $name => $description) {
            Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]);
        }
    }

    private function createRoles(): void
    {
        // ============================================
        // SUPER ADMIN - Full system access
        // ============================================
        $superAdmin = Role::firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => 'web',
        ]);
        // Super admin gets ALL permissions automatically via filament-authz

        // ============================================
        // ADMIN - Most permissions except sensitive ones
        // ============================================
        $admin = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
        ]);
        $admin->syncPermissions(Permission::where('name', 'not like', 'role.%')
            ->where('name', 'not like', 'permission.%')
            ->where('name', '!=', 'settings.manage')
            ->where('name', '!=', 'impersonate.user')
            ->get());

        // ============================================
        // INVENTORY MANAGER - Warehouse operations
        // ============================================
        $inventoryManager = Role::firstOrCreate([
            'name' => 'inventory_manager',
            'guard_name' => 'web',
        ]);
        $inventoryManager->syncPermissions([
            // Full inventory access
            'inventory_location.viewAny',
            'inventory_location.view',
            'inventory_location.create',
            'inventory_location.update',
            'inventory_level.viewAny',
            'inventory_level.view',
            'inventory_level.update',
            'inventory_movement.viewAny',
            'inventory_movement.view',
            'inventory_movement.create',
            'inventory_allocation.viewAny',
            'inventory_allocation.view',
            // Special inventory actions
            'inventory.transfer',
            'inventory.adjust',
            'inventory.receive',
            'inventory.ship',
            // Read products
            'product.viewAny',
            'product.view',
            // Read orders for fulfillment
            'order.viewAny',
            'order.view',
            'order.fulfill',
            // Dashboard
            'dashboard.view',
        ]);

        // ============================================
        // MARKETING MANAGER - Vouchers & Affiliates
        // ============================================
        $marketingManager = Role::firstOrCreate([
            'name' => 'marketing_manager',
            'guard_name' => 'web',
        ]);
        $marketingManager->syncPermissions([
            // Full voucher access
            'voucher.viewAny',
            'voucher.view',
            'voucher.create',
            'voucher.update',
            'voucher.delete',
            'voucher_usage.viewAny',
            'voucher_usage.view',
            'voucher.redeem_manual',
            'voucher.bulk_create',
            'voucher.analytics',
            // Full affiliate access
            'affiliate.viewAny',
            'affiliate.view',
            'affiliate.create',
            'affiliate.update',
            'affiliate_attribution.viewAny',
            'affiliate_attribution.view',
            'affiliate_conversion.viewAny',
            'affiliate_conversion.view',
            'affiliate.approve',
            'affiliate.analytics',
            // Read products for campaigns
            'product.viewAny',
            'product.view',
            'category.viewAny',
            'category.view',
            // Analytics
            'dashboard.view',
            'analytics.view',
            'reports.export',
        ]);

        // ============================================
        // FINANCE MANAGER - Payments & Payouts
        // ============================================
        $financeManager = Role::firstOrCreate([
            'name' => 'finance_manager',
            'guard_name' => 'web',
        ]);
        $financeManager->syncPermissions([
            // Payment access
            'purchase.viewAny',
            'purchase.view',
            'purchase.refund',
            'subscription.viewAny',
            'subscription.view',
            'subscription.cancel',
            // Affiliate payouts
            'affiliate.viewAny',
            'affiliate.view',
            'affiliate_conversion.viewAny',
            'affiliate_conversion.view',
            'affiliate_conversion.update',
            'affiliate.payout',
            // Order finances
            'order.viewAny',
            'order.view',
            'order.refund',
            // Analytics & Reports
            'dashboard.view',
            'analytics.view',
            'reports.export',
        ]);

        // ============================================
        // CUSTOMER SUPPORT - Limited access for helping customers
        // ============================================
        $support = Role::firstOrCreate([
            'name' => 'customer_support',
            'guard_name' => 'web',
        ]);
        $support->syncPermissions([
            // Read orders
            'order.viewAny',
            'order.view',
            'order.cancel',
            // Read products
            'product.viewAny',
            'product.view',
            'category.viewAny',
            'category.view',
            // Read inventory (check stock)
            'inventory_level.viewAny',
            'inventory_level.view',
            // Read carts
            'cart.viewAny',
            'cart.view',
            // User lookup
            'user.viewAny',
            'user.view',
            // Voucher application
            'voucher.viewAny',
            'voucher.view',
            'voucher.redeem_manual',
            // Dashboard
            'dashboard.view',
        ]);

        // ============================================
        // VIEWER - Read-only access for auditors/analysts
        // ============================================
        $viewer = Role::firstOrCreate([
            'name' => 'viewer',
            'guard_name' => 'web',
        ]);
        $viewerPermissions = Permission::where('name', 'like', '%.viewAny')
            ->orWhere('name', 'like', '%.view')
            ->orWhere('name', '=', 'dashboard.view')
            ->orWhere('name', '=', 'analytics.view')
            ->get();
        $viewer->syncPermissions($viewerPermissions);
    }

    private function assignRolesToUsers(): void
    {
        // Assignments are done in UserSeeder after users are created
    }
}
