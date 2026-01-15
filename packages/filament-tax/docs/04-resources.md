---
title: Resources
---

# Resources

The plugin provides four Filament resources for managing tax configuration.

## Tax Zone Resource

Manages geographic tax zones that determine which tax rates apply based on customer location.

### Navigation

- **Icon:** `heroicon-o-globe-alt`
- **Group:** Tax (configurable)
- **Sort:** 1

### List View

| Column | Description |
|--------|-------------|
| Name | Zone display name |
| Code | Unique identifier code |
| Countries | Number of countries in zone |
| States | Number of states in zone |
| Rates | Count of active tax rates |
| Default | Whether this is the fallback zone |
| Active | Zone status |

**Filters:**
- Active zones only
- Default zone only

**Actions:**
- Edit zone
- View zone details

**Bulk Actions:**
- Toggle active status
- Delete zones

### Form Fields

```
┌─────────────────────────────────────────────┐
│ Basic Information                            │
├─────────────────────────────────────────────┤
│ Name*         [__________________________]  │
│ Code*         [__________________________]  │
│ Description   [__________________________]  │
│               [__________________________]  │
├─────────────────────────────────────────────┤
│ Geographic Targeting                         │
├─────────────────────────────────────────────┤
│ Countries     [Select countries...      ▼]  │
│ States        [__________________________]  │
│               (comma-separated)              │
│ Postcodes     [__________________________]  │
│               (comma-separated, supports *)  │
├─────────────────────────────────────────────┤
│ Settings                                     │
├─────────────────────────────────────────────┤
│ Priority      [10___] (higher = checked     │
│                        first)                │
│ ☑ Active                                    │
│ ☐ Default zone                              │
└─────────────────────────────────────────────┘
```

**Field Details:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| name | TextInput | Yes | Display name for the zone |
| code | TextInput | Yes | Unique identifier (uppercase) |
| description | Textarea | No | Optional description |
| countries | Select | No | Multi-select of ISO country codes |
| states | TagsInput | No | State/province codes |
| postcodes | TagsInput | No | Postcode patterns (`43*`, `40000-49999`) |
| priority | TextInput | No | Resolution priority (default: 10) |
| is_active | Toggle | No | Enable/disable zone |
| is_default | Toggle | No | Use as fallback zone |

### Relation Managers

#### Rates Relation Manager

When viewing a zone, the Rates tab shows all tax rates for that zone:

| Column | Description |
|--------|-------------|
| Name | Rate display name |
| Tax Class | Product category |
| Rate | Percentage (formatted) |
| Compound | Whether rate compounds |
| Shipping | Applies to shipping |
| Active | Rate status |

**Inline Actions:**
- Create new rate for this zone
- Edit rate
- Delete rate

---

## Tax Class Resource

Manages product categorization for different tax treatments.

### Navigation

- **Icon:** `heroicon-o-tag`
- **Group:** Tax
- **Sort:** 2

### List View

| Column | Description |
|--------|-------------|
| Name | Class display name |
| Slug | Unique identifier |
| Description | Optional description |
| Position | Display order |
| Default | Whether this is the default class |
| Active | Class status |

**Filters:**
- Active only
- Default only

### Form Fields

```
┌─────────────────────────────────────────────┐
│ Tax Class Details                            │
├─────────────────────────────────────────────┤
│ Name*         [__________________________]  │
│ Slug*         [__________________________]  │
│               (auto-generated from name)     │
│ Description   [__________________________]  │
│               [__________________________]  │
├─────────────────────────────────────────────┤
│ Settings                                     │
├─────────────────────────────────────────────┤
│ Position      [0____]                        │
│ ☑ Active                                    │
│ ☐ Default class                             │
└─────────────────────────────────────────────┘
```

**Field Details:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| name | TextInput | Yes | Display name |
| slug | TextInput | Yes | Unique identifier for code reference |
| description | Textarea | No | Explain when to use this class |
| position | TextInput | No | Sort order (lower = first) |
| is_active | Toggle | No | Enable/disable class |
| is_default | Toggle | No | Use as fallback class |

### Common Tax Classes

| Slug | Example Use |
|------|-------------|
| `standard` | Normal taxable goods |
| `reduced` | Essential items with lower rate |
| `zero-rated` | Tax-free but reportable |
| `exempt` | Not subject to tax |
| `digital` | Digital goods/services |

---

## Tax Rate Resource

Manages tax percentages applied to products and shipping.

### Navigation

- **Icon:** `heroicon-o-calculator`
- **Group:** Tax
- **Sort:** 3

### List View

| Column | Description |
|--------|-------------|
| Name | Rate display name |
| Zone | Associated tax zone |
| Tax Class | Product category |
| Rate | Percentage display |
| Compound | Whether rate compounds on previous taxes |
| Shipping | Whether rate applies to shipping |
| Active | Rate status |

**Filters:**
- Active only
- By zone (dropdown)
- By tax class (dropdown)
- Compound rates only
- Shipping rates only

