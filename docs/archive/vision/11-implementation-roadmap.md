# Implementation Roadmap

> **Document:** 11 of 11  
> **Package:** `aiarmada/inventory`  
> **Status:** Vision

---

## Overview

A phased approach to transform the inventory package into an enterprise-grade **Warehouse Management System (WMS)** with batch tracking, serial numbers, cost valuation, and demand forecasting.

---

## Timeline Summary

| Phase | Focus | Duration | Dependencies |
|-------|-------|----------|--------------|
| 1 | Location Hierarchy & Zones | 2 weeks | None |
| 2 | Enhanced Stock Levels | 2 weeks | Phase 1 |
| 3 | Batch/Lot Tracking | 3 weeks | Phase 2 |
| 4 | Serial Number Management | 2 weeks | Phase 3 |
| 5 | Cost Valuation | 3 weeks | Phase 3 |
| 6 | Allocation Strategy Evolution | 2 weeks | Phase 3 |
| 7 | Replenishment & Forecasting | 3 weeks | Phase 5 |
| 8 | Filament Dashboard | 2 weeks | All |
| 9 | Analytics & Reporting | 2 weeks | Phase 7 |

**Total Estimated Duration:** 21 weeks

---

## Phase 1: Location Hierarchy & Zones (Weeks 1-2)

### Objectives
- Implement warehouse → zone → aisle → bay → bin hierarchy
- Add temperature zone support
- Enable location coordinates for optimized picking

### Deliverables

**Database:**
- [ ] Add `parent_id`, `path`, `depth` to `inventory_locations`
- [ ] Add `temperature_zone`, `is_hazmat_certified` columns
- [ ] Add `coordinate_x/y/z`, `pick_sequence` columns
- [ ] Create hierarchical indexes

**Models:**
- [ ] Update `InventoryLocation` with tree traits
- [ ] Add `TemperatureZone` enum
- [ ] Add location validation scopes

**Services:**
- [ ] `LocationTreeService` - ancestry management
- [ ] Add temperature compatibility checks

**Tests:**
- [ ] Location hierarchy operations
- [ ] Path regeneration on move
- [ ] Temperature zone validation

### Acceptance Criteria
- Locations can be nested to any depth
- Temperature zones enforced on stock placement
- Pick sequence auto-calculated from coordinates

---

## Phase 2: Enhanced Stock Levels (Weeks 3-4)

### Objectives
- Implement reorder point and safety stock
- Add quantity decimal support
- Create threshold alert system

### Deliverables

**Database:**
- [ ] Add `reorder_point`, `safety_stock`, `max_stock` to levels
- [ ] Add `quantity_decimal` for fractional quantities
- [ ] Add `alert_status`, `last_alert_at` columns

**Services:**
- [ ] `StockThresholdService` - monitoring & alerts
- [ ] `AlertDispatchService` - notification routing

**Events:**
- [ ] `LowStockDetected`
- [ ] `SafetyStockBreached`
- [ ] `MaxStockExceeded`
- [ ] `StockRestored`

**Listeners:**
- [ ] `NotifyLowStock`
- [ ] `TriggerReorderSuggestion`

**Tests:**
- [ ] Threshold monitoring
- [ ] Alert dispatch
- [ ] Decimal quantity calculations

### Acceptance Criteria
- Alerts fire when thresholds crossed
- Decimal quantities work across all operations
- Alert history tracked

---

## Phase 3: Batch/Lot Tracking (Weeks 5-7)

### Objectives
- Full batch lifecycle management
- Expiry date tracking with FEFO allocation
- Recall capability

### Deliverables

**Database:**
- [ ] Create `inventory_batches` table
- [ ] Add `batch_id` to movements and allocations
- [ ] Create expiry indexes

**Models:**
- [ ] `InventoryBatch` model with full lifecycle
- [ ] `BatchStatus` enum

