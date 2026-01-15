---
title: Resources
---

# Resources

## DocResource

The primary resource for managing documents (invoices, receipts, quotations, etc.).

### List View

| Column | Description |
|--------|-------------|
| Number | Document number (searchable, copyable) |
| Type | Invoice, Receipt, Quotation badge |
| Status | Color-coded status badge |
| Customer | Customer name (searchable) |
| Total | Formatted amount with currency |
| Issue Date | Document issue date |
| Due Date | Due date (highlighted if overdue) |

#### Filters

- **Type** - Filter by document type
- **Status** - Filter by document status
- **Overdue** - Show only overdue documents
- **Paid** - Show only paid documents
- **Has PDF** - Show documents with generated PDFs
- **This Month** - Show documents from current month

### Create/Edit Form

The form is organized into sections:

**Document Information**
- Document number (auto-generated if empty)
- Document type selection
- Template selection (scoped by owner)
- Status, issue date, due date
- Currency and tax rate

**Customer Information**
- Name, email, phone
- Address, city, state, postcode, country

**Line Items**
- Repeatable items with name, quantity, price
- Optional description per item
- Collapsible, cloneable, reorderable

**Amounts**
- Subtotal, tax amount, discount, total
- Auto-calculated when items change

**Notes & Terms**
- Additional notes
- Terms and conditions

**Metadata**
- Custom key-value data (JSON)

### View Page

Displays complete document details with:

- Document information infolist
- Customer address formatted
- Line items with calculated totals
- Amount summary
- Template information
- Timestamps

#### Header Actions

| Action | Description |
|--------|-------------|
| Edit | Open edit form |
| Generate PDF | Create/regenerate PDF |
| Download PDF | Download the PDF file |
| Mark as Sent | Update status (for draft/pending) |
| Mark as Paid | Record payment |
| Record Payment | Add partial payment |
| Send Email | Send document via email |
| Cancel | Cancel the document |
| Delete | Delete the document |

### Relation Managers

**StatusHistoriesRelationManager**
- Status badge with color
- Notes/reason for change
- Who made the change
- Timestamp

**PaymentsRelationManager**
- Payment amount and method
- Transaction ID / reference
- Payment date
- Notes

**EmailsRelationManager**
- Recipient email
- Subject and status
- Open/click counts
- Sent timestamp

**VersionsRelationManager**
- Version number
- Change summary
- Snapshot data
- Restore action

**ApprovalsRelationManager**
- Assignee and requester
- Status (pending/approved/rejected)
- Comments
- Approve/reject actions

---

## DocTemplateResource

Manage document templates with PDF configuration.

### List View

| Column | Description |
|--------|-------------|
| Name | Template name (searchable) |
| Slug | Unique identifier (copyable) |
| Type | Document type badge |
| Default | Boolean indicator |
| Documents | Count of documents using template |
| Updated | Last update time |

#### Filters

- **Document Type** - Filter by type
- **Default Only** - Show only default templates

### Create/Edit Form

**Template Information**
- Name (auto-generates slug)
- Slug (unique identifier, owner-scoped)
- Description
- Document type
- View name (Blade view reference)
- Default template toggle

**PDF Settings**
- Paper format (A4, Letter, Legal, A3, A5)
- Orientation (Portrait/Landscape)
- Margins (top, right, bottom, left in mm)
- Print background toggle

**Custom Settings**
- Key-value pairs for custom configuration

### View Page

Displays:
- Template information
- PDF settings summary
- Custom settings (if any)
- Usage statistics (document count)
- Timestamps

#### Actions

| Action | Description |
|--------|-------------|
| Edit | Open edit form |
| Set as Default | Make default for document type |
| Delete | Delete the template |

---

## DocSequenceResource

Configure automatic document number sequences.

### List View

| Column | Description |
|--------|-------------|
| Name | Sequence name |
| Type | Document type |
| Prefix | Number prefix (INV, QUO, etc.) |
| Format | Full format string |
| Reset | Reset frequency |
| Active | Boolean indicator |

