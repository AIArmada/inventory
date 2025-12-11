# Task: Add Multitenancy Support to Commerce Packages

**Created:** 2025-12-12  
**Status:** ✅ Completed  
**Priority:** High

## Objective

Add `HasOwner` trait from `commerce-support` to all root-level Eloquent models across commerce packages to enable owner-based multitenancy scoping.

## Background

The `commerce-support` package provides multitenancy infrastructure:
- `OwnerResolverInterface` — contract for resolving current tenant
- `NullOwnerResolver` — default no-op (disables multitenancy)
- `HasOwner` trait — adds polymorphic owner scoping to models

Some packages already implement this (`orders`, `shipping`, `products`, `customers`, `docs`). This task extends coverage to remaining packages.

## Scope

### Phase 1: High Priority Packages

| Package | Models | Migration Needed |
|---------|--------|-----------------|
| `pricing` | `PriceList`, `Promotion` | ✅ Done |
| `inventory` | `InventoryLocation` | ✅ Done |
| `tax` | `TaxZone`, `TaxClass` | ✅ Done |
| `vouchers` | `Voucher` | ✅ Done |

### Phase 2: Medium Priority Packages

| Package | Models | Migration Needed |
|---------|--------|-----------------|
| `affiliates` | `Affiliate` | ✅ Done |
| `cart` | `Condition` | ✅ Done |
| `jnt` | `JntOrder` | ✅ Done |
| `filament-authz` | `AccessPolicy`, `RoleTemplate`, `PermissionGroup` | ✅ Done |

---

## Implementation Steps

### Phase 1: High Priority

#### Step 1.1: `pricing` Package
- [x] Create migration `add_owner_columns_to_price_lists_table.php`
- [x] Create migration `add_owner_columns_to_promotions_table.php`
- [x] Add `HasOwner` trait to `PriceList` model
- [x] Add `HasOwner` trait to `Promotion` model
- [x] Update `$fillable` arrays with `owner_type`, `owner_id`
- [x] Add PHPDoc `@property` annotations
- [x] Run tests: `./vendor/bin/pest tests/src/Pricing --parallel`

#### Step 1.2: `inventory` Package
- [x] Create migration `add_owner_columns_to_inventory_locations_table.php`
- [x] Add `HasOwner` trait to `InventoryLocation` model
- [x] Update `$fillable` array
- [x] Add PHPDoc annotations
- [x] Run tests: `./vendor/bin/pest tests/src/Inventory --parallel`

#### Step 1.3: `tax` Package
- [x] Create migration `add_owner_columns_to_tax_zones_table.php`
- [x] Create migration `add_owner_columns_to_tax_classes_table.php`
- [x] Add `HasOwner` trait to `TaxZone` model
- [x] Add `HasOwner` trait to `TaxClass` model
- [x] Update `$fillable` arrays
- [x] Add PHPDoc annotations
- [x] Run tests: `./vendor/bin/pest tests/src/Tax --parallel`

#### Step 1.4: `vouchers` Package
- [x] Create migration `add_owner_columns_to_vouchers_table.php`
- [x] Add `HasOwner` trait to `Voucher` model
- [x] Update `$fillable` arrays
- [x] Add PHPDoc annotations
- [x] Run tests: `./vendor/bin/pest tests/src/Vouchers --parallel`

### Phase 2: Medium Priority

#### Step 2.1: `affiliates` Package
- [x] Add `HasOwner` trait to `Affiliate` model (migration exists)
- [x] Update `$fillable` arrays
- [x] Add PHPDoc annotations
- [x] Run tests: `./vendor/bin/pest tests/src/Affiliates --parallel`

#### Step 2.2: `cart` Package
- [x] Create migration `add_owner_columns_to_conditions_table.php`
- [x] Add `HasOwner` trait to `Condition` model
- [x] Update `$fillable` array
- [x] Add PHPDoc annotations
- [x] Run tests: `./vendor/bin/pest tests/src/Cart --parallel`

#### Step 2.3: `jnt` Package
- [x] Add `HasOwner` trait to `JntOrder` model (migration exists)
- [x] Update `$fillable` array
- [x] Add PHPDoc annotations
- [x] Run tests: `./vendor/bin/pest tests/src/Jnt --parallel`

#### Step 2.4: `filament-authz` Package
- [x] Create migration `add_owner_columns_to_access_policies_table.php`
- [x] Create migration `add_owner_columns_to_role_templates_table.php`
- [x] Create migration `add_owner_columns_to_permission_groups_table.php`
- [x] Add `HasOwner` trait to `AccessPolicy` model
- [x] Add `HasOwner` trait to `RoleTemplate` model
- [x] Add `HasOwner` trait to `PermissionGroup` model
- [x] Update `$fillable` arrays
- [x] Add PHPDoc annotations
- [x] Run tests: `./vendor/bin/pest tests/src/FilamentAuthz --parallel`