**Services:**
- [ ] `BatchService` - create, split, merge, dispose
- [ ] `BatchAllocationService` - FEFO allocation
- [ ] `ExpiryMonitorService` - expiry alerts & holds

**Events:**
- [ ] `BatchCreated`, `BatchExpired`, `BatchRecalled`

**Commands:**
- [ ] `inventory:check-expiry` - daily expiry scan

**Tests:**
- [ ] Batch CRUD operations
- [ ] FEFO allocation ordering
- [ ] Expiry hold automation
- [ ] Recall propagation

### Acceptance Criteria
- Batches tracked through full lifecycle
- FEFO allocation prioritizes earliest expiry
- Expired batches auto-held
- Recall marks all affected stock

---

## Phase 4: Serial Number Management (Weeks 8-9)

### Objectives
- Unique serial tracking for high-value items
- Complete lifecycle history
- Warranty tracking

### Deliverables

**Database:**
- [ ] Create `inventory_serials` table
- [ ] Create `inventory_serial_history` table

**Models:**
- [ ] `InventorySerial` model
- [ ] `InventorySerialHistory` model
- [ ] `SerialStatus`, `SerialCondition` enums

**Services:**
- [ ] `SerialService` - lifecycle operations
- [ ] `SerialLookupService` - search & trace

**Traits:**
- [ ] `HasSerialNumbers` for products

**Tests:**
- [ ] Serial registration & allocation
- [ ] Status transitions
- [ ] History logging
- [ ] Warranty validation

### Acceptance Criteria
- Each serial uniquely tracked
- Full history from receipt to sale
- Warranty status queryable
- Customer assignment tracked

---

## Phase 5: Cost Valuation (Weeks 10-12)

### Objectives
- Implement FIFO, weighted average, standard costing
- Landed cost support
- Inventory valuation reports

### Deliverables

**Database:**
- [ ] Create `inventory_cost_layers` table
- [ ] Create `inventory_standard_costs` table
- [ ] Create `inventory_valuation_snapshots` table
- [ ] Add `unit_cost_minor` to movements

**Models:**
- [ ] `InventoryCostLayer` model
- [ ] `InventoryStandardCost` model
- [ ] `InventoryValuationSnapshot` model
- [ ] `CostingMethod` enum

**Services:**
- [ ] `CostLayerService` - FIFO layer management
- [ ] `WeightedAverageCostService` - running average
- [ ] `StandardCostService` - variance calculation
- [ ] `InventoryValuationService` - reporting

**Commands:**
- [ ] `inventory:valuation-snapshot` - periodic snapshots

**Tests:**
- [ ] FIFO cost consumption
- [ ] Weighted average recalculation
- [ ] Standard cost variance
- [ ] Valuation accuracy

### Acceptance Criteria
- COGS calculated correctly per method
- Variances tracked for standard cost
- Valuation snapshots for period close
- Landed costs distributed to inventory

---

## Phase 6: Allocation Strategy Evolution (Weeks 13-14)

### Objectives
- Add FEFO strategy for perishables
- Implement nearest location strategy
- Create backorder system

### Deliverables

**Database:**
- [ ] Create `inventory_backorders` table
- [ ] Add `strategy_metadata` to allocations

**Strategies:**
- [ ] `FefoStrategy` - first expiry first out
- [ ] `NearestLocationStrategy` - proximity based

**Models:**
- [ ] `InventoryBackorder` model

**Services:**
- [ ] Update `AllocationContext` with batch awareness
- [ ] `BackorderService` - queue & fulfillment

**Events:**
- [ ] `BackorderCreated`, `BackorderFulfilled`

**Tests:**
- [ ] FEFO allocation ordering
- [ ] Backorder queuing
- [ ] Auto-fulfillment on receipt

### Acceptance Criteria
- FEFO respects expiry dates
- Backorders auto-fulfilled when stock arrives
- Strategy selection configurable per product

---

## Phase 7: Replenishment & Forecasting (Weeks 15-17)

