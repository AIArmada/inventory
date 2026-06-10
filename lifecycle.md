# Inventory Package — Lifecycle Refactoring

## 1. Executive Summary

The inventory package (14 tables, 14 models, 13 enums, 2 state machines) has three genuine lifecycle concerns:

- **Duplicate state columns** on `inventory_batches` where `status` already encodes `quarantined` and `recalled` states alongside redundant `is_quarantined`/`is_recalled` booleans.
- **`is_active` on supplier_leadtimes** — a simple boolean toggle that could optionally gain a `deactivated_at timestampTz` for audit.
- **`SerialStatus` enum duplicates state machine** transition rules already defined in the Spatie state object.

All other tables are already well-structured or are configuration entities where booleans and scheduling windows are the correct lifecycle pattern.

**Scope**: migrations, models, enums, states. No backward compatibility.

---

## 2. Full Inventory by Table

| # | Table | Migration | Model | Lifecycle Column | `is_*` Booleans | `*_at` Timestamps | Issues |
|---|-------|-----------|-------|------------------|-----------------|-------------------|--------|
| 1 | `inventory_locations` | `000001` | `InventoryLocation` | `is_active` (bool) | `is_active`, `is_hazmat_certified` | `timestampsTz()` | P1 |
| 2 | `inventory_levels` | `000002` | `InventoryLevel` | `alert_status` (string) | — | `last_alert_at`, `last_stock_check_at`, `timestampsTz()` | — |
| 3 | `inventory_movements` | `000003` | `InventoryMovement` | `type` (string) | — | `occurred_at`, `timestampsTz()` | — |
| 4 | `inventory_allocations` | `000004` | `InventoryAllocation` | — | — | `expires_at`, `timestampsTz()` | — |
| 5 | `inventory_batches` | `000008` | `InventoryBatch` | `status` (string) | `is_quarantined`, `is_recalled` | `quality_checked_at`, `recalled_at`, `timestampsTz()` | P2 |
| 6 | `inventory_serials` | `000010` | `InventorySerial` | `status` (State) | — | `assigned_at`, `sold_at`, `timestampsTz()` | P3 |
| 7 | `inventory_serial_history` | `000011` | `InventorySerialHistory` | `event_type` (string) | — | `occurred_at`, `timestampsTz()` | — |
| 8 | `inventory_cost_layers` | `000012` | `InventoryCostLayer` | `costing_method` (string) | — | `layer_date`, `timestampsTz()` | — |
| 9 | `inventory_standard_costs` | `000013` | `InventoryStandardCost` | — | — | `effective_from`, `effective_to`, `timestampsTz()` | — |
| 10 | `inventory_valuation_snapshots` | `000014` | `InventoryValuationSnapshot` | — | — | `timestampsTz()` | — |
| 11 | `inventory_backorders` | `000015` | `InventoryBackorder` | `status` (State) | — | `requested_at`, `promised_at`, `fulfilled_at`, `cancelled_at`, `timestampsTz()` | — |
| 12 | `inventory_demand_history` | `000016` | `InventoryDemandHistory` | — | — | `timestampsTz()` | — |
| 13 | `inventory_supplier_leadtimes` | `000017` | `InventorySupplierLeadtime` | — | `is_active`, `is_primary` | `last_order_at`, `last_received_at`, `timestampsTz()` | P1 |
| 14 | `inventory_reorder_suggestions` | `000018` | `InventoryReorderSuggestion` | `status` (Enum) | — | `approved_at`, `ordered_at`, `timestampsTz()` | — |

---

## 3. Problems Summary

### P1: `is_active` boolean on supplier leadtimes lacks audit timestamp

**Affected**: `inventory_supplier_leadtimes`

The `is_active` boolean on supplier leadtimes is a simple on/off toggle with no timestamp for when deactivation occurred.

**Fix**: Optionally add `deactivated_at` timestampTz (nullable, null = active). Keep `is_active` as boolean — it's a config entity toggle. `is_primary` stays as boolean (designation flag).

### P2: Duplicate state: `status` column + `is_quarantined`/`is_recalled` booleans

**Affected**: `inventory_batches`

The `status` column already encodes `quarantined`, `recalled`, `expired`, `depleted`, `active`, `on_hold`. The `is_quarantined` and `is_recalled` booleans are fully redundant with `status`. Having both creates consistency risk (status says active but is_quarantined = true).

**Fix**: Remove `is_quarantined` and `is_recalled` booleans. Derive from `status` column. Keep `quarantine_reason`, `recall_reason`, and `recalled_at`. Add `quarantined_at timestampTz` for audit.

### P3: `SerialStatus` enum duplicates state machine transition rules

**Affected**: `src/Enums/SerialStatus.php`, `src/States/SerialStatus.php`

The `src/Enums/SerialStatus` PHP enum defines `allowedTransitions()` and `canTransitionTo()` — the same transition rules encoded in the `src/States/SerialStatus::config()` state machine. The `InventorySerial` model uses `hasStates` (Spatie) but also calls the enum's `isAllocatable()` and `canTransitionTo()`. This is a DRY violation and out-of-sync risk.

**Fix**: Remove transition rules from the enum. The model should delegate to the state object (`$this->status->canTransitionTo(...)`) exclusively. The Enum becomes a lightweight label/option provider only.

