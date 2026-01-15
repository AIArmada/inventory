# Filament Docs

[![Latest Version on Packagist](https://img.shields.io/packagist/v/aiarmada/filament-docs.svg?style=flat-square)](https://packagist.org/packages/aiarmada/filament-docs)
[![Total Downloads](https://img.shields.io/packagist/dt/aiarmada/filament-docs.svg?style=flat-square)](https://packagist.org/packages/aiarmada/filament-docs)

Filament admin panel integration for the [AIArmada Docs](../docs) package. Manage invoices, receipts, document templates, and PDF generation directly from your Filament panel.

## Features

- 📄 **Document Management** - Full CRUD for invoices and receipts
- 📝 **Template System** - Create and manage document templates with PDF settings
- 📊 **Status Tracking** - Track document lifecycle with status history
- 📥 **PDF Generation** - Generate and download PDFs with one click
- 🔍 **Advanced Filtering** - Filter by type, status, date, and more
- ⚡ **Bulk Actions** - Generate PDFs and update status for multiple documents

## Requirements

- PHP 8.4+
- Laravel 12.0+
- Filament 5.0+
- [aiarmada/docs](../docs) package

## Installation

```bash
composer require aiarmada/filament-docs
```

Register the plugin in your Filament panel:

```php
use AIArmada\FilamentDocs\FilamentDocsPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentDocsPlugin::make(),
        ]);
}
```

Publish the configuration (optional):

```bash
php artisan vendor:publish --tag=filament-docs-config
```

## Resources

### DocResource

Manage documents with:

- **List View** - Sortable columns, advanced filters, search by document number/customer
- **Create/Edit** - Customer data, line items repeater, auto-calculated totals
- **View Page** - Document details, status actions, PDF download
- **Relation Manager** - View complete status history

### DocTemplateResource

Manage document templates with:

- **Template Settings** - Name, slug, document type, default designation
- **PDF Configuration** - Paper format, orientation, margins
- **Usage Statistics** - Track how many documents use each template

## Actions

| Action | Description |
|--------|-------------|
| Generate PDF | Create or regenerate document PDF |
| Download PDF | Download the generated PDF file |
| Mark as Sent | Update status to sent |
| Mark as Paid | Record payment with timestamp |
| Cancel | Cancel the document |
| Set as Default | Make template the default for its type |

## Configuration

```php
// config/filament-docs.php
return [
    'navigation' => [
        'group' => 'Documents',
    ],

    'features' => [
        'auto_generate_pdf' => true,
    ],

    'resources' => [
        'navigation_sort' => [
            'docs' => 10,
            'doc_templates' => 20,
            'sequences' => 90,
            'email_templates' => 91,
            'pending_approvals' => 15,
            'aging_report' => 100,
        ],
    ],
];
```

## Documentation

See the [docs](docs/) folder for detailed documentation:

- [Installation](docs/01-installation.md)
- [Resources](docs/02-resources.md)
- [Configuration](docs/03-configuration.md)

## License

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.
