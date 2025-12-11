# Multitenancy Implementation Plan

## Overview

This plan audits all packages in the commerce monorepo and identifies which models need multitenancy support via the `HasOwner` trait from `commerce-support`.

---

## Current Status

### ✅ Packages WITH HasOwner Support

| Package | Models with HasOwner |
|---------|---------------------|
| `commerce-support` | Provides: `HasOwner` trait, `OwnerResolverInterface` |
| `orders` | `Order` |
| `shipping` | `Shipment`, `ShippingZone`, `ReturnAuthorization` |
| `products` | `Product`, `Category`, `Collection` |
| `customers` | `Customer`, `CustomerGroup`, `Segment` |
| `docs` | `Doc`, `DocTemplate` |

### ❌ Packages WITHOUT HasOwner Support (Need Implementation)

| Package | Models Missing HasOwner | Priority |
|---------|------------------------|----------|
| `pricing` | `PriceList`, `Promotion`, `Price`, `PriceTier` | 🔴 High |
| `inventory` | `InventoryLocation`, `InventoryLevel`, `InventoryBatch`, `InventorySerial`, etc. | 🔴 High |
| `tax` | `TaxZone`, `TaxRate`, `TaxClass`, `TaxExemption` | 🔴 High |
| `vouchers` | `Voucher`, `Campaign`, `GiftCard`, `VoucherWallet` | 🔴 High |
| `affiliates` | `Affiliate`, `AffiliateProgram`, `AffiliateConversion`, `AffiliatePayout` | 🟡 Medium |
| `cart` | `Condition`, `CartEvent` | 🟡 Medium |
| `jnt` | `JntOrder` | 🟡 Medium |
| `filament-authz` | `AccessPolicy`, `RoleTemplate`, `PermissionGroup`, `ScopedPermission` | 🟡 Medium |

---

## Implementation Details by Package

### 1. `pricing` (Priority: High)

**Root model:** `PriceList`  
**Child models:** `Price`, `PriceTier`, `Promotion`

| Model | Add HasOwner? | Reason |
|-------|---------------|--------|
| `PriceList` | ✅ Yes | Root entity - tenants have their own price lists |
| `Price` | ❌ No | Child of PriceList - inherit via parent |
| `PriceTier` | ❌ No | Child of Price - inherit via parent |
| `Promotion` | ✅ Yes | Standalone - tenants have their own promotions |

**Migration:**
```php
// Add to existing migration or create new
$table->nullableMorphs('owner');
```

---

### 2. `inventory` (Priority: High)

**Root model:** `InventoryLocation`  
**Child models:** All others reference location

| Model | Add HasOwner? | Reason |
|-------|---------------|--------|
| `InventoryLocation` | ✅ Yes | Root entity - tenants have their own warehouses |
| `InventoryLevel` | ❌ No | References Location - inherit via Location |
| `InventoryBatch` | ❌ No | References Location - inherit via parent |
| `InventorySerial` | ❌ No | Child of Batch - inherit via parent |
| `InventoryMovement` | ❌ No | Audit log - inherit via Location |
| `InventoryAllocation` | ❌ No | References Level - inherit via parent |
| Other models | ❌ No | All reference Location indirectly |

**Migration:**
```php
// For inventory_locations table
$table->nullableMorphs('owner');
```

---

### 3. `tax` (Priority: High)

**Root model:** `TaxZone`  
**Child models:** `TaxRate`, `TaxClass`, `TaxExemption`

| Model | Add HasOwner? | Reason |
|-------|---------------|--------|
| `TaxZone` | ✅ Yes | Root entity - tenants have their own tax zones |
| `TaxRate` | ❌ No | Child of TaxZone - inherit via parent |
| `TaxClass` | ✅ Yes | Standalone classification - tenant-specific |
| `TaxExemption` | ❌ No | References Customer (already has owner) |

**Migration:**
```php
// For tax_zones and tax_classes tables
$table->nullableMorphs('owner');
```

---

### 4. `vouchers` (Priority: High)

| Model | Add HasOwner? | Reason |
|-------|---------------|--------|
| `Voucher` | ✅ Yes | Root entity - tenants issue their own vouchers |
| `VoucherUsage` | ❌ No | Child of Voucher - inherit via parent |
| `VoucherTransaction` | ❌ No | Child of Voucher - inherit via parent |
| `VoucherWallet` | ❌ No | References Customer (already has owner) |
| `Campaign` | ✅ Yes | Root entity - tenant campaigns |
| `CampaignVariant` | ❌ No | Child of Campaign |
| `CampaignEvent` | ❌ No | Audit log |
| `GiftCard` | ✅ Yes | Root entity - tenant gift cards |
| `GiftCardTransaction` | ❌ No | Child of GiftCard |

**Migration:**
```php
// For vouchers, campaigns, gift_cards tables
$table->nullableMorphs('owner');
```

---

### 5. `affiliates` (Priority: Medium)

| Model | Add HasOwner? | Reason |
|-------|---------------|--------|
| `AffiliateProgram` | ✅ Yes | Root entity - tenant programs |
| `Affiliate` | ❌ No | Belongs to Program |
| `AffiliateConversion` | ❌ No | References Affiliate |
| `AffiliatePayout` | ❌ No | References Affiliate |
| `AffiliateNetwork` | ✅ Yes | Tenant networks |
| Other models | ❌ No | All reference Program/Affiliate |

