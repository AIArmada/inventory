---
title: Inventory Package Audit Report
audited: 2025-12-15
status: passed
---

# Inventory Package Audit Report

## Summary

| Metric | Value |
|--------|-------|
| **Total Issues Found** | 10 |
| **Critical** | 3 |
| **High** | 3 |
| **Medium** | 3 |
| **Low** | 1 |
| **All Fixed** | ✅ Yes |

---

## Package Overview

**Package**: `aiarmada/inventory`  
**Purpose**: Inventory levels, allocations (reservations), movements, and reporting primitives.

---

## Verification Results

### PHPStan Level 6
```
✅ PASSED - No errors
128/128 files analyzed
```

### Tests
```
✅ PASSED
1133 passed, 6 skipped (2411 assertions)
Duration: 218.00s (parallel: 16 processes)
```

### Pint Code Style
```
✅ PASSED
packages/inventory + tests/src/Inventory
```

---

## Issues Found & Fixed

### 1) Owner scoping was not enforced (Critical)
- **File/Location**: `packages/inventory/src/Services/InventoryService.php`, `packages/inventory/src/Services/InventoryAllocationService.php`
- **Problem Snippet**:
  ```php
  ->whereHas('location', fn ($q) => $q->where('is_active', true))
  ```
- **Why it's wrong**: When `inventory.owner.enabled=true`, availability/allocation queries must scope by current owner. Without it, tenants can read/allocate inventory from other owners (especially if inventoryable IDs can collide across tenants).
- **Fix**: Applied explicit owner-based constraints (`owner_type`/`owner_id`) to location subqueries and blocked mutations against out-of-scope locations.
- **Tests**: Added `tests/src/Inventory/Unit/OwnerScopingTest.php`.
- **Fixed Version**: ✅ Implemented.

### 2) Shipping ignored reserved stock (Critical)
- **File/Location**: `packages/inventory/src/Services/InventoryService.php`
- **Problem Snippet**:
  ```php
  if ($level === null || $level->quantity_on_hand < $quantity) { ... }
  ```
- **Why it's wrong**: `quantity_reserved` must reduce what is shippable. Ignoring it allows shipping inventory that has already been reserved for carts/orders (oversell).
- **Fix**: Shipping now locks the level row and checks `available` (on_hand - reserved), and throws with the correct “available” value.
- **Tests**: Added `test_shipping_considers_reserved_quantity()` in `tests/src/Inventory/Unit/InventoryServiceTest.php`.
- **Fixed Version**: ✅ Implemented.

### 3) Allocation race condition allowed over-reservation (Critical)
- **File/Location**: `packages/inventory/src/Services/InventoryAllocationService.php`
- **Problem Snippet**:
  ```php
  $levels = $this->getLevelsForAllocation($model, $strategy);
  // no row locks while checking $level->available + incrementReserved()
  ```
- **Why it's wrong**: Concurrent allocations can observe the same available stock and both reserve it, resulting in reserved quantities exceeding on-hand stock.
- **Fix**: Allocation selection now uses `lockForUpdate()` on candidate levels during allocation.
- **Fixed Version**: ✅ Implemented.

### 4) Wrong exception type on allocation failure (High)
- **File/Location**: `packages/inventory/src/Services/InventoryAllocationService.php`
- **Problem Snippet**:
  ```php
  throw new InvalidArgumentException('Insufficient inventory ...');
  ```
- **Why it's wrong**: Cart/checkout flows expect `InsufficientInventoryException` for predictable handling; throwing `InvalidArgumentException` breaks integrations and error contracts.
- **Fix**: Allocation failures now throw `InsufficientInventoryException` with requested/available details; tests updated.
- **Fixed Version**: ✅ Implemented.

### 5) Inventory actions produced inconsistent movements (High)
- **File/Location**: `packages/inventory/src/Actions/*`
- **Problem Snippet**:
  ```php
  'quantity' => -$quantity
  ```
- **Why it's wrong**: Movements were inconsistent with services/factories and broke reporting (shipments and transfers could sum negative/zero).
- **Fix**: Actions now delegate to `InventoryService`, ensuring one normalized movement record per operation with positive quantities and direction via `from_location_id`/`to_location_id`.
- **Tests**: Updated action tests in `tests/src/Inventory/Unit/Actions/*`.
- **Fixed Version**: ✅ Implemented.

### 6) Availability cache could return stale results (High)
- **File/Location**: `packages/inventory/src/Services/InventoryService.php`, `packages/inventory/src/Services/InventoryAllocationService.php`
- **Problem**: In-memory availability caching was not invalidated after mutations/reservations, causing stale availability within the same request lifecycle.
- **Fix**: Cache is cleared after inventory mutations (receive/ship/transfer/adjust) and after reservation changes (allocate/release/commit/cleanup).
- **Fixed Version**: ✅ Implemented.

### 7) JSON column config key mismatch in migrations (Medium)
- **File/Location**: `packages/inventory/database/migrations/*`
- **Problem Snippet**:
  ```php
  $jsonType = config('inventory.json_column_type', 'json');
  ```
- **Why it's wrong**: The package config uses `inventory.database.json_column_type`; migrations ignored the configured value and silently fell back to `json`.
- **Fix**: Standardized migrations to read `inventory.database.json_column_type` with a safe fallback.
- **Fixed Version**: ✅ Implemented.

### 8) Missing `inventory.defaults.currency` config (Medium)
- **File/Location**: `packages/inventory/config/inventory.php`
- **Problem**: Costing/valuation services reference `config('inventory.defaults.currency', 'MYR')`, but the config key did not exist.
- **Fix**: Added `defaults.currency` to config.
- **Fixed Version**: ✅ Implemented.

### 9) Allocation factory referenced wrong TTL config (Medium)
- **File/Location**: `packages/inventory/database/factories/InventoryAllocationFactory.php`
- **Problem**: Used `inventory.allocation_expiry_minutes` which does not exist.
- **Fix**: Uses `inventory.allocation_ttl_minutes`.
- **Fixed Version**: ✅ Implemented.

### 10) PHP warnings in inventory tests (Low)
- **File/Location**: `tests/src/Inventory/Unit/Events/Concerns/HasInventoryEventDataTest.php`, `tests/src/Inventory/Unit/Traits/HasLocationHierarchyTest.php`
- **Problem**: Redundant `use` statements for global classes triggered warnings during test runs.
- **Fix**: Removed redundant imports.
- **Fixed Version**: ✅ Implemented.

---

## Audit Metadata

| Field | Value |
|-------|-------|
| **Auditor** | Codex CLI (.github/agents/Auditor.agent.md) |
| **Audit Date** | 2025-12-15 |
| **Status** | ✅ Passed (tests + PHPStan + Pint) |

