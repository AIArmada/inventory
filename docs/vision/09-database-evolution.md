# Database Evolution

> **Document:** 09 of 11  
> **Package:** `aiarmada/inventory`  
> **Status:** Vision

---

## Overview

A comprehensive **database schema evolution plan** to support location hierarchy, batch/lot tracking, serial numbers, cost layers, and demand forecasting.

---

## Schema Evolution Summary

### New Tables

| Table | Purpose |
|-------|---------|
| `inventory_batches` | Batch/lot tracking with expiry |
| `inventory_serials` | Individual unit tracking |
| `inventory_serial_history` | Serial lifecycle events |
| `inventory_cost_layers` | FIFO/cost tracking |
| `inventory_standard_costs` | Standard costing |
| `inventory_valuation_snapshots` | Point-in-time valuations |
| `inventory_demand_history` | Historical demand data |
| `inventory_supplier_leadtimes` | Supplier lead times |
| `inventory_reorder_suggestions` | Replenishment suggestions |
| `inventory_backorders` | Unfulfilled demand queue |

### Table Modifications

| Table | Changes |
|-------|---------|
| `inventory_locations` | Add hierarchy, types, zones, bins |
| `inventory_levels` | Add quantity types, thresholds, costs |
| `inventory_allocations` | Add batch/serial references |
| `inventory_movements` | Add cost tracking, batch/serial |

---

## Phase 1: Location Hierarchy

### Modify inventory_locations

```php
Schema::table('inventory_locations', function (Blueprint $table) {
    // Hierarchy
    $table->foreignUuid('parent_id')->nullable()->after('id');
    $table->string('path', 500)->nullable()->index()->after('parent_id');
    $table->integer('depth')->default(0)->after('path');
    
    // Type
    $table->string('type')->default('warehouse')->after('code');
    
    // Capacity
    $table->integer('max_capacity')->nullable()->after('priority');
    $table->integer('current_utilization')->default(0)->after('max_capacity');
    $table->string('capacity_unit', 20)->default('units')->after('current_utilization');
    
    // Picking
    $table->string('pick_sequence', 50)->nullable()->after('capacity_unit');
    $table->boolean('is_pickable')->default(true)->after('pick_sequence');
    $table->boolean('is_receivable')->default(true)->after('is_pickable');
    
    // Coordinates
    $table->decimal('coordinate_x', 10, 2)->nullable()->after('is_receivable');
    $table->decimal('coordinate_y', 10, 2)->nullable()->after('coordinate_x');
    $table->decimal('coordinate_z', 10, 2)->nullable()->after('coordinate_y');
    
    // Attributes
    $table->string('temperature_zone', 30)->nullable()->after('coordinate_z');
    $table->boolean('is_hazmat_certified')->default(false)->after('temperature_zone');
    $table->json('restrictions')->nullable()->after('is_hazmat_certified');
});
```

---

## Phase 2: Enhanced Levels

### Modify inventory_levels

```php
Schema::table('inventory_levels', function (Blueprint $table) {
    // Additional quantities
    $table->integer('quantity_committed')->default(0)->after('quantity_reserved');
    $table->integer('quantity_in_transit')->default(0)->after('quantity_committed');
    $table->integer('quantity_on_order')->default(0)->after('quantity_in_transit');
    $table->integer('quantity_backordered')->default(0)->after('quantity_on_order');
    
    // Thresholds
    $table->integer('safety_stock')->default(0)->after('reorder_point');
    $table->integer('min_quantity')->default(0)->after('safety_stock');
    $table->integer('max_quantity')->nullable()->after('min_quantity');
    
    // Replenishment
    $table->integer('reorder_quantity')->nullable()->after('max_quantity');
    $table->integer('lead_time_days')->default(0)->after('reorder_quantity');
    
    // Cost
    $table->integer('unit_cost_minor')->default(0)->after('lead_time_days');
    $table->integer('total_value_minor')->default(0)->after('unit_cost_minor');
    
    // Decimal support
    $table->boolean('use_decimal_quantities')->default(false)->after('total_value_minor');
    $table->decimal('decimal_on_hand', 15, 4)->nullable()->after('use_decimal_quantities');
    $table->decimal('decimal_reserved', 15, 4)->nullable()->after('decimal_on_hand');
    
    // Tracking
    $table->timestamp('last_received_at')->nullable()->after('decimal_reserved');
    $table->timestamp('last_shipped_at')->nullable()->after('last_received_at');
    $table->timestamp('last_counted_at')->nullable()->after('last_shipped_at');
});
```