---

## 4. Recommended Structure

### 4.1 Column Naming Convention

| Concept | Current Pattern | Recommendation |
|---------|----------------|----------------|
| Quarantined state | `is_quarantined` boolean (redundant with `status`) | Remove boolean; use `status = 'quarantined'` + `quarantined_at` |
| Recalled state | `is_recalled` boolean (redundant with `status`) | Remove boolean; use `status = 'recalled'` + `recalled_at` |
| Active/inactive (leadtimes) | `is_active` boolean | Keep boolean; optionally add `deactivated_at timestampTz` |
| Primary supplier | `is_primary` boolean | Keep boolean (designation flag) |
| Hazmat certification | `is_hazmat_certified` boolean | Keep (attribute, not lifecycle) |

### 4.2 Per-Table Target Schema

#### `inventory_batches`
```
REMOVE: is_quarantined, is_recalled
ADD:    quarantined_at (timestampTz, nullable)
KEEP:   status, quarantine_reason, recall_reason, recalled_at, quality_checked_at, quality_status
```

#### `inventory_supplier_leadtimes`
```
ADD:    deactivated_at (timestampTz, nullable) — OPTIONAL
KEEP:   is_active (boolean), is_primary (boolean)
```

#### `inventory_locations`
```
No changes needed. `is_active` boolean is correct for a config entity.
Optionally add `deactivated_at timestampTz` for audit.
```

### 4.3 Model Changes

| Model | Change |
|-------|--------|
| `InventoryBatch` | Remove `is_quarantined`, `is_recalled` from casts/fillable. Add `quarantined_at` cast. Update `quarantine()`/`releaseFromQuarantine()`/`recall()` to set `*_at` timestamps and rely on `status` column for state. |
| `InventorySupplierLeadtime` | Optionally add `deactivated_at` cast. Update `scopeActive()` to `whereNull('deactivated_at')` if using deactivated_at. `is_primary` stays as boolean. |
| `InventorySerial` | Remove `use AIArmada\Inventory\Enums\SerialStatus` import for transition logic. Delegate exclusively to state object. |

### 4.4 Enum/State Changes

- `src/Enums/SerialStatus.php`: Remove `allowedTransitions()`, `canTransitionTo()`. Keep `label()`, `color()`, `options()`, `isAllocatable()`, `isInStock()`.
- `src/States/SerialStatus.php`: No changes — remains source of truth for transitions.
- `src/Models/InventorySerial.php`: Change `canTransitionTo()` and `transitionStatusTo()` to use only the state object, never the enum.

---

## 5. Refactoring Plan — Parallel-Agent Checklist

### Agent A — Migrations

- [x] **A1**: Drop `is_quarantined`, `is_recalled` from `inventory_batches`, add `quarantined_at` timestampTz
- [x] **A2**: Optionally add `deactivated_at` timestampTz to `inventory_supplier_leadtimes`
- [x] **A3**: Optionally add `deactivated_at` timestampTz to `inventory_locations`

### Agent B — Models

- [x] **B1**: `InventoryBatch` — remove `is_quarantined`/`is_recalled` fillable/cast, add `quarantined_at` cast, update lifecycle methods (`quarantine()`, `releaseFromQuarantine()`, `recall()`)
- [x] **B2**: `InventorySupplierLeadtime` — optionally add `deactivated_at` cast, update `scopeActive()` if using deactivated_at
- [x] **B3**: `InventorySerial` — remove enum-based transition delegation, use state object exclusively

### Agent C — Enums & States

- [x] **C1**: Strip `allowedTransitions()` and `canTransitionTo()` from `src/Enums/SerialStatus.php`
- [x] **C2**: Verify `src/States/SerialStatus::config()` matches all needed transitions
- [x] **C3**: Audit all `SerialStatus` enum usages across the package for transition logic that should delegate to state

---

## 6. Migration Strategy

### Phase 1: Add new columns (non-breaking)

1. Add `quarantined_at` timestampTz nullable to `inventory_batches`
2. Optionally add `deactivated_at` timestampTz nullable to `inventory_supplier_leadtimes` and `inventory_locations`

### Phase 2: Backfill data

1. Backfill `quarantined_at = updated_at` where `is_quarantined = true` in `inventory_batches`

### Phase 3: Remove redundant booleans (requires model updates first)

1. Drop `is_quarantined` and `is_recalled` from `inventory_batches`

### Rollback: Not supported (no `down()` required per guidelines)

---

## 7. Verification Commands

```bash
# PHPStan on the inventory package
./vendor/bin/phpstan analyse packages/inventory/src --level=6

# Run inventory tests
./vendor/bin/pest --parallel packages/inventory/tests

# Lint changed files
./vendor/bin/pint packages/inventory/src packages/inventory/database

# Check for residual is_quarantined / is_recalled in models
rg -n "is_quarantined\|is_recalled" packages/inventory/src/Models

# Verify all serial models use state object for transitions
rg -n "canTransitionTo\|allowedTransitions" packages/inventory/src/Enums/SerialStatus.php

# Verify all models use timestampsTz
rg -n "timestamps\b" packages/inventory/database/migrations
```
