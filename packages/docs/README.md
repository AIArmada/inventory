# Docs Package for Laravel

Modern Laravel package for generating professional documents (invoices, receipts, and more) with PDF support using Spatie Laravel PDF and Tailwind CSS.

## Features

- 📄 Generate professional PDF documents with Blade templates
- 🎨 Tailwind CSS support for beautiful, customizable designs
- 📊 Support for multiple document types: invoices, receipts (expandable)
- 🔢 Automatic document numbering with configurable formats
- 📝 Multiple templates support for each document type
- 📱 Status tracking (Draft, Pending, Sent, Paid, etc.)
- 💾 Store PDFs on any Laravel filesystem disk
- 📧 Email document capabilities
- 🔄 Status history tracking
- 🏢 Configurable company and customer data

## Supported Document Types

### Currently Supported
- **Invoices** - Full invoice generation with line items, taxes, discounts
- **Receipts** - Payment receipts (coming soon)

### Expandable Architecture
The package is designed to easily support additional document types such as quotations, purchase orders, delivery notes, and more.

## Installation

Install the package via Composer:

```bash
composer require aiarmada/docs
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=docs-config
```

Run the migrations:

```bash
php artisan migrate
```

## Configuration

The package configuration is located in `config/docs.php`. After publishing, you can customize all aspects of document generation.

### Document Types

Configure multiple document types (invoices, receipts, tickets, etc.) with type-specific settings:

```php
'types' => [
    'invoice' => [
        'default_template' => 'doc-default',
        'number_format' => [
            'prefix' => 'INV',           // Document number prefix
            'year_format' => 'y',        // Year format (Y = 2025, y = 25)
            'separator' => '-',           // Separator between parts
            'suffix_length' => 6,        // Random suffix length
        ],
        'storage' => [
            'disk' => 'local',           // Storage disk (local, s3, etc.)
            'path' => 'docs/invoices',   // Path within disk
        ],
        'defaults' => [
            'currency' => 'MYR',         // Default currency
            'tax_rate' => 0,             // Default tax rate (0 = 0%, 0.06 = 6%)
            'due_days' => 30,            // Days until due
        ],
    ],
    // Add more types as needed (receipt, ticket, quotation, etc.)
],
```

**Example Document Numbers:**
- `INV25-A1B2C3` (Invoice)
- `RCP25-D4E5F6` (Receipt)
- `TKT25-G7H8I9` (Ticket)

### PDF Configuration

Control PDF generation settings globally:

```php
'pdf' => [
    'format' => 'a4',              // Paper size: a4, letter, legal, a3, a5
    'orientation' => 'portrait',   // portrait or landscape
    'margin' => [
        'top' => 10,               // Margin in millimeters
        'right' => 10,
        'bottom' => 10,
        'left' => 10,
    ],
    'full_bleed' => false,         // Set to true for borderless PDFs
    'print_background' => true,    // Enable background colors/gradients
],
```

**Override PDF Settings Per Template:**

You can override these settings in your template configuration:

```php
DocTemplate::create([
    'name' => 'Borderless Template',
    'settings' => [
        'pdf' => [
            'format' => 'a4',
            'orientation' => 'landscape',
            'full_bleed' => true,      // Removes all margins
            'margin' => [
                'top' => 0,
                'right' => 0,
                'bottom' => 0,
                'left' => 0,
            ],
        ],
    ],
]);
```

**Override PDF Settings Per Document:**

You can also override settings when creating individual documents:

```php
$document = $docService->createDoc(DocData::from([
    'doc_type' => 'invoice',
    'pdf_options' => [
        'format' => 'letter',
        'orientation' => 'landscape',
        'margin' => [
            'top' => 5,
            'right' => 5,
            'bottom' => 5,
            'left' => 5,
        ],
    ],
    // ... other data
]));
```

**Setting Precedence:** Config defaults < Template settings < Per-document options

### Company Information

Set default company details that appear on all documents:

```php
'company' => [
    'name' => env('DOCS_COMPANY_NAME', config('app.name')),
    'address' => env('DOCS_COMPANY_ADDRESS'),
    'city' => env('DOCS_COMPANY_CITY'),
    'state' => env('DOCS_COMPANY_STATE'),
    'postcode' => env('DOCS_COMPANY_POSTCODE'),
    'country' => env('DOCS_COMPANY_COUNTRY'),
    'phone' => env('DOCS_COMPANY_PHONE'),
    'email' => env('DOCS_COMPANY_EMAIL'),
    'website' => env('DOCS_COMPANY_WEBSITE'),
    'tax_id' => env('DOCS_COMPANY_TAX_ID'),
],
```

**Environment Variables:**

Add these to your `.env` file:

```env
DOCS_COMPANY_NAME="Your Company Name"
DOCS_COMPANY_ADDRESS="123 Business Street"
DOCS_COMPANY_CITY="Kuala Lumpur"
DOCS_COMPANY_STATE="Federal Territory"
DOCS_COMPANY_POSTCODE="50000"
DOCS_COMPANY_COUNTRY="Malaysia"
DOCS_COMPANY_PHONE="+60 3-1234-5678"
DOCS_COMPANY_EMAIL="billing@yourcompany.com"
DOCS_COMPANY_WEBSITE="https://yourcompany.com"
DOCS_COMPANY_TAX_ID="123456789"

DOCS_STORAGE_DISK=local
DOCS_STORAGE_PATH=docs/invoices
DOCS_CURRENCY=MYR
DOCS_TAX_RATE=0.06
DOCS_DUE_DAYS=30
```

### Database Configuration

Control JSON column types for the documents:

```php
'database' => [
    'json_column_type' => env('DOCS_JSON_COLUMN_TYPE', 'json'), // or 'jsonb' for PostgreSQL
],
```

## Usage

### Basic Document Creation

```php
use AIArmada\Docs\Services\DocService;
use AIArmada\Docs\DataObjects\DocData;
use AIArmada\Docs\Enums\DocStatus;

$docService = app(DocService::class);

$document = $docService->createDoc(DocData::from([
    'doc_type' => 'invoice',
    'items' => [
        [
            'name' => 'Web Development Service',
            'description' => 'Custom website development',
            'quantity' => 1,
            'price' => 2500.00,
        ],
        [
            'name' => 'Hosting (Annual)',
            'quantity' => 1,
            'price' => 500.00,
        ],
    ],
    'customer_data' => [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'address' => '123 Main St',
        'city' => 'Kuala Lumpur',
        'state' => 'Federal Territory',
        'postcode' => '50000',
        'country' => 'Malaysia',
    ],
    'notes' => 'Thank you for your business!',
    'terms' => 'Payment due within 30 days.',
    'generate_pdf' => true,
]));
```

### Advanced Document Creation

Create documents with custom numbering, dates, taxes, and discounts:

```php
$document = $docService->createDoc(DocData::from([
    'doc_number' => 'INV-2025-001',        // Optional: Auto-generated if not provided
    'doc_type' => 'invoice',
    'template_slug' => 'modern',           // Optional: Use specific template
    'status' => DocStatus::PENDING,        // Optional: Defaults to DRAFT
    
    // Dates
    'issue_date' => now(),
    'due_date' => now()->addDays(30),
    
    // Financial details
    'currency' => 'USD',
    'tax_rate' => 0.06,                    // 6% tax
    'discount_amount' => 50.00,            // Fixed discount
    
    // Items
    'items' => [
        [
            'name' => 'Consulting Service',
            'description' => 'Business strategy consultation (2 hours)',
            'quantity' => 2,
            'price' => 150.00,
        ],
        [
            'name' => 'Report Writing',
            'quantity' => 1,
            'price' => 200.00,
        ],
    ],
    
    // Customer information
    'customer_data' => [
        'name' => 'ACME Corporation',
        'email' => 'billing@acme.com',
        'address' => '456 Corporate Blvd',
        'city' => 'Singapore',
        'postcode' => '018956',
        'country' => 'Singapore',
        'phone' => '+65 6123 4567',
    ],
    
    // Optional: Override company data for this document
    'company_data' => [
        'name' => 'My Company Ltd',
        'address' => '789 Business Ave',
        'city' => 'Kuala Lumpur',
        // ... other fields
    ],
    
    // Optional: PDF generation settings
    'generate_pdf' => true,
    'pdf_options' => [
        'format' => 'a4',
        'orientation' => 'portrait',
        'margin' => [
            'top' => 20,
            'right' => 20,
            'bottom' => 20,
            'left' => 20,
        ],
    ],
    
    // Optional: Additional metadata
    'metadata' => [
        'project_id' => 'PRJ-123',
        'department' => 'Sales',
        'custom_field' => 'Custom value',
    ],
]));
```