---

## Phase 3: Batch Tracking

### Create inventory_batches

```php
Schema::create('inventory_batches', function (Blueprint $table) {
    $table->uuid('id')->primary();
    
    $table->string('inventoryable_type');
    $table->uuid('inventoryable_id');
    $table->foreignUuid('location_id');
    $table->foreignUuid('level_id');
    
    // Identification
    $table->string('batch_number', 100)->index();
    $table->string('lot_number', 100)->nullable()->index();
    $table->string('supplier_batch', 100)->nullable();
    
    // Dates
    $table->date('manufactured_at')->nullable();
    $table->date('received_at');
    $table->date('expires_at')->nullable()->index();
    $table->date('best_before_at')->nullable();
    
    // Quantities
    $table->integer('quantity_received');
    $table->integer('quantity_on_hand')->default(0);
    $table->integer('quantity_reserved')->default(0);
    $table->integer('quantity_shipped')->default(0);
    $table->integer('quantity_adjusted')->default(0);
    $table->integer('quantity_disposed')->default(0);
    
    // Cost
    $table->integer('unit_cost_minor')->default(0);
    
    // Status
    $table->string('status', 30)->default('active');
    
    // Quality
    $table->string('quality_status', 30)->default('passed');
    $table->text('quality_notes')->nullable();
    $table->uuid('inspected_by')->nullable();
    $table->timestamp('inspected_at')->nullable();
    
    // Recall
    $table->string('recall_reason')->nullable();
    $table->timestamp('recalled_at')->nullable();
    $table->uuid('recalled_by')->nullable();
    
    $table->json('metadata')->nullable();
    $table->timestamps();
    
    $table->index(['inventoryable_type', 'inventoryable_id', 'status']);
    $table->unique(['inventoryable_type', 'inventoryable_id', 'location_id', 'batch_number'], 'batch_unique');
});
```

---

## Phase 4: Serial Numbers

### Create inventory_serials

```php
Schema::create('inventory_serials', function (Blueprint $table) {
    $table->uuid('id')->primary();
    
    $table->string('inventoryable_type');
    $table->uuid('inventoryable_id');
    $table->foreignUuid('location_id')->nullable();
    $table->foreignUuid('level_id')->nullable();
    $table->foreignUuid('batch_id')->nullable();
    
    // Identification
    $table->string('serial_number', 100)->unique();
    $table->string('manufacturer_serial', 100)->nullable();
    $table->string('imei', 20)->nullable()->index();
    $table->string('mac_address', 20)->nullable();
    
    // Status
    $table->string('status', 30)->default('in_stock');
    
    // Ownership
    $table->foreignUuid('customer_id')->nullable();
    $table->foreignUuid('order_id')->nullable();
    $table->timestamp('sold_at')->nullable();
    
    // Warranty
    $table->date('warranty_starts_at')->nullable();
    $table->date('warranty_ends_at')->nullable();
    $table->string('warranty_type', 30)->nullable();
    $table->json('warranty_claims')->nullable();
    
    // Condition
    $table->string('condition', 30)->default('new');
    $table->text('condition_notes')->nullable();
    
    // Cost
    $table->integer('unit_cost_minor')->default(0);
    $table->integer('sold_price_minor')->nullable();
    
    // Audit
    $table->timestamp('received_at');
    $table->uuid('received_by')->nullable();
    $table->timestamp('last_scanned_at')->nullable();
    $table->uuid('last_scanned_by')->nullable();
    
    $table->json('attributes')->nullable();
    $table->json('metadata')->nullable();
    $table->timestamps();
    
    $table->index(['inventoryable_type', 'inventoryable_id', 'status']);
    $table->index(['customer_id', 'warranty_ends_at']);
});
```

### Create inventory_serial_history

```php
Schema::create('inventory_serial_history', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('serial_id');
    
    $table->string('from_status', 30)->nullable();
    $table->string('to_status', 30);
    
    $table->foreignUuid('from_location_id')->nullable();
    $table->foreignUuid('to_location_id')->nullable();
    
    $table->string('event_type', 50);
    $table->string('reference_type', 100)->nullable();
    $table->uuid('reference_id')->nullable();
    
    $table->foreignUuid('user_id')->nullable();
    $table->text('notes')->nullable();
    
    $table->timestamp('occurred_at');
    $table->timestamps();
    
    $table->index(['serial_id', 'occurred_at']);
});
```

---

## Phase 5: Cost Tracking

### Create inventory_cost_layers

