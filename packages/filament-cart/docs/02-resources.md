# Resources

The plugin provides four Filament resources for cart management, all located under the **E-Commerce** navigation group.

## Cart Resource

**Route:** `/admin/carts`  
**Model:** `AIArmada\FilamentCart\Models\Cart`

Read-only resource displaying normalized cart snapshots.

### Table Columns

| Column | Description |
|--------|-------------|
| ID | UUID with copy action |
| Instance | Badge showing cart type (default, wishlist, quote, layaway) |
| Items | Count of line items |
| Conditions | Count of applied conditions |
| Subtotal | Pre-condition total |
| Total | Final calculated total |
| Created | Creation timestamp |
| Updated | Last modification timestamp |

### Filters

- **Instance** — Filter by cart type
- **Date Range** — Filter by creation date
- **Has Conditions** — Toggle to show carts with/without conditions

### Actions

- View cart details with nested items and conditions

---

## Cart Item Resource

**Route:** `/admin/cart-items`  
**Model:** `AIArmada\FilamentCart\Models\CartItem`

Read-only resource for individual line item analysis.

### Table Columns

| Column | Description |
|--------|-------------|
| ID | UUID with copy action |
| Cart | Link to parent cart |
| Name | Product/item name |
| Price | Unit price |
| Quantity | Item quantity |
| Total | Calculated line total |
| Conditions | Count of item-level conditions |
| Created | Creation timestamp |

### Filters

- **Price Range** — Filter by unit price
- **Quantity Range** — Filter by quantity
- **Has Conditions** — Toggle item-level conditions

### Search

Searches item name and JSON attributes.

---

## Cart Condition Resource

**Route:** `/admin/cart-conditions`  
**Model:** `AIArmada\FilamentCart\Models\CartCondition`

Read-only resource showing conditions applied to carts.

### Table Columns

| Column | Description |
|--------|-------------|
| ID | UUID with copy action |
| Cart | Link to parent cart |
| Name | Condition display name |
| Type | Badge (discount, fee, tax, shipping) |
| Target | Application target (subtotal, total, item) |
| Value | Percentage or fixed amount |
| Order | Calculation sequence |
| Created | Creation timestamp |

### Filters

- **Type** — Filter by condition type
- **Target** — Filter by application target

---

## Condition Resource

**Route:** `/admin/conditions`  
**Model:** `AIArmada\Cart\Models\Condition`

Full CRUD resource for managing reusable condition templates.

### Table Columns

| Column | Description |
|--------|-------------|
| Name | Unique identifier |
| Display Name | User-facing label |
| Type | Condition type |
| Target | Application target |
| Value | Percentage or fixed value |
| Dynamic | Whether condition uses rules |
| Active | Enabled status |
| Global | Auto-apply to all carts |

### Form Fields

**Basic Information:**
- Name (unique slug)
- Display Name
- Description
- Type (discount, tax, fee, shipping, surcharge)

**Pricing:**
- Target (cart subtotal, grand total, item)
- Value (supports +100, -10%, *1.5 syntax)
- Order (calculation sequence)

**Rules (Dynamic Conditions):**
- Factory Keys (min-items, total-at-least, etc.)
- Context (key-value configuration)

**Status:**
- Is Active
- Is Global

### Actions

- Create new condition templates
- Edit existing conditions
- Delete unused conditions

---

## Navigation Configuration

Customize the navigation group in `config/filament-cart.php`:

```php
return [
    'navigation_group' => 'E-Commerce',
    
    'resources' => [
        'navigation_sort' => [
            'carts' => 30,
        ],
    ],
];
```

## Extending Resources

Create custom resources by extending the base classes:

```php
namespace App\Filament\Resources;

use AIArmada\FilamentCart\Resources\CartResource as BaseCartResource;

class CustomCartResource extends BaseCartResource
{
    protected static ?string $navigationLabel = 'Shopping Carts';
    
    // Add custom functionality
}
```

Register your custom resource instead of the default in a custom plugin.