### Linking Documents to Models

Link documents to orders, tickets, or any other model using polymorphic relationships:

```php
use App\Models\Order;

$order = Order::find($orderId);

$document = $docService->createDoc(DocData::from([
    'doc_type' => 'invoice',
    'docable_type' => Order::class,
    'docable_id' => $order->id,
    'items' => [
        [
            'name' => 'Product from Order',
            'quantity' => $order->quantity,
            'price' => $order->price,
        ],
    ],
    'customer_data' => [
        'name' => $order->customer_name,
        'email' => $order->customer_email,
        // ... populate from order
    ],
]));

// Later, access the linked model
$order = $document->docable;  // Returns the Order model
```

### Automatic Calculations

The package automatically calculates totals:

```php
$document = $docService->createDoc(DocData::from([
    'doc_type' => 'invoice',
    'items' => [
        ['name' => 'Item 1', 'quantity' => 2, 'price' => 100],  // $200
        ['name' => 'Item 2', 'quantity' => 1, 'price' => 150],  // $150
    ],
    'tax_rate' => 0.06,           // 6% tax
    'discount_amount' => 25,      // $25 discount
]));

// Automatically calculated:
// Subtotal: $350
// Tax: $21 (6% of $350)
// Discount: -$25
// Total: $346
```

### PDF Generation

Generate PDFs from documents with full control over saving and output:

```php
$docService = app(DocService::class);

// Generate and save to disk
$pdfPath = $docService->generatePdf($document, save: true);
// Returns: "docs/invoices/inv25-abc123.pdf"

// Generate without saving (returns PDF content as string)
$pdfContent = $docService->generatePdf($document, save: false);
// Use for streaming, attaching to emails, etc.

// Example: Stream to browser
return response($pdfContent)
    ->header('Content-Type', 'application/pdf')
    ->header('Content-Disposition', 'inline; filename="'.$document->doc_number.'.pdf"');
```

### PDF Storage

Configure where PDFs are stored in `config/docs.php`:

```php
'types' => [
    'invoice' => [
        'storage' => [
            'disk' => 's3',                    // Use S3, local, or any Laravel disk
            'path' => 'documents/invoices',    // Path within the disk
        ],
    ],
],
```

Access stored PDFs:

```php
use Illuminate\Support\Facades\Storage;

$disk = config('docs.types.invoice.storage.disk');
$path = $document->pdf_path;

// Get URL (if disk supports it)
$url = Storage::disk($disk)->url($path);

// Download
return Storage::disk($disk)->download($path);

// Check if exists
if (Storage::disk($disk)->exists($path)) {
    // PDF exists
}
```

### Download Document PDF

```php
// Generates PDF if not already generated, or returns existing path
$pdfPath = $docService->downloadPdf($document);
```

### Document Status Management

Track document lifecycle with built-in status management:

```php
use AIArmada\Docs\Enums\DocStatus;

// Update status with notes
$docService->updateDocStatus(
    $document, 
    DocStatus::PAID, 
    'Payment received via bank transfer on '.now()->format('Y-m-d')
);

// Convenience methods on the model
$document->markAsPaid();    // Sets status to PAID
$document->markAsSent();    // Sets status to SENT

// Check current status
if ($document->status === DocStatus::PAID) {
    // Document is paid
}

// Access status label
echo $document->status->label();  // "Paid", "Pending", etc.
```

### Status History

View the complete history of status changes:

```php
// Get all status changes
$history = $document->statusHistories()
    ->orderBy('created_at', 'desc')
    ->get();

foreach ($history as $entry) {
    echo $entry->status->label();      // Status
    echo $entry->notes;                 // Change notes
    echo $entry->created_at->format('Y-m-d H:i'); // When
}
```