```php
Schema::create('inventory_cost_layers', function (Blueprint $table) {
    $table->uuid('id')->primary();
    
    $table->string('inventoryable_type');
    $table->uuid('inventoryable_id');
    $table->foreignUuid('location_id');
    $table->foreignUuid('batch_id')->nullable();
    $table->foreignUuid('movement_id')->nullable();
    
    $table->integer('quantity_received');
    $table->integer('quantity_remaining');
    
    $table->integer('unit_cost_minor');
    $table->integer('landed_cost_minor')->default(0);
    
    $table->timestamp('received_at');
    $table->string('reference', 100)->nullable();
    $table->json('cost_breakdown')->nullable();
    
    $table->timestamps();
    
    $table->index(['inventoryable_type', 'inventoryable_id', 'received_at']);
    $table->index(['location_id', 'quantity_remaining']);
});
```

### Create inventory_standard_costs

```php
Schema::create('inventory_standard_costs', function (Blueprint $table) {
    $table->uuid('id')->primary();
    
    $table->string('inventoryable_type');
    $table->uuid('inventoryable_id');
    
    $table->integer('standard_cost_minor');
    
    $table->date('effective_from');
    $table->date('effective_to')->nullable();
    
    $table->integer('purchase_variance_minor')->default(0);
    $table->integer('usage_variance_minor')->default(0);
    
    $table->timestamps();
    
    $table->unique(['inventoryable_type', 'inventoryable_id', 'effective_from'], 'std_cost_unique');
});
```

### Create inventory_valuation_snapshots

```php
Schema::create('inventory_valuation_snapshots', function (Blueprint $table) {
    $table->uuid('id')->primary();
    
    $table->date('snapshot_date');
    $table->foreignUuid('location_id')->nullable();
    
    $table->integer('total_quantity');
    $table->bigInteger('total_value_minor');
    $table->bigInteger('total_cost_minor');
    $table->bigInteger('total_landed_cost_minor');
    
    $table->bigInteger('fifo_value_minor');
    $table->bigInteger('avg_value_minor');
    $table->bigInteger('standard_value_minor');
    
    $table->integer('sku_count');
    $table->integer('sku_with_stock_count');
    
    $table->json('breakdown_by_category')->nullable();
    $table->json('breakdown_by_location')->nullable();
    
    $table->timestamps();
    
    $table->unique(['snapshot_date', 'location_id']);
});
```

---

## Phase 6: Demand & Replenishment

### Create inventory_demand_history

```php
Schema::create('inventory_demand_history', function (Blueprint $table) {
    $table->uuid('id')->primary();
    
    $table->string('inventoryable_type');
    $table->uuid('inventoryable_id');
    $table->foreignUuid('location_id')->nullable();
    
    $table->date('period_date');
    $table->string('period_type', 10)->default('daily');
    
    $table->integer('quantity_demanded');
    $table->integer('quantity_fulfilled');
    $table->integer('quantity_unfulfilled')->default(0);
    $table->integer('revenue_minor')->default(0);
    
    $table->timestamps();
    
    $table->unique(
        ['inventoryable_type', 'inventoryable_id', 'location_id', 'period_date', 'period_type'],
        'demand_unique'
    );
    $table->index(['period_date', 'period_type']);
});
```

### Create inventory_supplier_leadtimes

```php
Schema::create('inventory_supplier_leadtimes', function (Blueprint $table) {
    $table->uuid('id')->primary();
    
    $table->string('inventoryable_type');
    $table->uuid('inventoryable_id');
    $table->uuid('supplier_id');
    
    $table->integer('lead_time_days');
    $table->integer('lead_time_variance_days')->default(0);
    
    $table->integer('min_order_quantity')->default(1);
    $table->integer('order_multiple')->default(1);
    
    $table->integer('unit_cost_minor');
    $table->integer('shipping_cost_minor')->default(0);
    
    $table->decimal('on_time_delivery_rate', 5, 2)->default(100);
    $table->integer('total_orders')->default(0);
    
    $table->boolean('is_primary')->default(false);
    $table->boolean('is_active')->default(true);
    
    $table->timestamps();
    
    $table->index(['inventoryable_type', 'inventoryable_id', 'is_primary']);
});
```

### Create inventory_reorder_suggestions

