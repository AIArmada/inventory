---
title: Basic Usage
---

# Basic Usage

## Creating Documents

Use the `DocService` to create documents:

```php
use AIArmada\Docs\Services\DocService;
use AIArmada\Docs\DataObjects\DocData;

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
    ],
    'customer_data' => [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'address' => '123 Main St',
        'city' => 'Kuala Lumpur',
        'postcode' => '50000',
        'country' => 'Malaysia',
    ],
    'notes' => 'Thank you for your business!',
    'generate_pdf' => true,
]));
```

## Document Types

The package supports multiple document types:

- **invoice** - Full invoice with line items, taxes, discounts
- **receipt** - Payment receipts

Configure types in `config/docs.php`:

```php
'types' => [
    'invoice' => [
        'default_template' => 'doc-default',
        'numbering' => [
            'strategy' => DefaultNumberStrategy::class,
            'prefix' => 'INV',
        ],
    ],
    'receipt' => [
        'default_template' => 'doc-default',
        'numbering' => [
            'strategy' => DefaultNumberStrategy::class,
            'prefix' => 'RCP',
        ],
    ],
],
```

## Automatic Calculations

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

## Linking to Models

Link documents to orders, tickets, or any model:

```php
use App\Models\Order;

$order = Order::find($orderId);

$document = $docService->createDoc(DocData::from([
    'doc_type' => 'invoice',
    'docable_type' => Order::class,
    'docable_id' => $order->id,
    'items' => [...],
    'customer_data' => [...],
]));

// Access linked model
$order = $document->docable;
```

## Querying Documents

```php
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Enums\DocStatus;

// Get paid invoices
$paidInvoices = Doc::where('doc_type', 'invoice')
    ->where('status', DocStatus::PAID)
    ->get();

// Get overdue invoices
$overdueInvoices = Doc::where('doc_type', 'invoice')
    ->where('status', DocStatus::OVERDUE)
    ->where('due_date', '<', now())
    ->get();

// Eager load relationships
$docs = Doc::with(['template', 'statusHistories', 'docable'])
    ->get();
```