### Phase 3: Validation

- [x] Run PHPStan: `./vendor/bin/phpstan analyse --level=6`
- [x] Run Pint: `./vendor/bin/pint`
- [x] Run full test suite for affected packages
- [x] Commit changes with message: `feat: add multitenancy support to commerce packages`

---

## Migration Template

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('table_name', function (Blueprint $table) {
            $table->nullableMorphs('owner');
        });
    }

    public function down(): void
    {
        Schema::table('table_name', function (Blueprint $table) {
            $table->dropMorphs('owner');
        });
    }
};
```

## Model Template

```php
<?php

namespace AIArmada\Package\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string|null $owner_type
 * @property string|null $owner_id
 */
class ModelName extends Model
{
    use HasOwner;

    protected $fillable = [
        'owner_type',
        'owner_id',
        // ... existing fillables
    ];
}
```

---

## Acceptance Criteria

1. ✅ All 12 models have `HasOwner` trait
2. ✅ All migrations create `owner_type` and `owner_id` columns
3. ✅ All models have `owner_type`, `owner_id` in `$fillable` (where applicable)
4. ✅ PHPDoc `@property` annotations added
5. ✅ All package tests pass
6. ✅ PHPStan level 6 passes
7. ✅ Pint formatting applied

---

## Progress Tracker

| Package | Migration | Model | Fillable | PHPDoc | Tests | Config Toggle |
|---------|-----------|-------|----------|--------|-------|---------------|
| `pricing` | ✅ | ✅ + scopeForOwner | N/A (guarded) | ✅ | ✅ | ✅ `pricing.owner.enabled` |
| `inventory` | ✅ (existed) | ✅ + scopeForOwner | ✅ | ✅ | ✅ | ✅ `inventory.owner.enabled` |
| `tax` | ✅ | ✅ + scopeForOwner | N/A (guarded) | ✅ | ✅ | ✅ `tax.owner.enabled` |
| `vouchers` | ✅ (existed) | ✅ + scopeForOwner | ✅ | ✅ | ✅ | ✅ `vouchers.owner.enabled` |
| `affiliates` | ✅ (existed) | ✅ + scopeForOwner | ✅ | ✅ | ✅ | ✅ `affiliates.owner.enabled` |
| `cart` | ✅ | ✅ + scopeForOwner | ✅ | ✅ | ✅ | ✅ `cart.owner.enabled` |
| `jnt` | ✅ (existed) | ✅ + scopeForOwner | ✅ | ✅ | ✅ | ✅ `jnt.owner.enabled` |
| `filament-authz` | ✅ | ✅ + scopeForOwner | ✅ | ✅ | ✅ | ✅ `filament-authz.owner.enabled` |

### Implementation Notes

**All packages now use the shared HasOwner trait from commerce-support with config-guarded scopeForOwner.**

All packages now consistently implement the config toggle pattern:
- `inventory` — `InventoryLocation` (checks `inventory.owner.enabled`)
- `vouchers` — `Voucher` (checks `vouchers.owner.enabled`)
- `affiliates` — `Affiliate` (checks `affiliates.owner.enabled`, auto-assign on create)
- `jnt` — `JntOrder` (checks `jnt.owner.enabled`, auto-resolves owner)
- `pricing` — `PriceList`, `Promotion` (checks `pricing.owner.enabled`)
- `tax` — `TaxZone`, `TaxClass` (checks `tax.owner.enabled`)
- `cart` — `Condition` (checks `cart.owner.enabled`)
- `filament-authz` — `AccessPolicy`, `RoleTemplate`, `PermissionGroup` (checks `filament-authz.owner.enabled`)

### Audit Changes (December 2025)

**Issue Found:** Inconsistent multitenancy implementation across packages.

**Resolution:** Added `owner.enabled` config and `scopeForOwner` override to:
- `pricing` config + `PriceList`, `Promotion` models
- `tax` config + `TaxZone`, `TaxClass` models  
- `cart` `Condition` model (config already existed)
- `filament-authz` config + `AccessPolicy`, `RoleTemplate`, `PermissionGroup` models

All packages now follow the same pattern:
1. Config toggle: `<package>.owner.enabled` (default: false)
2. When disabled: scoping is bypassed (single-tenant mode)
3. When enabled: owner scoping applies with global record inclusion
