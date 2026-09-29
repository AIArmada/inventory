---
title: Inventory Context
package: inventory
status: current
surface: domain
family: catalog-and-identity
keywords:
  - stock
  - warehouse
  - allocation
  - fifo
  - replenishment
  - reservation
---

# Inventory Context

## Snapshot
- Composer: `aiarmada/inventory`
- Role: Multi-location stock: levels, movements, allocations, batches/serials, costing (FIFO/WA/standard), replenishment.
- Triggers: stock, warehouse, allocation, fifo, replenishment, reservation
- Search first: `src/Models, src/Actions, src/Services, config, docs`
- Related: `filament-inventory`, `cart`, `orders`, `shipping`
- Paired: `filament-inventory` (Filament admin adapter)

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../filament-inventory/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Owns models, actions, services, events, calculations, and persistence rules.
- If admin UI changes too, audit `filament-inventory`.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: Stock, allocation, costing, or replenishment.
- Skip when: Admin UI — see filament-inventory.
- Owner/security: Custom InventoryOwnerScope; key inventory.owner.

## Key surfaces
- Models: `InventoryAllocation`, `InventoryBackorder`, `InventoryBatch`, `InventoryCostLayer`, `InventoryDemandHistory`, `InventoryLevel`, `InventoryLocation`, `InventoryMovement`, `InventoryOperation`, `InventoryReorderSuggestion`, `InventoryReservation`, `InventorySerial`, `InventorySerialHistory`, `InventoryStandardCost`, `InventorySupplierLeadtime`, `InventoryValuationSnapshot` (all 16 are owner-scoped)
- Actions/Services: `Actions/AdjustInventory`, `Actions/AllocateStock`, `Actions/ApproveReorderSuggestion`, `Actions/CheckLowInventory`, `Actions/CommitStock`, `Actions/CreateBackorder`, `Actions/CreateBatch`, `Actions/CreateValuationSnapshot`
- Config `inventory.php`: `database` (→ `table_prefix`, `json_column_type`, `tables.*`), `defaults` (→ `currency`), `models` (→ `product`, `variant`), `default_reorder_point`, `allocation_strategy`, `allocation_ttl_minutes`, `allow_split_allocation`, `owner` (→ `enabled`, `include_global`, `auto_assign_on_create`), `cart` (→ `enabled`, `validate_on_add`, `auto_allocate_on_add`, `reserve_on_checkout`, `block_checkout_on_insufficient`, `allow_backorder`, `max_backorder_quantity`, `allocation_metadata_key`, `backorder_metadata_key`), `payment` (→ `auto_commit`, `events`), `orders` (→ `enabled`), `events` (→ `low_inventory`, `out_of_inventory`), `cleanup` (→ `keep_expired_for_minutes`)

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: none — the five canonical docs cover this package