### Create/Edit Form

**Sequence Settings**
- Name
- Document type
- Prefix (e.g., INV, QUO, CN)
- Reset frequency (Never, Daily, Monthly, Yearly)

**Number Format**
- Format string with tokens
- Start number
- Increment
- Padding (zeros)
- Active toggle

**Format Tokens:**
- `{PREFIX}` - The sequence prefix
- `{NUMBER}` - The sequential number
- `{YYYY}` - 4-digit year
- `{YY}` - 2-digit year
- `{MM}` - 2-digit month
- `{DD}` - 2-digit day
- `{YYMM}` - Year+month
- `{YYMMDD}` - Full date

**Example:** `{PREFIX}-{YYMM}-{NUMBER}` → `INV-2601-000001`

---

## DocEmailTemplateResource

Configure email templates for document communications.

### List View

| Column | Description |
|--------|-------------|
| Name | Template name |
| Type | Document type |
| Trigger | When email is sent |
| Subject | Email subject preview |
| Active | Boolean indicator |

### Create/Edit Form

**Template Settings**
- Name and slug
- Document type
- Trigger (send, reminder, overdue, paid, created)
- Active toggle

**Email Content**
- Subject line with variables
- Rich text body with variables

**Available Variables:**
- `{{doc_number}}` - Document number
- `{{doc_type}}` - Document type
- `{{customer_name}}` - Customer name
- `{{total}}` - Total amount
- `{{currency}}` - Currency code
- `{{due_date}}` - Due date
- `{{issue_date}}` - Issue date
- `{{company_name}}` - Company name

---

## Extending Resources

### Custom Resource Class

Override any resource in your application:

```php
<?php

namespace App\Filament\Resources;

use AIArmada\FilamentDocs\Resources\DocResource as BaseDocResource;

class DocResource extends BaseDocResource
{
    public static function getNavigationGroup(): ?string
    {
        return 'Billing';
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-currency-dollar';
    }
    
    public static function getNavigationLabel(): string
    {
        return 'Invoices';
    }
}
```

### Custom Table Configuration

Add custom columns or modify existing ones:

```php
<?php

namespace App\Filament\Resources\DocResource\Tables;

use AIArmada\FilamentDocs\Resources\DocResource\Tables\DocsTable as BaseDocsTable;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DocsTable extends BaseDocsTable
{
    public static function configure(Table $table): Table
    {
        return parent::configure($table)
            ->columns([
                // Merge with existing columns
                TextColumn::make('metadata.project_id')
                    ->label('Project'),
            ]);
    }
}
```

### Custom Form Fields

Extend forms with additional fields:

```php
<?php

namespace App\Filament\Resources\DocResource\Schemas;

use AIArmada\FilamentDocs\Resources\DocResource\Schemas\DocForm as BaseDocForm;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class DocForm extends BaseDocForm
{
    public static function configure(Schema $schema): Schema
    {
        return parent::configure($schema)
            ->schema([
                // Add your custom fields
                Select::make('metadata.project_id')
                    ->label('Project')
                    ->options(Project::pluck('name', 'id')),
            ]);
    }
}
```

### Custom Actions

Add custom actions to view/edit pages:

```php
<?php

namespace App\Filament\Resources\DocResource\Pages;

use AIArmada\FilamentDocs\Resources\DocResource\Pages\ViewDoc as BaseViewDoc;
use Filament\Actions\Action;

class ViewDoc extends BaseViewDoc
{
    protected function getHeaderActions(): array
    {
        return array_merge(parent::getHeaderActions(), [
            Action::make('export_xml')
                ->label('Export UBL')
                ->icon('heroicon-o-code-bracket')
                ->action(fn () => $this->exportToUbl()),
        ]);
    }
    
    protected function exportToUbl(): void
    {
        // Custom UBL export logic
    }
}
```