### Available Statuses

```php
DocStatus::DRAFT           // Initial state
DocStatus::PENDING         // Awaiting approval or action
DocStatus::SENT            // Delivered to customer
DocStatus::PAID            // Payment received
DocStatus::PARTIALLY_PAID  // Partial payment received
DocStatus::OVERDUE         // Past due date
DocStatus::CANCELLED       // Cancelled
DocStatus::REFUNDED        // Payment refunded
```

## Creating Custom Templates

Templates are Blade views that define how your documents look. The package uses a structured path convention for template views.

### Template View Paths

Templates are automatically resolved using the following path convention:

```
docs::templates.<template-slug>
```

**Examples:**
- `docs::templates.doc-default` → Default template
- `docs::templates.modern` → Custom modern template
- `docs::templates.minimal` → Custom minimal template

### Template Resolution Priority

When creating a document, the package resolves templates in this order:

1. **By UUID** (if `doc_template_id` provided)
   ```php
   $template = DocTemplate::find($data->docTemplateId);
   ```

2. **By Slug** (if `template_slug` provided)
   ```php
   $template = DocTemplate::where('slug', $data->templateSlug)->first();
   ```

3. **Database Default** (if no template found)
   ```php
   $template = DocTemplate::where('is_default', true)
       ->where('doc_type', $docType)
       ->first();
   ```

4. **Config Fallback** (during PDF generation if template still null)
   ```php
   $viewName = config("docs.types.{$docType}.default_template", "{$docType}-default");
   ```

**Best Practice:** Explicitly specify `template_slug` when creating documents to ensure deterministic template selection:

```php
$document = $docService->createDoc(DocData::from([
    'doc_type' => 'invoice',
    'template_slug' => 'nusavue-invoice',  // ✅ Explicit and predictable
    // ... other data
]));
```

**Why explicit is better:**
- ✅ **Predictable** - Always uses the template you specify
- ✅ **Independent** - Not affected by database default changes
- ✅ **Testable** - Tests don't depend on database state
- ❌ Without it - Depends on database having correct default template seeded

### View Resolution

The `DocService` automatically normalizes view names. You can reference templates in multiple ways:

```php
// All of these resolve to: docs::templates.modern
'view_name' => 'modern'
'view_name' => 'templates.modern'
'view_name' => 'docs.templates.modern'
'view_name' => 'docs::templates.modern'
```

The `normalizeViewName()` method handles various input formats and ensures they resolve to the canonical `docs::templates.<slug>` format, which Laravel uses to find views in:
- Package views: `packages/docs/resources/views/templates/`
- Published views: `resources/views/vendor/docs/templates/` (takes precedence)
- Application views: `resources/views/docs/templates/`

### Creating a New Template

**1. Create the Blade View**

Create your template file in the package or publish views to your application:

```bash
# Publish views to customize
php artisan vendor:publish --tag=docs-views
```

This publishes templates to: `resources/views/vendor/docs/templates/`

Create a new template file:

```blade
<!-- resources/views/vendor/docs/templates/modern.blade.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $doc->doc_type === 'invoice' ? 'Invoice' : 'Document' }} {{ $doc->doc_number }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100">
    <div class="mx-auto max-w-4xl bg-white p-8 shadow-2xl">
        <!-- Header -->
        <div class="mb-8 border-b-4 border-indigo-600 pb-4">
            <h1 class="text-5xl font-black text-indigo-600">
                {{ strtoupper($doc->doc_type) }}
            </h1>
            <p class="mt-2 text-lg text-gray-700">{{ $doc->doc_number }}</p>
        </div>

        <!-- Company & Customer Info -->
        <div class="mb-8 grid grid-cols-2 gap-8">
            @if($doc->company_data)
            <div>
                <h2 class="mb-3 text-sm font-bold uppercase tracking-wide text-gray-500">From</h2>
                <p class="text-lg font-bold text-gray-900">{{ $doc->company_data['name'] ?? '' }}</p>
                <div class="mt-2 text-sm text-gray-600">
                    @if(!empty($doc->company_data['address']))
                        <p>{{ $doc->company_data['address'] }}</p>
                    @endif
                    @if(!empty($doc->company_data['city']))
                        <p>{{ $doc->company_data['city'] }}{{ !empty($doc->company_data['state']) ? ', '.$doc->company_data['state'] : '' }} {{ $doc->company_data['postcode'] ?? '' }}</p>
                    @endif
                    @if(!empty($doc->company_data['email']))
                        <p>{{ $doc->company_data['email'] }}</p>
                    @endif
                </div>
            </div>
            @endif

            @if($doc->customer_data)
            <div>
                <h2 class="mb-3 text-sm font-bold uppercase tracking-wide text-gray-500">Bill To</h2>
                <p class="text-lg font-bold text-gray-900">{{ $doc->customer_data['name'] ?? '' }}</p>
                <div class="mt-2 text-sm text-gray-600">
                    @if(!empty($doc->customer_data['email']))
                        <p>{{ $doc->customer_data['email'] }}</p>
                    @endif
                    @if(!empty($doc->customer_data['address']))
                        <p>{{ $doc->customer_data['address'] }}</p>
                    @endif
                    @if(!empty($doc->customer_data['city']))
                        <p>{{ $doc->customer_data['city'] }}{{ !empty($doc->customer_data['state']) ? ', '.$doc->customer_data['state'] : '' }} {{ $doc->customer_data['postcode'] ?? '' }}</p>
                    @endif
                </div>
            </div>
            @endif
        </div>

        <!-- Items Table -->
        <table class="mb-8 w-full">
            <thead>
                <tr class="bg-indigo-600 text-white">
                    <th class="p-3 text-left text-sm font-bold uppercase">Item</th>
                    <th class="p-3 text-right text-sm font-bold uppercase">Qty</th>
                    <th class="p-3 text-right text-sm font-bold uppercase">Price</th>
                    <th class="p-3 text-right text-sm font-bold uppercase">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($doc->items as $item)
                <tr class="border-b border-gray-200">
                    <td class="p-3">
                        <div class="font-semibold text-gray-900">{{ $item['name'] ?? $item['description'] ?? '' }}</div>
                        @if(!empty($item['description']) && isset($item['name']))
                            <div class="text-sm text-gray-600">{{ $item['description'] }}</div>
                        @endif
                    </td>
                    <td class="p-3 text-right">{{ $item['quantity'] ?? 1 }}</td>
                    <td class="p-3 text-right">{{ $doc->currency }} {{ number_format($item['price'] ?? 0, 2) }}</td>
                    <td class="p-3 text-right font-semibold">{{ $doc->currency }} {{ number_format(($item['quantity'] ?? 1) * ($item['price'] ?? 0), 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Totals -->
        <div class="flex justify-end">
            <div class="w-80 rounded-lg bg-gray-50 p-6">
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Subtotal:</span>
                        <span class="font-semibold">{{ $doc->currency }} {{ number_format($doc->subtotal, 2) }}</span>
                    </div>
                    @if($doc->tax_amount > 0)
                    <div class="flex justify-between">
                        <span class="text-gray-600">Tax:</span>
                        <span class="font-semibold">{{ $doc->currency }} {{ number_format($doc->tax_amount, 2) }}</span>
                    </div>
                    @endif
                    @if($doc->discount_amount > 0)
                    <div class="flex justify-between text-green-600">
                        <span>Discount:</span>
                        <span class="font-semibold">-{{ $doc->currency }} {{ number_format($doc->discount_amount, 2) }}</span>
                    </div>
                    @endif
                    <div class="flex justify-between border-t-2 border-indigo-600 pt-3 text-xl font-bold text-indigo-600">
                        <span>Total:</span>
                        <span>{{ $doc->currency }} {{ number_format($doc->total, 2) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        @if($doc->notes || $doc->terms)
        <div class="mt-8 space-y-4 border-t pt-6">
            @if($doc->notes)
            <div>
                <h3 class="mb-2 text-sm font-bold uppercase text-gray-600">Notes</h3>
                <p class="text-sm text-gray-700">{{ $doc->notes }}</p>
            </div>
            @endif
            @if($doc->terms)
            <div>
                <h3 class="mb-2 text-sm font-bold uppercase text-gray-600">Terms & Conditions</h3>
                <p class="text-sm text-gray-700">{{ $doc->terms }}</p>
            </div>
            @endif
        </div>
        @endif
    </div>
</body>
</html>
```