### Objectives
- Demand forecasting engine
- Automated reorder suggestions
- Supplier lead time tracking

### Deliverables

**Database:**
- [ ] Create `inventory_demand_history` table
- [ ] Create `inventory_supplier_leadtimes` table
- [ ] Create `inventory_reorder_suggestions` table

**Models:**
- [ ] `InventoryDemandHistory` model
- [ ] `InventorySupplierLeadtime` model
- [ ] `InventoryReorderSuggestion` model

**Services:**
- [ ] `DemandForecastService` - moving average, trend
- [ ] `SafetyStockCalculator` - service level based
- [ ] `ReorderPointCalculator` - EOQ integration
- [ ] `ReorderSuggestionService` - automation

**Commands:**
- [ ] `inventory:generate-suggestions` - daily run
- [ ] `inventory:update-demand-history` - after sales

**Tests:**
- [ ] Forecast accuracy
- [ ] Safety stock calculations
- [ ] Suggestion generation
- [ ] Lead time tracking

### Acceptance Criteria
- Demand forecasted with configurable methods
- Safety stock calculated per service level
- Reorder suggestions auto-generated
- Supplier lead times tracked

---

## Phase 8: Filament Dashboard (Weeks 18-19)

### Objectives
- Comprehensive warehouse management UI
- Real-time monitoring widgets
- Bulk operations

### Deliverables

**Widgets:**
- [ ] `InventoryOverviewWidget`
- [ ] `LowStockAlertsWidget`
- [ ] `ExpiringBatchesWidget`
- [ ] `ReorderSuggestionsWidget`
- [ ] `RecentMovementsWidget`

**Resources:**
- [ ] Update `InventoryLocationResource` with tree view
- [ ] Create `BatchResource`
- [ ] Create `SerialResource`
- [ ] Update `MovementResource` with batch/serial

**Actions:**
- [ ] `ReceiveStockAction` with batch/serial
- [ ] `TransferStockAction`
- [ ] `AdjustStockAction`
- [ ] `DisposeBatchAction`

**Tests:**
- [ ] Widget data accuracy
- [ ] Action validation
- [ ] Permission enforcement

### Acceptance Criteria
- Dashboard provides at-a-glance warehouse status
- All operations performable via Filament
- Role-based access control

---

## Phase 9: Analytics & Reporting (Weeks 20-21)

### Objectives
- Stock velocity analysis
- Inventory turnover reports
- Expiry waste tracking

### Deliverables

**Reports:**
- [ ] Inventory Valuation Report
- [ ] Stock Movement Report
- [ ] Expiry Analysis Report
- [ ] Dead Stock Report
- [ ] Stockout Analysis

**Metrics:**
- [ ] Days of Supply calculation
- [ ] Inventory Turnover Ratio
- [ ] Carrying Cost estimation
- [ ] Service Level tracking

**Exports:**
- [ ] Excel export for all reports
- [ ] PDF generation option

**Tests:**
- [ ] Report data accuracy
- [ ] Performance at scale
- [ ] Export functionality

### Acceptance Criteria
- Reports available in Filament
- Exportable to Excel/PDF
- Period comparison enabled

---

## Risk Mitigation

| Risk | Impact | Mitigation |
|------|--------|------------|
| Data migration complexity | High | Run parallel with old system first |
| Performance at scale | High | Aggressive indexing, query optimization |
| Existing integration breaks | Medium | Maintain backward compatibility |
| User adoption | Medium | Comprehensive documentation, training |

---

## Success Metrics

| Metric | Target |
|--------|--------|
| Inventory accuracy | 99.5% |
| Stockout reduction | 40% |
| Expired waste reduction | 50% |
| Pick time reduction | 30% |
| COGS accuracy | 100% |

---

## Navigation

**Previous:** [10-filament-enhancements.md](10-filament-enhancements.md)  
**Next:** [PROGRESS.md](PROGRESS.md)
