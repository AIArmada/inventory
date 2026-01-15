# Targeting Infrastructure Migration Plan

## Overview

This plan consolidates targeting/eligibility logic into a shared infrastructure, creates a dedicated promotions package, and removes duplicate code from cart.

### Current State
- `cart`: `BuiltInRulesFactory` (774 lines, 39 rules) - returns closures
- `vouchers`: `TargetingEngine` + 14 evaluators (~3,265 lines) - evaluator pattern
- `pricing`: `Promotion` model with `conditions` JSON - no evaluation engine

### Target State
- `commerce-support`: Shared `Targeting/` infrastructure
- `promotions`: New package for auto-applied discounts
- `vouchers`: Uses shared targeting (code-based discounts)
- `pricing`: Pure price calculation only
- `cart`: No targeting logic (just applies conditions)

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│  commerce-support                                               │
│  └── Targeting/ (shared evaluation engine)                      │
│      ├── Contracts/                                             │
│      ├── Enums/                                                 │
│      ├── TargetingEngine.php                                    │
│      ├── TargetingContext.php                                   │
│      └── Evaluators/ (16+ evaluators)                           │
└─────────────────────────────────────────────────────────────────┘
                           │
        ┌──────────────────┼──────────────────┐
        ▼                  ▼                  ▼
┌───────────────┐  ┌───────────────┐  ┌───────────────┐
│   Vouchers    │  │  Promotions   │  │  Affiliates   │
│               │  │   (NEW)       │  │               │
│ Code-based    │  │ Auto-applied  │  │ Referral      │
│ discounts     │  │ discounts     │  │ tracking      │
└───────────────┘  └───────────────┘  └───────────────┘
        │                  │                  │
        │   toCartCondition() / ConditionProvider
        └──────────────────┼──────────────────┘
                           ▼
