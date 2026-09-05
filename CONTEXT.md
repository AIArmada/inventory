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
- Models: `InventoryAllocation`, `InventoryBackorder`, `InventoryBatch`, `InventoryCostLayer`, `InventoryDemandHistory`, `InventoryLevel`, `InventoryLocation`, `InventoryMovement`, `InventoryOperation`, `InventoryReorderSuggestion`
- Actions/Services: `Actions/AdjustInventory`, `Actions/AllocateStock`, `Actions/ApproveReorderSuggestion`, `Actions/CheckLowInventory`, `Actions/CommitStock`, `Actions/CreateBackorder`, `Actions/CreateBatch`, `Actions/CreateValuationSnapshot`
- Config `inventory.php`: `locations`, `levels`, `movements`, `allocations`, `batches`, `serials`, `serial_history`, `cost_layers`, `standard_costs`, `valuation_snapshots`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: none — the five canonical docs cover this package
