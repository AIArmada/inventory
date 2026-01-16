---
title: Actions
---

# Actions

The package provides 8 table and page actions for inventory operations.

## Stock Operations

### Receive Stock

Add inventory to a location (purchase receipts, returns, production output).

```php
use AIArmada\FilamentInventory\Actions\ReceiveStockAction;

ReceiveStockAction::make()
```

**Fields:**

| Field | Type | Description |
|-------|------|-------------|
| product_type | Select | Morphable product class |
| product_id | TextInput | Product identifier |
| quantity | TextInput (numeric) | Quantity to receive |
| unit_cost | TextInput (numeric) | Cost per unit |
| reference | TextInput | PO number, return ref, etc. |
| reason | Textarea | Notes |

**Events Dispatched:**
- Creates `receipt` movement
- Updates stock level
- May trigger allocation fulfillment

### Ship Stock

Remove inventory from a location (sales, transfers out, consumption).

```php
use AIArmada\FilamentInventory\Actions\ShipStockAction;

ShipStockAction::make()
```

**Fields:**

| Field | Type | Description |
|-------|------|-------------|
| product_type | Select | Morphable product class |
| product_id | TextInput | Product identifier |
| quantity | TextInput (numeric) | Quantity to ship |
| reference | TextInput | Order number, transfer ref |
| reason | Textarea | Notes |

**Validation:**
- Quantity must be ≤ available stock
- Displayed available quantity updates live

**Events Dispatched:**
- Creates `shipment` movement
- Updates stock level

### Transfer Stock

Move inventory between locations.

```php
use AIArmada\FilamentInventory\Actions\TransferStockAction;

TransferStockAction::make()
```

**Fields:**

| Field | Type | Description |
|-------|------|-------------|
| product_type | Select | Morphable product class |
| product_id | TextInput | Product identifier |
| from_location_id | Select | Source location |
| to_location_id | Select | Destination location |
| quantity | TextInput (numeric) | Quantity to transfer |
| reference | TextInput | Transfer number |
| reason | Textarea | Notes |

**Behavior:**
- Creates paired movements (out/in)
- Available quantity updates live when source changes
- Cannot transfer to same location

### Adjust Stock

Correct inventory discrepancies (cycle count corrections, damage write-offs).

```php
use AIArmada\FilamentInventory\Actions\AdjustStockAction;

AdjustStockAction::make()
```

**Fields:**

| Field | Type | Description |
|-------|------|-------------|
| adjustment_type | Radio | `add` or `remove` |
| quantity | TextInput (numeric) | Adjustment amount |
| reference | TextInput | Adjustment reference |
| reason | Textarea | Required explanation |

**Validation:**
- Cannot remove more than available
- Reason is mandatory

**Events Dispatched:**
- Creates `adjustment` movement

## Cycle Count

Physical inventory verification workflow.

```php
use AIArmada\FilamentInventory\Actions\CycleCountAction;

CycleCountAction::make()
```

**Fields:**

| Field | Type | Description |
|-------|------|-------------|
| counted_quantity | TextInput (numeric) | Physical count result |
| reference | TextInput | Count sheet reference |
| notes | Textarea | Discrepancy explanation |

**Behavior:**
- Displays current system quantity
- Auto-calculates variance
- Creates adjustment if variance ≠ 0
- Updates inventory accuracy metrics

## Allocation Management

### Release Allocation

Cancel or release reserved inventory.

```php
use AIArmada\FilamentInventory\Actions\ReleaseAllocationAction;

ReleaseAllocationAction::make()
```

**Fields:**

| Field | Type | Description |
|-------|------|-------------|
| quantity | TextInput (numeric) | Quantity to release |
| reason | Textarea | Release reason |

**Behavior:**
- Cannot release more than allocated
- Updates stock level available quantity
- Changes allocation status to `released`

## Reorder Management

### Approve Reorder Suggestion

Accept system-generated reorder recommendation.

```php
use AIArmada\FilamentInventory\Actions\ApproveReorderSuggestionAction;

ApproveReorderSuggestionAction::make()
```

**Behavior:**
- Marks suggestion as `approved`
- Typically triggers procurement workflow
- Success notification displayed

### Reject Reorder Suggestion

Dismiss a reorder recommendation.

```php
use AIArmada\FilamentInventory\Actions\RejectReorderSuggestionAction;

RejectReorderSuggestionAction::make()
```

**Behavior:**
- Marks suggestion as `rejected`
- Danger-colored button
- Removes from pending list

## Using Actions Programmatically

All actions use the underlying inventory package services:

```php
use AIArmada\Inventory\Actions\ReceiveStock;

app(ReceiveStock::class)->execute(
    location: $location,
    productType: Product::class,
    productId: $product->id,
    quantity: 100,
    unitCost: 10.00,
    reference: 'PO-001',
    reason: 'Initial stock',
);
```

## Action Authorization

Actions respect Filament's authorization system:

```php
// In your policy
public function receiveStock(User $user, InventoryLocation $location): bool
{
    return $user->can('manage inventory');
}
```

## Customizing Actions

Extend the base action classes:

```php
use AIArmada\FilamentInventory\Actions\ReceiveStockAction;

class CustomReceiveStockAction extends ReceiveStockAction
{
    protected function afterReceive(array $data): void
    {
        // Custom logic after stock received
        Notification::make()
            ->title('Stock received')
            ->sendToDatabase($this->getRecord()->location->manager);
    }
}
```

## Multitenancy

All actions automatically scope to the current owner via the core inventory package's owner-aware actions.
