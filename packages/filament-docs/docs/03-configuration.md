---
title: Configuration
---

# Configuration

## Plugin Configuration (Fluent API)

Configure the plugin directly in your panel provider using the fluent API:

```php
use AIArmada\FilamentDocs\FilamentDocsPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentDocsPlugin::make()
                // Navigation
                ->navigationGroup('Billing')
                ->navigationSort(5)
                
                // Disable specific pages
                ->agingReportEnabled(false)
                ->pendingApprovalsEnabled(false)
                
                // Disable specific widgets
                ->docStatsWidgetEnabled(false)
                ->quickActionsWidgetEnabled(false)
                ->recentDocumentsWidgetEnabled(false)
                ->revenueChartWidgetEnabled(false)
                ->statusBreakdownWidgetEnabled(false),
        ]);
}
```

### Available Configuration Methods

| Method | Description | Default |
|--------|-------------|---------|
| `navigationGroup(string)` | Set navigation group | `'Documents'` (from config) |
| `navigationSort(int)` | Set navigation sort order | `null` |
| `docResource(string)` | Use custom DocResource class | `DocResource::class` |
| `agingReportEnabled(bool)` | Enable/disable Aging Report page | `true` |
| `pendingApprovalsEnabled(bool)` | Enable/disable Pending Approvals page | `true` |
| `docStatsWidgetEnabled(bool)` | Enable/disable stats widget | `true` |
| `quickActionsWidgetEnabled(bool)` | Enable/disable quick actions widget | `true` |
| `recentDocumentsWidgetEnabled(bool)` | Enable/disable recent documents widget | `true` |
| `revenueChartWidgetEnabled(bool)` | Enable/disable revenue chart widget | `true` |
| `statusBreakdownWidgetEnabled(bool)` | Enable/disable status breakdown widget | `true` |

### Custom DocResource

Extend the built-in resource with custom behavior:

```php
use AIArmada\FilamentDocs\Resources\DocResource;

class CustomDocResource extends DocResource
{
    public static function getNavigationLabel(): string
    {
        return 'Invoices';
    }
}

// In your panel provider:
FilamentDocsPlugin::make()
    ->docResource(CustomDocResource::class)
```

## Publishing Configuration

```bash
php artisan vendor:publish --tag=filament-docs-config
```

Creates `config/filament-docs.php`.

## Configuration File

```php
<?php

declare(strict_types=1);

return [
    // Navigation
    'navigation' => [
        'group' => 'Documents',
    ],

    // Features
    'features' => [
        'auto_generate_pdf' => true,
    ],

    // Resources
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

## Configuration Options

### Navigation Group

Change where resources appear in the sidebar:

```php
'navigation' => [
    'group' => 'Billing',
],
```

Set to `null` to remove grouping:

```php
'navigation' => [
    'group' => null,
],
```

### Navigation Sort Order

Control the order of resources within the group:

```php
'resources' => [
    'navigation_sort' => [
        'docs' => 5,           // Appears first
        'doc_templates' => 100, // Appears later
    ],
],
```

Lower numbers appear first in the navigation.

### Auto Generate PDF

Control automatic PDF generation on document creation:

```php
'features' => [
    'auto_generate_pdf' => true,  // Generate PDF automatically
    'auto_generate_pdf' => false, // Manual generation only
],
```

When disabled, use the "Generate PDF" action to create PDFs.

## Runtime Configuration

Access configuration values in your code:

```php
// Get navigation group
$group = config('filament-docs.navigation.group');

// Get resource sort order
$sortOrder = config('filament-docs.resources.navigation_sort.docs');

// Check if auto-generate is enabled
if (config('filament-docs.features.auto_generate_pdf', true)) {
    // Generate PDF logic
}
```

## Related Configuration

### Docs Package Configuration

The underlying docs package has its own configuration:

```bash
php artisan vendor:publish --tag=docs-config
```

Key settings in `config/docs.php`:

- **company** - Default company information
- **numbering** - Document number generation strategies
- **storage** - PDF storage disk and path
- **pdf** - Default PDF settings

### Storage Configuration

Configure where PDFs are stored in `config/docs.php`:

```php
'storage' => [
    'disk' => 'local',
    'path' => 'docs',
],
```

For S3 storage:

```php
'storage' => [
    'disk' => 's3',
    'path' => 'documents/pdfs',
],
```
