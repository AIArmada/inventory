---
title: AIArmada Docs
---

# AIArmada Docs

A comprehensive document management package for Laravel with PDF generation, status workflows, email integration, and multi-tenancy support.

## Overview

AIArmada Docs provides everything you need to manage business documents like invoices, quotations, receipts, and custom document types. It includes:

- **Document Models** - Rich document model with line items, customer info, amounts
- **PDF Generation** - Via Spatie Laravel PDF with customizable templates
- **Status Workflows** - Draft → Pending → Sent → Paid with history tracking
- **Email Integration** - Email templates with variable substitution
- **Automatic Numbering** - Sequences with prefix, format, reset frequency
- **Approval Workflows** - Multi-step approval support
- **E-Invoice Submissions** - Track e-invoicing submissions
- **Multi-tenancy** - Full owner scoping via HasOwner trait

## Table of Contents

1. [Overview](00-overview.md) - Architecture and core concepts
2. [Installation](01-installation.md) - Setup and configuration
3. [Usage](02-usage.md) - Creating and managing documents
4. [PDF Generation](03-pdf-generation.md) - Generating PDF documents
5. [Status Management](04-status-management.md) - Status transitions and history
6. [Templates](05-templates.md) - Document templates
7. [Tailwind Usage](06-tailwind-usage.md) - Styling PDF templates with Tailwind
8. [Troubleshooting](99-troubleshooting.md) - Common issues and solutions

## Quick Start

```bash
composer require aiarmada/docs

php artisan vendor:publish --tag=docs-migrations
php artisan migrate
```

```php
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Enums\DocStatus;
use AIArmada\Docs\Enums\DocType;

$doc = Doc::create([
    'type' => DocType::Invoice,
    'status' => DocStatus::Draft,
    'customer_name' => 'Acme Corp',
    'customer_email' => 'billing@acme.com',
    'items' => [
        ['name' => 'Consulting', 'quantity' => 10, 'price' => 150],
    ],
    'subtotal' => 1500,
    'tax_rate' => 10,
    'tax_amount' => 150,
    'total' => 1650,
    'currency' => 'USD',
    'issue_date' => now(),
    'due_date' => now()->addDays(30),
]);

// Generate PDF
$doc->generatePdf();

// Send via email
$doc->sendEmail('billing@acme.com', 'Your Invoice #' . $doc->number);
```

## Requirements

| Requirement | Version |
|-------------|---------|
| PHP | 8.4+ |
| Laravel | 12.0+ |
| aiarmada/commerce-support | Required |
| spatie/laravel-pdf | Required |

## Related Packages

- **aiarmada/filament-docs** - Filament admin panel integration
- **aiarmada/commerce-support** - Multi-tenancy and shared utilities

## Support

For issues and feature requests, please use the [GitHub Issues](https://github.com/aiarmada/commerce/issues).