**Note:** Migrations already have `owner_type`/`owner_id` columns but models don't use trait.

**Migration:** Already exists - just add trait to models.

---

### 6. `cart` (Priority: Medium)

| Model | Add HasOwner? | Reason |
|-------|---------------|--------|
| `Condition` | ✅ Yes | Stored conditions need scoping |
| `CartEvent` | ❌ No | Audit log - cart session already scoped |
| `CartItem` | ❌ No | Not a separate entity in DB (session-based) |

**Migration:**
```php
// For conditions table
$table->nullableMorphs('owner');
```

---

### 7. `jnt` (Priority: Medium)

| Model | Add HasOwner? | Reason |
|-------|---------------|--------|
| `JntOrder` | ✅ Yes | Shipments are tenant-specific (migration exists) |
| `JntOrderItem` | ❌ No | Child of JntOrder |
| `JntOrderParcel` | ❌ No | Child of JntOrder |
| `JntTrackingEvent` | ❌ No | References JntOrder |
| `JntWebhookLog` | ❌ No | Audit log - reference by tracking number |

**Migration:** Already exists (`2025_01_15_000006_add_owner_columns_to_jnt_orders_table.php`) - just add trait.

---

### 8. `filament-authz` (Priority: Medium)

| Model | Add HasOwner? | Reason |
|-------|---------------|--------|
| `AccessPolicy` | ✅ Yes | Tenant-specific policies |
| `RoleTemplate` | ✅ Yes | Tenant role templates |
| `PermissionGroup` | ✅ Yes | Tenant permission groups |
| `ScopedPermission` | ❌ No | References user/role - context implicit |
| `Delegation` | ❌ No | References users |
| `PermissionRequest` | ❌ No | References user |
| `PermissionAuditLog` | ❌ No | Audit log |
| `PermissionSnapshot` | ❌ No | Snapshot data |

**Migration:**
```php
// For access_policies, role_templates, permission_groups tables
$table->nullableMorphs('owner');
```

---

## Packages NOT Needing HasOwner

| Package | Reason |
|---------|--------|
| `chip` | No persistent models - API wrapper only |
| `cashier`, `cashier-chip` | Uses Stripe/Chip customer IDs, not local models |
| `docs` | ✅ Already implemented |
| `filament-*` (except authz) | UI packages - use base package scoping |
| `csuite` | Utility package |

---

## Implementation Checklist

### Phase 1: High Priority (Data Integrity)
- [ ] `pricing` - Add `HasOwner` to `PriceList`, `Promotion`
- [ ] `inventory` - Add `HasOwner` to `InventoryLocation`
- [ ] `tax` - Add `HasOwner` to `TaxZone`, `TaxClass`
- [ ] `vouchers` - Add `HasOwner` to `Voucher`, `Campaign`, `GiftCard`

### Phase 2: Medium Priority (Completeness)
- [ ] `affiliates` - Add `HasOwner` to `AffiliateProgram`, `AffiliateNetwork`
- [ ] `cart` - Add `HasOwner` to `Condition`
- [ ] `jnt` - Add `HasOwner` to `JntOrder`
- [ ] `filament-authz` - Add `HasOwner` to `AccessPolicy`, `RoleTemplate`, `PermissionGroup`

### Phase 3: Filament Integration
- [ ] Create base `OwnerResolver` that uses `Filament::getTenant()`
- [ ] Update Filament resources to scope queries using `forOwner()`
- [ ] Add config toggle: `'multitenancy.enabled' => false` (default off)

---

## Per-Model Changes Required

### For each model adding HasOwner:

1. **Import trait:**
```php
use AIArmada\CommerceSupport\Traits\HasOwner;
```

2. **Use trait:**
```php
class PriceList extends Model
{
    use HasOwner;
}
```

3. **Add to fillables:**
```php
protected $fillable = [
    'owner_type',
    'owner_id',
    // ... other fields
];
```

4. **Create/update migration** (if columns don't exist):
```php
$table->nullableMorphs('owner');
```

5. **Add PHPDoc properties:**
```php
/**
 * @property string|null $owner_type
 * @property string|null $owner_id
 */
```

---

## Testing Requirements

For each model with `HasOwner`:

1. Test `forOwner()` scope returns correct records
2. Test `globalOnly()` scope returns only ownerless records
3. Test `assignOwner()` and `removeOwner()` methods
4. Test `belongsToOwner()` check
5. Verify Filament resources filter correctly

---

## Summary

| Status | Count | Models |
|--------|-------|--------|
| ✅ Already implemented | 12 | Order, Shipment, ShippingZone, ReturnAuthorization, Product, Category, Collection, Customer, CustomerGroup, Segment, Doc, DocTemplate |
| 🔴 High priority (add) | 8 | PriceList, Promotion, InventoryLocation, TaxZone, TaxClass, Voucher, Campaign, GiftCard |
| 🟡 Medium priority (add) | 7 | AffiliateProgram, AffiliateNetwork, Condition, JntOrder, AccessPolicy, RoleTemplate, PermissionGroup |
| ⚪ No action needed | ~50+ | Child models, audit logs, API wrappers |

**Total root models needing HasOwner:** 15 (8 high + 7 medium)  
**Already implemented:** 12  
**Grand total multitenancy-capable root models:** 27