```php
Schema::create('inventory_reorder_suggestions', function (Blueprint $table) {
    $table->uuid('id')->primary();
    
    $table->string('inventoryable_type');
    $table->uuid('inventoryable_id');
    $table->foreignUuid('location_id');
    $table->foreignUuid('supplier_id')->nullable();
    
    $table->integer('current_on_hand');
    $table->integer('current_available');
    $table->integer('reorder_point');
    $table->integer('safety_stock');
    
    $table->integer('suggested_quantity');
    $table->integer('economic_order_quantity')->nullable();
    $table->date('order_by_date');
    $table->date('expected_arrival_date');
    
    $table->string('urgency', 20);
    $table->integer('days_until_stockout')->nullable();
    $table->integer('estimated_cost_minor');
    
    $table->string('status', 20)->default('pending');
    $table->foreignUuid('purchase_order_id')->nullable();
    $table->timestamp('approved_at')->nullable();
    $table->uuid('approved_by')->nullable();
    
    $table->timestamps();
    
    $table->index(['status', 'urgency']);
});
```

### Create inventory_backorders

```php
Schema::create('inventory_backorders', function (Blueprint $table) {
    $table->uuid('id')->primary();
    
    $table->string('inventoryable_type');
    $table->uuid('inventoryable_id');
    $table->string('cart_id', 100);
    $table->uuid('order_id')->nullable();
    
    $table->integer('quantity_requested');
    $table->integer('quantity_fulfilled')->default(0);
    
    $table->string('status', 20)->default('pending');
    
    $table->timestamp('requested_at');
    $table->timestamp('fulfilled_at')->nullable();
    $table->integer('priority')->default(0);
    
    $table->json('metadata')->nullable();
    $table->timestamps();
    
    $table->index(['inventoryable_type', 'inventoryable_id', 'status']);
});
```

---

## Phase 7: Movement Enhancements

### Modify inventory_movements

```php
Schema::table('inventory_movements', function (Blueprint $table) {
    // Batch/Serial references
    $table->foreignUuid('batch_id')->nullable()->after('to_location_id');
    $table->foreignUuid('serial_id')->nullable()->after('batch_id');
    
    // Cost tracking
    $table->integer('unit_cost_minor')->nullable()->after('quantity');
    $table->integer('total_cost_minor')->nullable()->after('unit_cost_minor');
    
    // Before/after snapshot
    $table->integer('quantity_before')->nullable()->after('total_cost_minor');
    $table->integer('quantity_after')->nullable()->after('quantity_before');
});
```

### Modify inventory_allocations

```php
Schema::table('inventory_allocations', function (Blueprint $table) {
    $table->foreignUuid('batch_id')->nullable()->after('level_id');
    $table->foreignUuid('serial_id')->nullable()->after('batch_id');
    $table->string('strategy', 30)->nullable()->after('serial_id');
});
```

---

## Migration Order

```
1. 2024_01_01_000001_modify_inventory_locations_add_hierarchy.php
2. 2024_01_01_000002_modify_inventory_levels_add_quantities.php
3. 2024_01_01_000003_create_inventory_batches_table.php
4. 2024_01_01_000004_create_inventory_serials_table.php
5. 2024_01_01_000005_create_inventory_serial_history_table.php
6. 2024_01_01_000006_create_inventory_cost_layers_table.php
7. 2024_01_01_000007_create_inventory_standard_costs_table.php
8. 2024_01_01_000008_create_inventory_valuation_snapshots_table.php
9. 2024_01_01_000009_create_inventory_demand_history_table.php
10. 2024_01_01_000010_create_inventory_supplier_leadtimes_table.php
11. 2024_01_01_000011_create_inventory_reorder_suggestions_table.php
12. 2024_01_01_000012_create_inventory_backorders_table.php
13. 2024_01_01_000013_modify_inventory_movements_add_tracking.php
14. 2024_01_01_000014_modify_inventory_allocations_add_batch.php
```

---

## Indexing Strategy

### Composite Indexes

```php
// For batch expiry queries
$table->index(['status', 'expires_at', 'quantity_on_hand'], 'batch_expiry_idx');

// For serial warranty queries  
$table->index(['warranty_ends_at', 'status'], 'serial_warranty_idx');

// For cost layer consumption
$table->index(['inventoryable_type', 'inventoryable_id', 'quantity_remaining', 'received_at'], 'cost_layer_fifo_idx');

// For demand analysis
$table->index(['inventoryable_type', 'inventoryable_id', 'period_date'], 'demand_product_date_idx');
```

---

## Navigation

**Previous:** [08-replenishment-forecasting.md](08-replenishment-forecasting.md)  
**Next:** [10-filament-enhancements.md](10-filament-enhancements.md)