**2. Create a Template Record**

Register your template in the database:

```php
use AIArmada\Docs\Models\DocTemplate;

DocTemplate::create([
    'name' => 'Modern Template',
    'slug' => 'modern',
    'description' => 'A modern design with gradient background',
    'view_name' => 'modern',           // Will resolve to docs::templates.modern
    'doc_type' => 'invoice',
    'is_default' => false,
    'settings' => [
        'show_logo' => true,
        'primary_color' => '#4f46e5',
        'pdf' => [
            'format' => 'a4',
            'orientation' => 'portrait',
            'margin' => [
                'top' => 15,
                'right' => 15,
                'bottom' => 15,
                'left' => 15,
            ],
            'print_background' => true,  // Important for gradient backgrounds
        ],
    ],
]);
```

**3. Use Your Template**

Reference your template when creating documents:

```php
// By template slug
$document = $docService->createDoc(DocData::from([
    'template_slug' => 'modern',
    'doc_type' => 'invoice',
    // ... other data
]));

// By template ID
$document = $docService->createDoc(DocData::from([
    'doc_template_id' => $template->id,
    'doc_type' => 'invoice',
    // ... other data
]));
```

### Available Template Variables

All templates have access to the `$doc` object with these properties:

```php
$doc->doc_number          // Document number (e.g., INV25-ABC123)
$doc->doc_type            // Document type (invoice, receipt, ticket)
$doc->status              // DocStatus enum
$doc->issue_date          // Carbon instance
$doc->due_date            // Carbon instance (nullable)
$doc->subtotal            // Subtotal amount
$doc->tax_amount          // Tax amount
$doc->discount_amount     // Discount amount
$doc->total               // Total amount
$doc->currency            // Currency code (e.g., MYR, USD)
$doc->notes               // Customer notes
$doc->terms               // Terms and conditions
$doc->customer_data       // Array of customer information
$doc->company_data        // Array of company information
$doc->items               // Array of line items
$doc->metadata            // Array of additional data
$doc->template            // DocTemplate model instance
$doc->docable             // Polymorphic relation (Order, Ticket, etc.)
```

**Line Item Structure:**

```php
[
    'name' => 'Product Name',
    'description' => 'Optional description',
    'quantity' => 1,
    'price' => 100.00,
]
```

**Customer/Company Data Structure:**

```php
[
    'name' => 'Customer Name',
    'email' => 'customer@example.com',
    'address' => '123 Main St',
    'city' => 'Kuala Lumpur',
    'state' => 'Federal Territory',
    'postcode' => '50000',
    'country' => 'Malaysia',
    'phone' => '+60 3-1234-5678',
]
```

### Template Best Practices

1. **Use Tailwind CDN for Styling**: Include `<script src="https://cdn.tailwindcss.com"></script>` in the `<head>` for easy styling.

2. **Enable Background Printing**: Set `print_background: true` in PDF settings if using colored backgrounds or gradients.

3. **Handle Optional Data**: Always check if data exists before displaying:
   ```blade
   @if(!empty($doc->customer_data['address']))
       <p>{{ $doc->customer_data['address'] }}</p>
   @endif
   ```

4. **Format Dates**: Use Carbon methods for consistent date formatting:
   ```blade
   {{ $doc->issue_date->format('M d, Y') }}
   ```

5. **Format Currency**: Always include currency code and format numbers:
   ```blade
   {{ $doc->currency }} {{ number_format($doc->total, 2) }}
   ```

6. **Test PDF Output**: PDFs may render differently than HTML. Always test your templates with PDF generation:
   ```php
   $docService->generatePdf($document, save: true);
   ```

7. **Optimize for Print**: Use appropriate margins and avoid content near page edges unless using `full_bleed: true`.

### Default Template

