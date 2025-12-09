# Commerce Monorepo Full Audit Report

**Date**: December 5, 2025  
**Auditor**: GitHub Copilot (Claude Opus 4.5)  
**Scope**: All 21 packages in the commerce monorepo

---

## Executive Summary

| Severity | Count | Packages Affected |
|----------|-------|-------------------|
| **Critical** | 4 | cashier-chip, vouchers, filament-docs |
| **High** | 9 | affiliates, cashier-chip, filament-affiliates, filament-authz |
| **Medium** | 35+ | Nearly all packages |
| **Low** | 60+ | All packages |

---

## CRITICAL Issues (Must Fix Immediately)

### 1. cashier-chip - Hardcoded Table Names
**Files**: `Subscription.php`, `SubscriptionItem.php`  
**Issue**: Models use `protected $table = 'chip_subscriptions'` instead of config-based `getTable()`  
**Impact**: Ignores table prefix configuration, breaks multi-tenancy  
**Fix**: Replace with `getTable()` method using config

### 2. vouchers - VoucherTransaction Missing getTable()
**File**: `VoucherTransaction.php`  
**Issue**: Missing `getTable()` method entirely  
**Impact**: Ignores table configuration  
**Fix**: Add `getTable()` method

### 3. cashier-chip - Missing json_column_type Config
**File**: `config/cashier-chip.php`  
**Issue**: No `json_column_type` config despite having JSON columns  
**Impact**: May use wrong JSON column type in migrations  
**Fix**: Add `json_column_type` config key

### 4. filament-docs - Possible Invalid Dependency
**File**: `composer.json`  
**Issue**: Requires `aiarmada/docs` but package may not exist with this name  
**Impact**: Package installation failure  
**Fix**: Verify correct package name

---

## HIGH Issues (Fix Soon)

### 1. affiliates - Missing affiliate_id in Payouts
**File**: Migration `2024_01_04_000002_create_affiliate_payouts_table.php`  
**Issue**: `affiliate_payouts` table missing `affiliate_id` foreign key  
**Impact**: Cannot link payouts to affiliates  
**Fix**: Add migration to add `affiliate_id` column

### 2. affiliates - AffiliatePayout Model Missing Relationship
**File**: `AffiliatePayout.php`  
**Issue**: Model missing `affiliate()` BelongsTo relationship  
**Impact**: Cannot access payout's affiliate  
**Fix**: Add relationship and fillable

### 3. cashier-chip - Deprecated Property Patterns
**Files**: `Subscription.php`, `SubscriptionItem.php`  
**Issue**: Using `protected $casts` array instead of `casts()` method  
**Impact**: Not using PHP 8.4 best practices  
**Fix**: Convert to `casts()` method

### 4. filament-affiliates - Missing Resources Registration
**File**: `FilamentAffiliatesPlugin.php`  
**Issue**: `AffiliateFraudSignalResource` and `AffiliateProgramResource` not registered  
**Impact**: Resources exist but won't appear in panel  
**Fix**: Add to `register()` method resources array

### 5. filament-affiliates - Old Filament API Usage
**Files**: `AffiliateFraudSignalResource.php`, `AffiliateProgramResource.php`  
**Issue**: Using `Filament\Forms\Form` instead of `Filament\Schemas\Schema`  
**Impact**: Deprecated API in Filament 5  
**Fix**: Update to Schema API

### 6. filament-authz - shouldRegisterNavigation() Returns Nullable
**Files**: `RoleResource.php`, `PermissionResource.php`, `UserResource.php`  
**Issue**: `$user?->can()` can return `null` but method expects `bool`  
**Impact**: Potential type errors  
**Fix**: Explicitly cast to bool

### 7. filament-authz - Missing DB Import
**File**: `PermissionStatsWidget.php`  
**Issue**: Uses `DB` facade without import  
**Impact**: Relies on global alias  
**Fix**: Add `use Illuminate\Support\Facades\DB;`

---

## MEDIUM Issues

### Database & Models

| Package | File | Issue |
|---------|------|-------|
| affiliates | All Models | Missing type annotations on relations |
| docs | Doc.php, DocTemplate.php, DocStatusHistory.php | Missing PHPDoc `@property` annotations |
| docs | All Models | Relations missing type-safe generic PHPDoc |
| inventory | InventoryLevel.php | Missing `booted()` cascade (may be intentional) |
| stock | config/stock.php | Missing `json_column_type` config |
| stock | Stock.php | `stockable()` relation missing type-safe generic |
| vouchers | VoucherTransaction.php | Using old-style `$casts` property |
| vouchers | VoucherTransaction.php | Missing PHPDoc annotations |
| jnt | config/jnt.php | Missing `database.tables` configuration |
| filament-cart | Cart.php, CartItem.php, CartCondition.php | Hardcoded `$table` instead of `getTable()` |

