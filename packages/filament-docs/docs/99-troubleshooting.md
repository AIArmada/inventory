---
title: Troubleshooting
---

# Troubleshooting

Common issues and solutions for the Filament Docs package.

## Resources Not Showing

### Navigation Empty

**Symptom:** Documents and Templates not visible in sidebar.

**Solutions:**

1. **Verify plugin registration**
   ```php
   ->plugins([
       FilamentDocsPlugin::make(),
   ])
   ```

2. **Clear caches**
   ```bash
   php artisan config:clear
   php artisan filament:cache-components
   ```

3. **Check navigation group config**
   ```php
   config('filament-docs.navigation.group'); // Should return string
   ```

### Resources Under Wrong Group

**Symptom:** Resources appear but in different navigation section.

**Solution:** Configure navigation group:

```php
// config/filament-docs.php
'navigation' => [
    'group' => 'Billing', // Change to your preferred group
],
```

---

## PDF Issues

### "Generate PDF" Action Fails

**Symptom:** Click action, nothing happens or error shown.

**Solutions:**

1. **Check base package setup**
   ```bash
   npm install puppeteer
   ```

2. **Verify storage disk exists**
   ```php
   Storage::disk(config('docs.storage.disk'))->exists('.'); // Should work
   ```

3. **Check template view exists**
   ```php
   view()->exists('docs::templates.doc-default');
   ```

### Download Returns 404

**Symptom:** PDF download link fails.

**Solutions:**

1. **Check pdf_path is set**
   ```php
   $doc->pdf_path; // Should be non-null after generate
   ```

2. **Verify file exists**
   ```php
   Storage::disk(config('docs.storage.disk'))
       ->exists($doc->pdf_path);
   ```

3. **Check route is registered**
   ```bash
   php artisan route:list | grep filament-docs
   ```

---

## Multi-Tenancy

### Seeing Other Tenant's Documents

**Symptom:** Documents from other tenants visible.

**Solutions:**

1. **Verify owner mode enabled**
   ```php
   config('docs.owner.enabled'); // Must be true
   ```

2. **Check owner resolver bound**
   The `OwnerResolverInterface` must be bound in your service provider.

3. **Verify resource uses scope**
   All resources use `DocsOwnerScope::apply()` in `getEloquentQuery()`.

### Cannot Create Documents

**Symptom:** Create form submits but fails silently.

**Solution:** Owner context must be resolvable:

```php
use AIArmada\CommerceSupport\Support\OwnerContext;

// This should return your tenant model
OwnerContext::resolve();
```

---

## Page Issues

### Aging Report Shows Wrong Data

**Symptom:** Amounts or buckets seem incorrect.

**Solutions:**

1. **Check date calculations**
   The report uses `CarbonImmutable::now()` for comparisons.

2. **Verify due_date is set**
   Documents without `due_date` are excluded from aging.

3. **Check status filtering**
   Only PENDING, SENT, PARTIALLY_PAID, OVERDUE statuses included.

### Pending Approvals Empty

**Symptom:** Page shows "No Pending Approvals" but approvals exist.

**Solutions:**

1. **Check assigned_to matches user**
   ```php
   DocApproval::where('assigned_to', auth()->id())
       ->where('status', 'pending')
       ->get();
   ```

2. **Verify doc owner scope**
   Approvals are filtered to documents the user can access.

---

## Widget Issues

### Stats Showing Zero

**Symptom:** DocStatsWidget shows all zeros.

**Solutions:**

1. **Check documents exist**
   ```php
   Doc::count(); // Should be > 0
   ```

2. **Check owner scoping**
   Widgets use `DocsOwnerScope::applyToDocs()` which may filter all docs.

### Charts Not Rendering

**Symptom:** Revenue chart blank.

**Solutions:**

1. **Check Filament Chart dependencies**
   Ensure chart library is properly loaded.

2. **Verify data exists**
   Charts need documents with `status = PAID` and valid totals.

---

## Form Issues

### Template Select Empty

**Symptom:** No templates available in document form.

**Solutions:**

1. **Create templates first**
   ```php
   DocTemplate::create([
       'name' => 'Default',
       'slug' => 'default',
       'view_name' => 'doc-default',
       'doc_type' => 'invoice',
       'is_default' => true,
   ]);
   ```

2. **Check owner scoping**
   Templates must match current owner or be global.

### Items Repeater Not Calculating

**Symptom:** Subtotal/total not updating when adding items.

**Solution:** The repeater uses Livewire reactivity. If not updating:

1. Check for JavaScript errors in console
2. Ensure Livewire is properly loaded
3. Try refreshing the page

---

## Route Issues

### Hardcoded Panel Name

**Symptom:** Links fail because route expects 'admin' panel.

**Current Limitation:** Some routes hardcode `'filament.admin.resources.docs.view'`.

**Workaround:** Extend the pages in your app and override the URL generation.

### Download Route 403

**Symptom:** PDF download returns 403 Forbidden.

**Solutions:**

1. **Check auth middleware**
   Route requires `web` and `auth` middleware.

2. **Check owner access**
   ```php
   DocsOwnerScope::assertCanAccessDoc($doc);
   ```

---

## Common Errors

### Class Not Found Errors

```
Class 'AIArmada\FilamentDocs\Resources\DocResource' not found
```

**Solution:**
```bash
composer dump-autoload
```

### View Not Found

```
View [filament-docs::pages.aging-report] not found
```

**Solution:** Publish views if needed:
```bash
php artisan vendor:publish --tag=filament-docs-views
```

### Icon Errors

```
Heroicon::OutlinedDocumentText constant not found
```

**Solution:** This indicates Filament v5 vs v4 mismatch. Ensure using Filament 5.0+.

---

## Performance

### List View Slow

**Symptom:** Documents list takes long to load.

**Solutions:**

1. **Add database indexes**
   Ensure indexes on `status`, `doc_type`, `issue_date`, `due_date`.

2. **Disable navigation badge**
   Badge counts query all documents:
   ```php
   public static function getNavigationBadge(): ?string
   {
       return null; // Disable
   }
   ```

3. **Use pagination**
   Default pagination should be enabled. Increase if needed.

### Widget Queries Slow

**Symptom:** Dashboard widgets slow.

**Solution:** Widgets query on every page load. Consider caching:

```php
Cache::remember('doc-stats', 300, fn () => $this->calculateStats());
```

---

## Getting Help

1. Check the [GitHub Issues](https://github.com/aiarmada/commerce/issues)
2. Review the base [docs package troubleshooting](../docs/docs/99-troubleshooting.md)
3. Ensure compatible versions: PHP 8.4+, Laravel 12+, Filament 5.0+
