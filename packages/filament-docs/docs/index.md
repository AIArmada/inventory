---
title: Filament Docs
---

# Filament Docs Documentation

Filament admin panel integration for the AIArmada Docs package.

## Overview

This package provides a complete Filament admin interface for managing documents (invoices, quotations, receipts) with full PDF generation, email integration, and multi-tenancy support. It includes:

- **DocResource** - Full CRUD for invoices, quotations, receipts with PDF/email
- **DocTemplateResource** - Configure templates with PDF settings
- **DocSequenceResource** - Manage automatic document numbering
- **DocEmailTemplateResource** - Email templates with variables
- **Reporting Pages** - Aging report and pending approvals
- **Dashboard Widgets** - Stats, charts, quick actions, recent documents

## Table of Contents

1. [Overview](00-overview.md) - Architecture and core concepts
2. [Installation](01-installation.md) - Setup and panel registration
3. [Resources](02-resources.md) - All resources in detail
4. [Configuration](03-configuration.md) - Plugin configuration
5. [Pages & Widgets](04-pages-widgets.md) - Reports and dashboard widgets
6. [Customization](05-customization.md) - Extending and customizing
7. [Troubleshooting](99-troubleshooting.md) - Common issues and solutions

## Quick Start

```bash
composer require aiarmada/filament-docs
```

```php
use AIArmada\FilamentDocs\FilamentDocsPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentDocsPlugin::make()
                ->navigationGroup('Billing')
                ->docStatsWidgetEnabled(true),
        ]);
}
```

## Requirements

| Requirement | Version |
|-------------|---------|
| PHP | 8.4+ |
| Laravel | 12.0+ |
| Filament | 5.0+ |
| aiarmada/docs | Required |
| aiarmada/commerce-support | Required |

## Features at a Glance

| Feature | Description |
|---------|-------------|
| Documents | Full CRUD with line items, customer info, amounts |
| Templates | PDF settings, custom views, per-type defaults |
| Sequences | Auto-numbering with prefix/format/reset options |
| Email | Templates with variable substitution |
| PDF | Generate, preview, download directly from panel |
| Multi-tenancy | Full owner scoping via HasOwner trait |
| Widgets | Stats, revenue chart, status breakdown, recent docs |
| Reports | Aging report, pending approvals pages |

## Support

For issues and feature requests, please use the [GitHub Issues](https://github.com/aiarmada/commerce/issues).
