---
title: Inventory Context
package: inventory
status: current
surface: domain
family: catalog-and-identity
---

# Inventory Context

## Snapshot
- Composer: `aiarmada/inventory`
- Role: Stock, warehouses, allocations, costing, forecasting, and replenishment workflows.
- Search first: `src/Models`, `src/Actions`, `src/Services`, `src/Events`, `config`, `docs`
- Related: `filament-inventory`, `cart`, `orders`, `shipping`

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../filament-inventory/CONTEXT.md` when admin UI changes are involved
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Owns models, actions, services, events, calculations, and persistence rules.
- If admin UI changes too, audit `filament-inventory`.
- Update `docs/*.md` in the same pass when public behavior or config changes.