### Form Fields

```
┌─────────────────────────────────────────────┐
│ Tax Rate Details                             │
├─────────────────────────────────────────────┤
│ Name*         [__________________________]  │
│ Zone*         [Select zone...           ▼]  │
│ Tax Class*    [Select class...          ▼]  │
├─────────────────────────────────────────────┤
│ Rate Configuration                           │
├─────────────────────────────────────────────┤
│ Rate (%)*     [6.00___]                     │
│               (stored as basis points)       │
│ Priority      [10___]                        │
├─────────────────────────────────────────────┤
│ Options                                      │
├─────────────────────────────────────────────┤
│ ☐ Compound                                  │
│   (Calculate on amount + previous taxes)     │
│ ☑ Applies to shipping                       │
│ ☑ Active                                    │
└─────────────────────────────────────────────┘
```

**Field Details:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| name | TextInput | Yes | Display name (e.g., "SST 6%") |
| zone_id | Select | Yes | Tax zone this rate belongs to |
| tax_class | Select | Yes | Product class for rate matching |
| rate | TextInput | Yes | Rate percentage (converted to basis points) |
| priority | TextInput | No | Order for compound calculation |
| is_compound | Toggle | No | Calculate on subtotal + previous taxes |
| is_shipping | Toggle | No | Apply to shipping charges |
| is_active | Toggle | No | Enable/disable rate |

### Rate Entry

The rate field accepts a percentage value and converts it to basis points for storage:

- Enter: `6` or `6.00`
- Stored: `600` (basis points)
- Displayed: `6.00%`

---

## Tax Exemption Resource

Manages customer tax exemptions with approval workflow.

### Navigation

- **Icon:** `heroicon-o-shield-check`
- **Group:** Tax
- **Badge:** Count of pending exemptions

### List View

| Column | Description |
|--------|-------------|
| Customer | Exemptable entity name/ID |
| Zone | Tax zone (or "All Zones") |
| Status | Pending, Approved, Rejected |
| Reason | Exemption justification |
| Starts At | When exemption becomes active |
| Expires At | When exemption ends |
| Created | Creation timestamp |

**Filters:**
- By status (Pending, Approved, Rejected)
- By zone
- Expiring soon (within 30 days)
- Active only (approved + valid dates)

**Actions:**
- Approve (on pending)
- Reject (on pending)
- View certificate
- Download certificate

**Bulk Actions:**
- Approve selected
- Reject selected
- Delete selected

### Form Fields

```
┌─────────────────────────────────────────────┐
│ Exemption Request                            │
├─────────────────────────────────────────────┤
│ Customer Type [Select model...          ▼]  │
│ Customer ID   [__________________________]  │
├─────────────────────────────────────────────┤
│ Scope                                        │
├─────────────────────────────────────────────┤
│ Tax Zone      [All Zones                ▼]  │
│               (leave empty for all zones)    │
├─────────────────────────────────────────────┤
│ Validity Period                              │
├─────────────────────────────────────────────┤
│ Starts At     [📅 ___________________]      │
│ Expires At    [📅 ___________________]      │
├─────────────────────────────────────────────┤
│ Details                                      │
├─────────────────────────────────────────────┤
│ Reason*       [__________________________]  │
│               [__________________________]  │
│ Certificate # [__________________________]  │
│ Certificate   [📎 Upload file]              │
├─────────────────────────────────────────────┤
│ Status                                       │
├─────────────────────────────────────────────┤
│ Status        [Pending                  ▼]  │
│ Notes         [__________________________]  │
│               (internal notes)               │
└─────────────────────────────────────────────┘
```

**Field Details:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| exemptable_type | Select | Yes | Model class (e.g., Customer) |
| exemptable_id | TextInput | Yes | Entity ID |
| tax_zone_id | Select | No | Limit to specific zone (null = all) |
| starts_at | DatePicker | No | Start of validity period |
| expires_at | DatePicker | No | End of validity period |
| reason | Textarea | Yes | Justification for exemption |
| certificate_number | TextInput | No | External certificate reference |
| certificate_path | FileUpload | No | Supporting documentation |
| status | Select | Yes | pending, approved, rejected |
| notes | Textarea | No | Internal admin notes |

### Approval Workflow

1. **Pending** — Newly created or re-submitted
2. **Approved** — Admin approved, exemption active if dates valid
3. **Rejected** — Admin rejected, exemption not applied

Actions appear based on current status:
- Pending: Approve, Reject
- Approved: Reject (revoke)
- Rejected: Approve (reinstate)

### Certificate Download

The `DownloadTaxExemptionCertificateAction` provides secure certificate download:

```php
// Path is validated to prevent directory traversal
// Downloads file from storage
DownloadTaxExemptionCertificateAction::run($exemption);
```

Security features:
- Path validation against traversal attacks
- Existence check before download
- Proper content-disposition headers
