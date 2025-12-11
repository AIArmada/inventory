<?php

declare(strict_types=1);

namespace Database\Seeders;

use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventoryMovement;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * 📦 MULTI-LOCATION INVENTORY SHOWCASE SEEDER
 *
 * Creates a comprehensive inventory system demonstrating:
 * - Multi-warehouse locations across Malaysia
 * - Priority-based allocation strategies
 * - Realistic inventory levels with varying stock situations
 * - Movement history (receives, transfers, shipments)
 * - Low-stock and reorder scenarios
 */
final class InventorySeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('📦 Building Multi-Location Inventory System...');

        $locations = $this->createLocations();
        $this->createInventoryLevels($locations);
        $this->createMovementHistory($locations);

        $this->command->info('   ✓ Inventory system complete');
    }

    /**
     * @return array<string, InventoryLocation>
     */
    private function createLocations(): array
    {
        $this->command->info('   → Creating warehouse locations...');

        $warehouses = [
            // ============================================
            // PRIMARY DISTRIBUTION CENTERS (High Priority)
            // ============================================
            [
                'name' => 'Klang Valley Distribution Hub',
                'code' => 'KV-HUB',
                'address' => 'Lot 15, Jalan Perusahaan 2, Shah Alam Industrial Park, 40000 Shah Alam, Selangor',
                'is_active' => true,
                'priority' => 100,
                'capacity' => 10000,
                'current_utilization' => 7250,
                'temperature_zone' => 'ambient',
                'metadata' => [
                    'type' => 'distribution_center',
                    'region' => 'central',
                    'manager' => 'Raj Kumar',
                    'contact' => '+60123456791',
                    'operating_hours' => '24/7',
                ],
            ],
            [
                'name' => 'Johor Bahru Fulfillment Center',
                'code' => 'JB-FC',
                'address' => '88, Jalan Industri 5, Pasir Gudang Industrial Estate, 81700 Pasir Gudang, Johor',
                'is_active' => true,
                'priority' => 90,
                'capacity' => 8000,
                'current_utilization' => 5100,
                'temperature_zone' => 'ambient',
                'metadata' => [
                    'type' => 'fulfillment_center',
                    'region' => 'south',
                    'manager' => 'Tan Wei Ming',
                    'contact' => '+60177654321',
                    'operating_hours' => '6:00 - 22:00',
                ],
            ],

            // ============================================
            // REGIONAL WAREHOUSES (Medium Priority)
            // ============================================
            [
                'name' => 'Penang Regional Warehouse',
                'code' => 'PNG-WH',
                'address' => '22, Lorong Perusahaan 8, Bayan Lepas FIZ 4, 11900 Bayan Lepas, Penang',
                'is_active' => true,
                'priority' => 75,
                'capacity' => 5000,
                'current_utilization' => 3200,
                'temperature_zone' => 'ambient',
                'metadata' => [
                    'type' => 'regional_warehouse',
                    'region' => 'north',
                    'manager' => 'Lim Chee Keong',
                    'contact' => '+60164567890',
                ],
            ],
            [
                'name' => 'Kuching Borneo Hub',
                'code' => 'KCH-HUB',
                'address' => '55, Jalan Laksamana Cheng Ho, Pending Industrial Estate, 93450 Kuching, Sarawak',
                'is_active' => true,
                'priority' => 60,
                'capacity' => 3500,
                'current_utilization' => 1800,
                'temperature_zone' => 'ambient',
                'metadata' => [
                    'type' => 'regional_hub',
                    'region' => 'east_malaysia',
                    'manager' => 'James Wong',
                    'contact' => '+60198765432',
                ],
            ],

            // ============================================
            // SPECIALTY STORAGE (Special Conditions)
            // ============================================
            [
                'name' => 'Cold Chain Facility KL',
                'code' => 'KV-COLD',
                'address' => 'Lot 8, Jalan Pelabuhan, Port Klang, 42000 Pelabuhan Klang, Selangor',
                'is_active' => true,
                'priority' => 85,
                'capacity' => 2000,
                'current_utilization' => 950,
                'temperature_zone' => 'refrigerated',
                'metadata' => [
                    'type' => 'cold_storage',
                    'region' => 'central',
                    'temperature_range' => '2-8°C',
                    'specialization' => 'pharmaceutical',
                ],
            ],

            // ============================================
            // RETAIL STORES (Lower Priority)
            // ============================================
            [
                'name' => 'KLCC Flagship Store',
                'code' => 'KLCC-STORE',
                'address' => 'Lot G15, Suria KLCC, Jalan Ampang, 50088 Kuala Lumpur',
                'is_active' => true,
                'priority' => 40,
                'capacity' => 500,
                'current_utilization' => 380,
                'temperature_zone' => 'ambient',
                'metadata' => [
                    'type' => 'retail_store',
                    'region' => 'central',
                    'store_type' => 'flagship',
                ],
            ],
            [
                'name' => 'Mid Valley Store',
                'code' => 'MV-STORE',
                'address' => 'Unit 2-34, Mid Valley Megamall, Lingkaran Syed Putra, 59200 Kuala Lumpur',
                'is_active' => true,
                'priority' => 35,
                'capacity' => 400,
                'current_utilization' => 290,
                'temperature_zone' => 'ambient',
                'metadata' => [
                    'type' => 'retail_store',
                    'region' => 'central',
                ],
            ],

            // ============================================
            // INACTIVE/MAINTENANCE
            // ============================================
            [
                'name' => 'Ipoh Distribution Center (Renovating)',
                'code' => 'IPH-DC',
                'address' => '15, Jalan Lahat, Kawasan Perindustrian Lahat, 30200 Ipoh, Perak',
                'is_active' => false,
                'priority' => 50,
                'capacity' => 4000,
                'current_utilization' => 0,
                'temperature_zone' => 'ambient',
                'metadata' => [
                    'type' => 'distribution_center',
                    'region' => 'north',
                    'status' => 'renovation',
                    'reopening_date' => '2025-03-01',
                ],
            ],
        ];

        $locations = [];
        foreach ($warehouses as $data) {
            $location = InventoryLocation::firstOrCreate(
                ['code' => $data['code']],
                $data
            );
            $locations[$data['code']] = $location;
        }

        $this->command->info('      ✓ Created ' . count($locations) . ' warehouse locations');

        return $locations;
    }

    /**
     * @param  array<string, InventoryLocation>  $locations
     */
    private function createInventoryLevels(array $locations): void
    {
        $this->command->info('   → Distributing inventory across locations...');

        $products = Product::all();
        if ($products->isEmpty()) {
            $this->command->warn('      ⚠ No products found - skipping inventory levels');

            return;
        }

        $levelCount = 0;

        // Get active warehouses only
        $activeLocations = collect($locations)->filter(fn ($loc) => $loc->is_active);

        foreach ($products as $product) {
            // Distribute stock to random subset of locations
            $locationsForProduct = $activeLocations->random(min(4, $activeLocations->count()));

            foreach ($locationsForProduct as $location) {
                // Variable stock levels based on location priority
                $baseStock = match (true) {
                    $location->priority >= 90 => rand(100, 500),
                    $location->priority >= 60 => rand(50, 200),
                    $location->priority >= 40 => rand(20, 100),
                    default => rand(5, 50),
                };

                // Some products intentionally low stock for demo
                if (rand(1, 10) <= 2) {
                    $baseStock = rand(2, 10);
                }

                // Some products out of stock in some locations
                if (rand(1, 10) <= 1) {
                    $baseStock = 0;
                }

                $reserved = (int) ($baseStock * rand(0, 20) / 100);

                InventoryLevel::firstOrCreate(
                    [
                        'inventoryable_type' => Product::class,
                        'inventoryable_id' => $product->id,
                        'location_id' => $location->id,
                    ],
                    [
                        'quantity_on_hand' => $baseStock,
                        'quantity_reserved' => $reserved,
                        'reorder_point' => max(10, (int) ($baseStock * 0.2)),
                        'safety_stock' => max(5, (int) ($baseStock * 0.1)),
                        'max_stock' => (int) ($baseStock * 2.5),
                        'unit_of_measure' => 'EA',
                        'unit_conversion_factor' => 1.0,
                        'lead_time_days' => rand(3, 14),
                        'allocation_strategy' => rand(1, 5) === 1 ? 'fifo' : null, // Some use FIFO
                        'metadata' => [
                            'last_count_by' => fake()->name(),
                            'bin_location' => mb_strtoupper(Str::random(1)) . '-' . rand(1, 50) . '-' . rand(1, 10),
                        ],
                    ]
                );
                $levelCount++;
            }
        }

        $this->command->info('      ✓ Created ' . $levelCount . ' inventory level records');
    }

    /**
     * @param  array<string, InventoryLocation>  $locations
     */
    private function createMovementHistory(array $locations): void
    {
        $this->command->info('   → Generating movement history...');

        $products = Product::all();
        if ($products->isEmpty()) {
            return;
        }

        $movementCount = 0;
        $activeLocations = collect($locations)->filter(fn ($loc) => $loc->is_active)->values();

        // Receive shipments (supplier -> warehouse)
        foreach ($products->take(20) as $product) {
            $targetLocation = $activeLocations->random();

            for ($i = 0; $i < rand(2, 5); $i++) {
                InventoryMovement::create([
                    'inventoryable_type' => Product::class,
                    'inventoryable_id' => $product->id,
                    'from_location_id' => null, // External supplier
                    'to_location_id' => $targetLocation->id,
                    'quantity' => rand(50, 200),
                    'type' => 'receive',
                    'reason' => 'purchase',
                    'reference' => 'PO-' . mb_strtoupper(Str::random(8)),
                    'note' => 'Supplier delivery - ' . fake()->company(),
                    'occurred_at' => now()->subDays(rand(1, 90)),
                ]);
                $movementCount++;
            }
        }

        // Inter-warehouse transfers
        foreach ($products->take(15) as $product) {
            $fromLocation = $activeLocations->random();
            $toLocation = $activeLocations->filter(fn ($l) => $l->id !== $fromLocation->id)->random();

            InventoryMovement::create([
                'inventoryable_type' => Product::class,
                'inventoryable_id' => $product->id,
                'from_location_id' => $fromLocation->id,
                'to_location_id' => $toLocation->id,
                'quantity' => rand(10, 50),
                'type' => 'transfer',
                'reason' => 'rebalance',
                'reference' => 'TRF-' . mb_strtoupper(Str::random(8)),
                'note' => 'Stock rebalancing between locations',
                'occurred_at' => now()->subDays(rand(1, 30)),
            ]);
            $movementCount++;
        }

        // Shipments to customers (sales)
        foreach ($products->take(25) as $product) {
            $sourceLocation = $activeLocations->random();

            for ($i = 0; $i < rand(5, 15); $i++) {
                InventoryMovement::create([
                    'inventoryable_type' => Product::class,
                    'inventoryable_id' => $product->id,
                    'from_location_id' => $sourceLocation->id,
                    'to_location_id' => null, // Customer
                    'quantity' => rand(1, 5),
                    'type' => 'ship',
                    'reason' => 'sale',
                    'reference' => 'ORD-' . mb_strtoupper(Str::random(8)),
                    'note' => 'Customer order fulfillment',
                    'occurred_at' => now()->subDays(rand(1, 60)),
                ]);
                $movementCount++;
            }
        }

        // Adjustments (inventory counts, damages)
        foreach ($products->take(10) as $product) {
            $location = $activeLocations->random();

            InventoryMovement::create([
                'inventoryable_type' => Product::class,
                'inventoryable_id' => $product->id,
                'from_location_id' => null,
                'to_location_id' => $location->id,
                'quantity' => rand(-5, 10),
                'type' => 'adjust',
                'reason' => fake()->randomElement(['count', 'damage', 'found', 'expired']),
                'reference' => 'ADJ-' . mb_strtoupper(Str::random(8)),
                'note' => 'Inventory audit adjustment',
                'occurred_at' => now()->subDays(rand(1, 30)),
            ]);
            $movementCount++;
        }

        $this->command->info('      ✓ Created ' . $movementCount . ' movement records');
    }
}