### Config Issues

| Package | Issue |
|---------|-------|
| affiliates | Excessive env() wrappers on table_names |
| inventory | Uses `table_names` inconsistently (should be `database.tables`) |
| stock | Uses `table_name` at root level (inconsistent) |
| filament-affiliates | Navigation group inconsistency ('E-commerce' vs 'E-Commerce') |
| filament-jnt | Config keys don't match resource navigation_sort keys |
| filament-authz | Missing feature flags referenced in Plugin |

### Service Providers

| Package | File | Issue |
|---------|------|-------|
| commerce-support | ServiceProvider | Empty `packageRegistered()` and `packageBooted()` methods |
| filament-inventory | ServiceProvider | Empty render hook callback |
| filament-stock | ServiceProvider | Empty render hook callback |
| filament-vouchers | ServiceProvider | Missing widget Livewire registrations |

### Filament Resources

| Package | Issue |
|---------|-------|
| filament-affiliates | Hardcoded navigation properties instead of config |
| filament-affiliates | Using deprecated `BadgeColumn` |
| filament-cart | Hardcoded navigation sort values |
| filament-cart | `getNavigationBadge()` return type should be `?string` |
| filament-authz | Not using PackageServiceProvider pattern |
| filament-authz | `canPerform()` missing proper user type hint |

---

## LOW Issues

### Code Quality

| Package | Issue |
|---------|-------|
| cart | Backup file should be removed |
| cashier | Missing PHPDoc on `__call` magic method |
| chip | Payment migration missing index on foreign key |
| chip | Coding style: missing space in `if (!$this->...)` |
| jnt | Models not marked as `final` |
| vouchers | Models not marked as `final` |
| All filament-* | Missing `php` requirement in composer.json |
| filament-chip | PHPStan ignore comments for macro methods |
| filament-authz | Hardcoded package paths in discovery |

### Consistency Issues

| Issue | Affected Packages |
|-------|------------------|
| PHP version constraint missing | filament-affiliates, filament-cart, filament-chip, filament-docs, filament-inventory, filament-jnt |
| Filament version inconsistency (`^4.2 || ^5.0` vs `^5.0`) | filament-affiliates vs others |
| Polling interval format (int vs string) | filament-cart vs filament-chip |
| `$tenantOwnershipRelationshipName` missing | Several filament resources |
| `infolist()` method missing on View-enabled resources | filament-inventory resources |

---

## Files to Fix (Priority Order)

### Critical Files
1. `packages/cashier-chip/src/Models/Subscription.php`
2. `packages/cashier-chip/src/Models/SubscriptionItem.php`
3. `packages/cashier-chip/config/cashier-chip.php`
4. `packages/vouchers/src/Models/VoucherTransaction.php`

### High Priority Files
1. `packages/affiliates/database/migrations/*_create_affiliate_payouts_table.php`
2. `packages/affiliates/src/Models/AffiliatePayout.php`
3. `packages/filament-affiliates/src/FilamentAffiliatesPlugin.php`
4. `packages/filament-affiliates/src/Resources/AffiliateFraudSignalResource.php`
5. `packages/filament-affiliates/src/Resources/AffiliateProgramResource.php`
6. `packages/filament-authz/src/Resources/RoleResource.php`
7. `packages/filament-authz/src/Resources/PermissionResource.php`
8. `packages/filament-authz/src/Resources/UserResource.php`
9. `packages/filament-authz/src/Widgets/PermissionStatsWidget.php`

### Medium Priority Files
1. `packages/stock/config/stock.php`
2. `packages/docs/src/Models/*.php`
3. `packages/affiliates/src/Models/*.php`
4. `packages/filament-cart/src/Models/*.php`
5. `packages/filament-authz/config/filament-authz.php`
6. `packages/filament-jnt/config/filament-jnt.php`

---

## Next Steps

1. Fix all Critical issues
2. Fix all High issues
3. Run PHPStan level 6 analysis
4. Run Pint for code style
5. Run tests for affected packages
6. Commit fixes in logical groups
