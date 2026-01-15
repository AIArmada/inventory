---
title: Resources
---

# Resources

The plugin provides two main resources for managing customers and segments.

## Customer Resource

### Overview

The Customer Resource provides complete CRUD operations for customer management with advanced filtering and bulk actions.

### Table View

The customer list includes:

**Columns:**
- Customer name with email (searchable)
- Status badge (colored by status)
- Marketing opt-in status
- Tax exempt status
- Segment badges
- Join date

**Filters:**
- Status (Active, Inactive, Suspended, Pending Verification)
- Accepts Marketing (Yes/No/All)
- Tax Exempt (Yes/No/All)
- Segments (multi-select)

**Actions:**
- View customer details
- Edit customer
- Delete customer

**Bulk Actions:**
- Delete selected
- Opt-in to marketing
- Opt-out of marketing

### Form View

The customer form is organized into sections:

**Customer Information (2 columns):**
```php
- First Name * (required)
- Last Name * (required)
- Email * (required, unique per owner)
- Phone (tel input)
- Company
```

**Preferences (2 columns):**
```php
- Accepts Marketing (toggle)
- Tax Exempt (toggle)
- Tax Exempt Reason (conditional textarea)
```

**Status Sidebar:**
```php
- Status dropdown (Active/Inactive/Suspended/Pending)
```

**Segments Sidebar:**
```php
- Segment selection (multi-select, owner-scoped)
- Helper text: "Manual segment assignment"
```

### View/Infolist

The customer view page displays comprehensive information:

**Customer Overview:**
- Full name
- Email (copyable)
- Phone (copyable)
- Status badge

**Activity:**
- Last Login date
- Customer Since date

**Segments:**
- Assigned segment badges

### Relation Managers

#### Addresses

Manage customer addresses with:
- Label, type, recipient, company, phone
- Address lines, city, state, postcode, country
- Default billing/shipping toggles
- Actions: Set as billing, Set as shipping, Delete

#### Notes

Customer notes with:
- Content (rich text)
- Internal/Public visibility
- Pinned status
- Created by user
- Actions: Pin/Unpin, Edit, Delete

### Header Actions

On view page:
- **Edit** - Navigate to edit form

### Global Search

Customers are globally searchable by:
- First name
- Last name
- Email
- Phone
- Company

Search results show customer name and email.

### Owner Scoping

All queries are automatically scoped:
```php
public static function getEloquentQuery(): Builder
{
    $query = parent::getEloquentQuery();
    return CustomersOwnerScope::applyToOwnedQuery($query);
}
```

This ensures users only see customers within their owner context.

## Segment Resource

### Overview

The Segment Resource manages customer segmentation with support for automatic rule-based segments and manual assignment.

### Table View

**Columns:**
- Segment name with description
- Type badge (Loyalty, Behavior, Demographic, Custom)
- Customer count (relation count)
- Automatic indicator (boolean icon)
- Active indicator (boolean icon)
- Priority (sortable, hidden by default)
- Created date (hidden by default)

**Filters:**
- Type (Loyalty/Behavior/Demographic/Custom)
- Assignment Type (Automatic/Manual)
- Active status (Yes/No/All)

**Actions:**
- View segment
- Edit segment
- Rebuild (automatic segments only)
- Delete segment

### Form View

**Segment Information (2 columns):**
```php
- Segment Name * (auto-generates slug)
- Slug * (unique)
- Type * (Loyalty/Behavior/Demographic/Custom)
- Description (textarea, full width)
```

**Assignment Rules:**
```php
- Automatic Assignment (toggle, live)
- Conditions (repeater, conditional on automatic)
  - Field (select from predefined list)
  - Value (numeric or toggle based on field)
```

**Available Condition Fields:**
- Accepts Marketing (boolean)
- Is Tax Exempt (boolean)
- Days Since Registration
- Days Since Last Login
- Days Without Login

**Settings Sidebar:**
```php
- Active (toggle, default true)
- Priority (numeric, for pricing)
```

**Manual Assignment Sidebar (manual segments only):**
```php
- Customers (multi-select, owner-scoped)
```

### Rebuild Action

For automatic segments:
```php
Action::make('rebuild')
    ->label('Rebuild')
    ->icon('heroicon-o-arrow-path')
    ->color('warning')
    ->requiresConfirmation()
    ->action(fn ($record) => {
        $count = $record->rebuildCustomerList();
        
        Notification::make()
            ->success()
            ->title('Segment Rebuilt')
            ->body("{$count} customer(s) in segment")
            ->send();
    });
```

Rebuilds segment membership based on current conditions.

### Owner Scoping

Segment queries are owner-scoped:
```php
public static function getEloquentQuery(): Builder
{
    $query = parent::getEloquentQuery();
    return CustomersOwnerScope::applyToOwnedQuery($query);
}
```

Customer selections in forms are also scoped:
```php
Forms\Components\Select::make('customers')
    ->relationship(
        name: 'customers',
        titleAttribute: 'email',
        modifyQueryUsing: fn (Builder $query) =>
            CustomersOwnerScope::applyToOwnedQuery($query)
    );
```

## Customizing Resources

### Extend Resources

Create custom resource classes:

```php
namespace App\Filament\Resources;

use AIArmada\FilamentCustomers\Resources\CustomerResource as BaseCustomerResource;

class CustomerResource extends BaseCustomerResource
{
    // Override navigation
    protected static ?string $navigationGroup = 'Sales';
    protected static ?int $navigationSort = 5;
    
    // Add custom columns
    public static function table(Table $table): Table
    {
        return parent::table($table)
            ->columns([
                // Add your custom columns
            ]);
    }
    
    // Add custom actions
    protected function getHeaderActions(): array
    {
        return array_merge(parent::getHeaderActions(), [
            // Your custom actions
        ]);
    }
}
```

### Override Forms

Modify form schemas:

```php
public static function form(Schema $schema): Schema
{
    return $schema
        ->schema([
            // Your custom form schema
            // Or call parent::form($schema) and modify
        ]);
}
```

### Custom Filters

Add additional filters:

```php
public static function table(Table $table): Table
{
    return parent::table($table)
        ->filters([
            Tables\Filters\Filter::make('custom_filter')
                ->query(fn ($query) => $query->where(...)),
        ]);
}
```

### Custom Actions

Add resource-level actions:

```php
public static function table(Table $table): Table
{
    return parent::table($table)
        ->actions([
            Action::make('custom_action')
                ->icon('heroicon-o-star')
                ->action(fn ($record) => {
                    // Your action logic
                }),
        ]);
}
```

## Next Steps

- [Widgets](05-widgets.md) - Dashboard widgets