┌─────────────────────────────────────────────────────────────────┐
│  Cart (receives conditions, applies math, calculates totals)    │
└─────────────────────────────────────────────────────────────────┘
```

---

## Phase 1: Create Shared Targeting in commerce-support

### 1.1 Create Directory Structure
- [ ] Create `packages/commerce-support/src/Targeting/`
- [ ] Create `packages/commerce-support/src/Targeting/Contracts/`
- [ ] Create `packages/commerce-support/src/Targeting/Enums/`
- [ ] Create `packages/commerce-support/src/Targeting/Evaluators/`

### 1.2 Create Contracts
- [ ] `TargetingEngineInterface.php`
- [ ] `TargetingRuleEvaluator.php`
- [ ] `TargetingContextInterface.php`

### 1.3 Create Enums
- [ ] `TargetingMode.php` (all, any, custom)
- [ ] `TargetingRuleType.php` (enum of all rule types)

### 1.4 Create Core Classes
- [ ] `TargetingEngine.php` (move from vouchers)
- [ ] `TargetingContext.php` (move from vouchers)
- [ ] `TargetingConfiguration.php` (move from vouchers)

### 1.5 Create Evaluators (move from vouchers + add new)

**From Vouchers (move as-is):**
- [ ] `CartValueEvaluator.php`
- [ ] `CartQuantityEvaluator.php`
- [ ] `ProductInCartEvaluator.php`
- [ ] `CategoryInCartEvaluator.php`
- [ ] `UserSegmentEvaluator.php`
- [ ] `UserAttributeEvaluator.php`
- [ ] `FirstPurchaseEvaluator.php`
- [ ] `CustomerLifetimeValueEvaluator.php`
- [ ] `TimeWindowEvaluator.php`
- [ ] `DayOfWeekEvaluator.php`
- [ ] `DateRangeEvaluator.php`
- [ ] `ChannelEvaluator.php`
- [ ] `DeviceEvaluator.php`
- [ ] `GeographicEvaluator.php`
- [ ] `ReferrerEvaluator.php`

**New Evaluators (porting cart's rules):**
- [ ] `MetadataEvaluator.php` (cart's metadata-*, has-metadata rules)
- [ ] `ItemAttributeEvaluator.php` (cart's item-attribute-* rules)
- [ ] `ItemConstraintEvaluator.php` (cart's item-quantity-*, item-price-* rules)
- [ ] `CurrencyEvaluator.php` (cart's currency-is rule)

### 1.6 Register in Service Provider
- [ ] Update `CommerceSupportServiceProvider` to bind `TargetingEngineInterface`

### 1.7 Verification
- [ ] PHPStan Level 6 passes for commerce-support
- [ ] Write tests for TargetingEngine
- [ ] Write tests for each evaluator

---

## Phase 2: Create Promotions Package

### 2.1 Create Package Structure
- [ ] Create `packages/promotions/`
- [ ] Create `packages/promotions/composer.json`
- [ ] Create `packages/promotions/src/`
- [ ] Create `packages/promotions/config/promotions.php`
- [ ] Create `packages/promotions/database/migrations/`

### 2.2 Create Service Provider
- [ ] `PromotionsServiceProvider.php`

### 2.3 Create Enums
- [ ] `PromotionType.php` (percentage, fixed, buy_x_get_y)
- [ ] `PromotionStatus.php` (active, inactive, scheduled, expired)

### 2.4 Create Models
- [ ] Move `Promotion.php` from pricing (refactor to use shared targeting)
- [ ] Create migration (or move from pricing)

### 2.5 Create Services
- [ ] `PromotionEngine.php` (evaluates which promotions apply)

### 2.6 Create Cart Integration
- [ ] `PromotionConditionProvider.php` (implements `ConditionProviderInterface`)

### 2.7 Verification
- [ ] PHPStan Level 6 passes
- [ ] Write tests for Promotion model
- [ ] Write tests for PromotionEngine
- [ ] Write tests for ConditionProvider

---

## Phase 3: Update Vouchers Package

### 3.1 Update Imports
- [ ] Replace `AIArmada\Vouchers\Targeting\*` with `AIArmada\CommerceSupport\Targeting\*`

### 3.2 Delete Targeting Directory
- [ ] Delete `packages/vouchers/src/Targeting/` (entire directory)
  - [ ] Backup: `TargetingEngine.php` (moved to commerce-support)
  - [ ] Backup: `TargetingContext.php` (moved to commerce-support)
  - [ ] Backup: `TargetingConfiguration.php` (moved to commerce-support)
  - [ ] Backup: `Contracts/` (moved to commerce-support)
  - [ ] Backup: `Enums/` (moved to commerce-support)
  - [ ] Backup: `Evaluators/` (moved to commerce-support)

### 3.3 Update Voucher Model
- [ ] Update `isApplicable()` to use shared `TargetingEngineInterface`
- [ ] Add `toCartCondition()` method if not present

### 3.4 Update or Create ConditionProvider
- [ ] `VoucherConditionProvider.php` (if not exists)

### 3.5 Update composer.json
- [ ] Add dependency on `commerce-support` (if not already)

### 3.6 Verification
- [ ] PHPStan Level 6 passes
- [ ] All voucher tests pass
- [ ] Integration tests with cart pass

---

## Phase 4: Update Pricing Package

### 4.1 Remove Promotion
- [ ] Delete `packages/pricing/src/Models/Promotion.php`
- [ ] Delete `packages/pricing/src/Enums/PromotionType.php`
- [ ] Delete promotion migration (or move to promotions package)
- [ ] Update `packages/pricing/config/pricing.php` (remove promotion tables)

### 4.2 Update References
- [ ] Remove promotion-related code from `PricingServiceProvider`
- [ ] Update any imports referencing Promotion

### 4.3 Verification
- [ ] PHPStan Level 6 passes
- [ ] All pricing tests pass (remove promotion tests)

---

## Phase 5: Remove from Cart

### 5.1 Delete Files
- [ ] Delete `packages/cart/src/Services/BuiltInRulesFactory.php` (-774 lines)
- [ ] Delete `packages/cart/src/Contracts/RulesFactoryInterface.php`
- [ ] Delete `packages/cart/src/Testing/ExampleRulesFactory.php`

### 5.2 Simplify ManagesDynamicConditions
- [ ] Remove rule evaluation logic
- [ ] Keep only:
  - Condition application (percentage/fixed math)
  - Dirty flag management
  - Metadata storage for reconstruction

### 5.3 Update Config
- [ ] Remove `rules_factory` config key
- [ ] Keep `condition_providers` config

### 5.4 Update Service Provider
- [ ] Remove `RulesFactoryInterface` binding

### 5.5 Update Tests
- [ ] Remove/update tests for BuiltInRulesFactory
- [ ] Update dynamic condition tests

### 5.6 Verification
- [ ] PHPStan Level 6 passes
- [ ] All cart tests pass
- [ ] Integration tests with vouchers/promotions pass

---

## Phase 6: Update Filament Packages

### 6.1 filament-promotions (new)
- [ ] Create `packages/filament-promotions/`
- [ ] Create resource for managing promotions
- [ ] Move promotion-related UI from filament-pricing if exists

### 6.2 filament-vouchers
- [ ] Update targeting form builders to use shared targeting
- [ ] Remove any duplicate targeting UI code

### 6.3 filament-cart
- [ ] Remove references to `BuiltInRulesFactory`
- [ ] Update condition management to use external providers

### 6.4 filament-pricing
- [ ] Remove promotion-related resources
- [ ] Keep only price/price-list/tier resources

---

## Phase 7: Final Verification

### 7.1 Static Analysis
- [ ] `./vendor/bin/phpstan analyse packages/commerce-support/src --level=6`
- [ ] `./vendor/bin/phpstan analyse packages/promotions/src --level=6`
- [ ] `./vendor/bin/phpstan analyse packages/vouchers/src --level=6`
- [ ] `./vendor/bin/phpstan analyse packages/pricing/src --level=6`
- [ ] `./vendor/bin/phpstan analyse packages/cart/src --level=6`

### 7.2 Tests
- [ ] `./vendor/bin/pest tests/src/CommerceSupport --parallel`
- [ ] `./vendor/bin/pest tests/src/Promotions --parallel`
- [ ] `./vendor/bin/pest tests/src/Vouchers --parallel`
- [ ] `./vendor/bin/pest tests/src/Pricing --parallel`
- [ ] `./vendor/bin/pest tests/src/Cart --parallel`

### 7.3 Integration Tests
- [ ] Voucher + Cart integration
- [ ] Promotion + Cart integration
- [ ] Multiple providers stacking correctly

---

## Line Count Impact

| Package | Before | After | Change |
|---------|--------|-------|--------|
| commerce-support | ~X | +3,000 | +3,000 (new shared code) |
| promotions | 0 | +800 | +800 (new package) |
| vouchers | ~Y | -3,265 | -3,265 (moved to shared) |
| pricing | ~Z | -400 | -400 (promotion removed) |
| cart | 13,726 | 12,500 | -1,226 (no targeting) |
| **Net Change** | | | **-1,091** (deduplication) |

---

## Rule Mapping: Cart → Shared Evaluators

| Cart BuiltInRulesFactory | Shared Evaluator |
|--------------------------|------------------|
| `always-true`, `always-false` | Not needed (trivial) |
| `has-any-item`, `min-items`, `max-items` | `CartQuantityEvaluator` |
| `min-quantity`, `max-quantity` | `CartQuantityEvaluator` |
| `subtotal-at-least`, `subtotal-below`, `subtotal-between` | `CartValueEvaluator` |
| `total-at-least`, `total-below`, `total-between` | `CartValueEvaluator` |
| `has-item`, `missing-item`, `item-list-includes-*` | `ProductInCartEvaluator` |
| `has-metadata`, `metadata-equals`, `metadata-*` | `MetadataEvaluator` (NEW) |
| `customer-tag` | `UserSegmentEvaluator` |
| `currency-is` | `CurrencyEvaluator` (NEW) |
| `cart-condition-exists`, `cart-condition-type-exists` | `CartConditionEvaluator` (NEW) |
| `day-of-week` | `DayOfWeekEvaluator` |
| `date-window` | `DateRangeEvaluator` |
| `time-window` | `TimeWindowEvaluator` |
| `item-attribute-equals`, `item-attribute-in` | `ItemAttributeEvaluator` (NEW) |
| `item-quantity-*`, `item-price-*`, `item-total-*` | `ItemConstraintEvaluator` (NEW) |
| `item-has-condition` | `CartConditionEvaluator` (NEW) |
| `item-id-prefix` | `ProductInCartEvaluator` |

---

## Notes

- **Do not delete files without backup** - use `git` or copy first
- **Run tests after each phase** - don't batch all changes
- **Update imports incrementally** - one package at a time
- **Filament packages can be done last** - they're UI only

---

## Progress

Started: 2026-01-14
Last Updated: 2026-01-14
Status: **Planning Complete**