The package includes a default template (`doc-default`) that supports:
- Invoice, receipt, and ticket types
- Company and customer information
- Line items with descriptions
- Tax, discounts, and totals
- Voucher summaries
- Notes and terms
- Status badges
- Responsive design with Tailwind CSS

You can use this as a reference when creating your own templates.

## Document Status

The package includes predefined statuses for documents:

- **Draft** - Initial state
- **Pending** - Awaiting approval or action
- **Sent** - Delivered to customer
- **Paid** - Payment received
- **Partially Paid** - Partial payment received
- **Overdue** - Past due date
- **Cancelled** - Cancelled
- **Refunded** - Payment refunded

## Querying Documents

Use Eloquent to query documents:

```php
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Enums\DocStatus;

// Get all paid invoices
$paidInvoices = Doc::where('doc_type', 'invoice')
    ->where('status', DocStatus::PAID)
    ->get();

// Get overdue invoices
$overdueInvoices = Doc::where('doc_type', 'invoice')
    ->where('status', DocStatus::OVERDUE)
    ->where('due_date', '<', now())
    ->get();

// Get documents for a specific customer
$customerDocs = Doc::whereJsonContains('customer_data->email', 'customer@example.com')
    ->orderBy('issue_date', 'desc')
    ->get();

// Get documents linked to a model
$order = Order::find($orderId);
$orderDocs = Doc::where('docable_type', Order::class)
    ->where('docable_id', $order->id)
    ->get();

// Eager load relationships
$docs = Doc::with(['template', 'statusHistories', 'docable'])
    ->get();

// Get total amount for paid invoices
$totalRevenue = Doc::where('doc_type', 'invoice')
    ->where('status', DocStatus::PAID)
    ->sum('total');
```

## Working with Templates

### Query Templates

```php
use AIArmada\Docs\Models\DocTemplate;

// Get default template for a doc type
$defaultTemplate = DocTemplate::where('doc_type', 'invoice')
    ->where('is_default', true)
    ->first();

// Get template by slug
$template = DocTemplate::where('slug', 'modern')->first();

// Get all templates for a doc type
$invoiceTemplates = DocTemplate::where('doc_type', 'invoice')->get();
```

### Update Template Settings

```php
$template->update([
    'settings' => [
        'primary_color' => '#10b981',
        'show_logo' => true,
        'pdf' => [
            'format' => 'a4',
            'margin' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10],
        ],
    ],
]);
```

### Set Default Template

```php
// Make a template the default for its doc type
$template->update(['is_default' => true]);

// This will automatically set other templates of the same type to non-default
```

## Requirements

- PHP 8.4+
- Laravel 12.0+
- Spatie Laravel PDF 1.5+
- Node.js 18+ and npm
- Puppeteer (for PDF generation)

### Installing Dependencies

After installing the package, you need to install Node.js dependencies for PDF generation:

```bash
# Install npm packages (includes puppeteer)
npm install

# Or if dependencies are already defined in package.json
npm install puppeteer
```

> **Note:** Puppeteer is required for PDF generation. The package uses Spatie Laravel PDF which relies on Puppeteer/Chromium to render PDFs from HTML.

## Advanced Usage

### Custom Document Number Generation

Override the default document numbering:

```php
use AIArmada\Docs\Services\DocService;

class CustomDocService extends DocService
{
    public function generateDocNumber(string $docType = 'invoice'): string
    {
        // Your custom logic
        $prefix = match($docType) {
            'invoice' => 'INV',
            'receipt' => 'RCP',
            'ticket' => 'TKT',
            default => 'DOC',
        };
        
        $year = now()->year;
        $sequence = Doc::where('doc_type', $docType)
            ->whereYear('created_at', $year)
            ->count() + 1;
        
        return sprintf('%s-%d-%05d', $prefix, $year, $sequence);
        // Returns: INV-2025-00001, INV-2025-00002, etc.
    }
}

// Register in a service provider
$this->app->bind(DocService::class, CustomDocService::class);
```

### Metadata and Custom Fields

Store additional data in the metadata field:

```php
$document = $docService->createDoc(DocData::from([
    'doc_type' => 'invoice',
    'items' => [...],
    'metadata' => [
        'project_id' => 'PRJ-123',
        'project_name' => 'Website Redesign',
        'sales_rep' => 'Jane Smith',
        'payment_link' => 'https://payment.example.com/inv-123',
        'custom_fields' => [
            'po_number' => 'PO-2025-456',
            'contract_id' => 'CNT-789',
        ],
    ],
]));

// Access metadata
$projectId = $document->metadata['project_id'];
$poNumber = $document->metadata['custom_fields']['po_number'];
```

### Voucher Summary Support

The default template includes voucher summary support. Include voucher data in metadata:

```php
$document = $docService->createDoc(DocData::from([
    'doc_type' => 'invoice',
    'items' => [...],
    'discount_amount' => 50.00,
    'metadata' => [
        'voucher_summary' => [
            'voucher_codes' => ['SUMMER2025', 'LOYALTY10'],
            'total_discount_cents' => 5000,  // $50.00
            'total_charge_cents' => 0,
            'vouchers' => [
                [
                    'code' => 'SUMMER2025',
                    'name' => 'Summer Sale',
                    'amount_cents' => -3000,  // -$30.00 (discount)
                ],
                [
                    'code' => 'LOYALTY10',
                    'name' => 'Loyalty Discount',
                    'amount_cents' => -2000,  // -$20.00 (discount)
                ],
            ],
        ],
    ],
]));
```

### Conditional PDF Generation

Control when PDFs are generated:

```php
// Generate PDF immediately
$document = $docService->createDoc(DocData::from([
    'doc_type' => 'invoice',
    'items' => [...],
    'generate_pdf' => true,  // PDF generated on creation
]));

// Generate PDF later (e.g., after approval)
$document = $docService->createDoc(DocData::from([
    'doc_type' => 'invoice',
    'items' => [...],
    'generate_pdf' => false,  // No PDF yet
]));

// Later, when ready
if ($document->status === DocStatus::APPROVED) {
    $docService->generatePdf($document, save: true);
}
```

### Multiple Document Types

Create different document types with specific configurations:

```php
// Invoice
$invoice = $docService->createDoc(DocData::from([
    'doc_type' => 'invoice',
    'due_date' => now()->addDays(30),
    // ... invoice data
]));

// Receipt (no due date)
$receipt = $docService->createDoc(DocData::from([
    'doc_type' => 'receipt',
    // ... receipt data
]));

// Ticket
$ticket = $docService->createDoc(DocData::from([
    'doc_type' => 'ticket',
    'docable_type' => Ticket::class,
    'docable_id' => $ticket->id,
    // ... ticket data
]));
```

### Testing

Example test for document creation:

```php
use AIArmada\Docs\Services\DocService;
use AIArmada\Docs\DataObjects\DocData;
use AIArmada\Docs\Models\Doc;

test('creates invoice document', function () {
    $docService = app(DocService::class);
    
    $document = $docService->createDoc(DocData::from([
        'doc_type' => 'invoice',
        'items' => [
            ['name' => 'Service', 'quantity' => 1, 'price' => 100],
        ],
        'customer_data' => [
            'name' => 'Test Customer',
            'email' => 'test@example.com',
        ],
    ]));
    
    expect($document)->toBeInstanceOf(Doc::class)
        ->and($document->doc_type)->toBe('invoice')
        ->and($document->subtotal)->toBe(100.0)
        ->and($document->total)->toBe(100.0);
    
    $this->assertDatabaseHas('docs', [
        'id' => $document->id,
        'doc_type' => 'invoice',
    ]);
});
```

## Development Tools

The package includes the following development tools as specified:

- **Spatie Laravel Package Tools** - For package scaffolding and structure
- **Larastan** - PHPStan wrapper for Laravel, level 6 static analysis
- **Laravel Rector** - Automated code refactoring and upgrades
- **Laravel Pint** - Opinionated PHP code style fixer

Run these tools with:

```bash
composer format    # Run Laravel Pint
composer analyse   # Run Larastan
vendor/bin/rector  # Run Rector
```

## License

MIT License. See [LICENSE](LICENSE.md) for details.
